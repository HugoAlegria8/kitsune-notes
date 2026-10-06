<?php

declare(strict_types=1);

namespace KitsuneNotes\Support;

use Throwable;

/**
 * Cliente SMTP mínimo y sin dependencias para entregar un mensaje ya
 * construido (véase MimeMessage) a un servidor de correo real.
 *
 * Qué soporta:
 *  - STARTTLS (puerto 587, RFC 3207) y TLS implícito (puerto 465).
 *  - Autenticación AUTH PLAIN y AUTH LOGIN (RFC 4954).
 *  - Doble punto inicial (dot-stuffing) y cierre correcto de DATA (RFC 5321).
 *
 * Decisiones de seguridad:
 *  - La verificación del certificado del servidor está SIEMPRE activa: no hay
 *    ninguna opción para desactivarla. Si el sistema no encuentra los
 *    certificados de confianza (típico en PHP para Windows) se indica un
 *    fichero con KN_SMTP_CAFILE.
 *  - Con STARTTLS, si el servidor no ofrece cifrado no se envía nada «por si
 *    acaso». Solo TLS 1.2 o superior.
 *  - Con cifrado «none» la contraseña únicamente se envía hacia localhost.
 *  - Los mensajes de error nunca incluyen credenciales.
 *
 * No pretende ser un cliente de propósito general: no reintenta, no mantiene
 * la conexión abierta entre mensajes y no construye el mensaje (eso lo hace
 * MimeMessage). Es suficiente para el correo transaccional de la tienda y
 * se puede sustituir por PHPMailer o Symfony Mailer tocando solo Mailer.
 */
final class SmtpClient
{
    public const ENCRYPTION_STARTTLS = 'tls';
    public const ENCRYPTION_IMPLICIT = 'ssl';
    public const ENCRYPTION_NONE     = 'none';

    /** @var resource|null */
    private $socket = null;

    /** Instante (microtime) a partir del cual se abandona el envío. */
    private float $deadline = 0.0;

    /** @var list<string> capacidades anunciadas en el último EHLO, en mayúsculas */
    private array $capabilities = [];

    public function __construct(
        private readonly string $host,
        private readonly int $port = 587,
        private readonly string $encryption = self::ENCRYPTION_STARTTLS,
        private readonly string $username = '',
        private readonly string $password = '',
        private readonly int $timeout = 10,
        private readonly string $ehloName = 'localhost',
        private readonly string $caFile = '',
    ) {
    }

    /**
     * Entrega un mensaje completo (cabeceras + cuerpo) a los destinatarios.
     *
     * @param list<string> $recipients direcciones de correo (solo ASCII)
     *
     * @return string respuesta con la que el servidor aceptó el mensaje
     *
     * @throws SmtpException si algún paso falla; el mensaje explica cuál
     */
    public function send(string $from, array $recipients, string $message): string
    {
        $this->assertUsable();

        $from       = $this->address($from);
        $recipients = array_map(fn (string $to): string => $this->address($to), $recipients);

        if ($recipients === []) {
            throw new SmtpException('No hay ningún destinatario al que enviar el mensaje.');
        }

        // Tope global: aunque cada lectura tenga su propio tiempo de espera,
        // el cliente no puede retener la petición del cliente web indefinidamente.
        $this->deadline = microtime(true) + max(8, $this->timeout * 3);

        try {
            $this->connect();

            $this->expect($this->read(), [220], 'saludo');
            $this->ehlo();

            if ($this->encryption === self::ENCRYPTION_STARTTLS) {
                $this->startTls();
            }

            $this->authenticate();

            $this->command('MAIL FROM:<' . $from . '>', [250], 'MAIL FROM');

            foreach ($recipients as $recipient) {
                $this->command('RCPT TO:<' . $recipient . '>', [250, 251], 'RCPT TO');
            }

            $this->command('DATA', [354], 'DATA');
            $this->write($this->dotStuff($message) . ".\r\n");

            [$code, $text] = $this->expect($this->read(), [250], 'DATA');

            return $code . ' ' . $this->tidy($text);
        } finally {
            $this->close();
        }
    }

    // -----------------------------------------------------------------
    // Comprobaciones previas
    // -----------------------------------------------------------------

    private function assertUsable(): void
    {
        if ($this->host === '') {
            throw new SmtpException('Falta KN_SMTP_HOST: indica en el fichero .env el servidor SMTP (por ejemplo smtp.gmail.com).');
        }

        if ($this->port < 1 || $this->port > 65535) {
            throw new SmtpException('KN_SMTP_PORT no es un puerto válido (usa 587 con STARTTLS o 465 con SSL).');
        }

        if (!in_array($this->encryption, [self::ENCRYPTION_STARTTLS, self::ENCRYPTION_IMPLICIT, self::ENCRYPTION_NONE], true)) {
            throw new SmtpException('KN_SMTP_ENCRYPTION debe ser «tls» (STARTTLS, puerto 587), «ssl» (puerto 465) o «none».');
        }

        if ($this->encryption !== self::ENCRYPTION_NONE && !extension_loaded('openssl')) {
            throw new SmtpException(
                'La extensión openssl de PHP no está activada, y hace falta para el correo cifrado. '
                . 'En Windows, quita el «;» de la línea extension=openssl en php.ini y vuelve a arrancar el servidor.'
            );
        }

        if ($this->encryption === self::ENCRYPTION_NONE && $this->username !== '' && !$this->isLoopback($this->host)) {
            throw new SmtpException(
                'Con KN_SMTP_ENCRYPTION=none la contraseña viajaría sin cifrar: '
                . 'solo se permite hacia localhost. Usa «tls» o «ssl».'
            );
        }

        if ($this->caFile !== '' && !is_readable($this->caFile)) {
            throw new SmtpException('KN_SMTP_CAFILE apunta a un fichero que no existe o no se puede leer: ' . $this->caFile);
        }
    }

    private function isLoopback(string $host): bool
    {
        $host = strtolower(trim($host, '[]'));

        return $host === 'localhost' || $host === '::1' || str_starts_with($host, '127.');
    }

    /** Valida una dirección para usarla en un comando SMTP (evita inyectar comandos). */
    private function address(string $email): string
    {
        $email = trim($email);

        if ($email === ''
            || preg_match('/[\x00-\x20\x7F-\xFF<>"\\\\]/', $email) === 1
            || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new SmtpException('La dirección «' . $this->tidy($email) . '» no es válida para el envío por SMTP.');
        }

        return $email;
    }

    // -----------------------------------------------------------------
    // Conexión y cifrado
    // -----------------------------------------------------------------

    private function connect(): void
    {
        $host = str_contains($this->host, ':') && !str_starts_with($this->host, '[')
            ? '[' . $this->host . ']'
            : $this->host;

        $target  = sprintf('%s://%s:%d', $this->encryption === self::ENCRYPTION_IMPLICIT ? 'ssl' : 'tcp', $host, $this->port);
        $context = stream_context_create(['ssl' => $this->sslOptions()]);

        $errno  = 0;
        $errstr = '';

        [$socket, $warnings] = $this->collectWarnings(
            function () use ($target, $context, &$errno, &$errstr) {
                return stream_socket_client($target, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT, $context);
            }
        );

        if ($socket === false) {
            // Si el fallo es de TLS, la causa está en los avisos de PHP (certificado
            // inválido…); si no, en el texto del error del sistema («Connection refused»).
            $detail = stripos($warnings, 'ssl') !== false || stripos($warnings, 'crypto') !== false
                ? $warnings
                : ($errstr !== '' ? $errstr : $warnings);

            throw new SmtpException(sprintf(
                'No se pudo conectar con %s:%d (%s).%s',
                $this->host,
                $this->port,
                $this->tidy($detail !== '' ? $detail : 'sin detalle'),
                $this->certificateHint($detail)
            ));
        }

        stream_set_timeout($socket, $this->timeout);
        $this->socket = $socket;
    }

    /** @return array<string, mixed> */
    private function sslOptions(): array
    {
        $options = [
            'verify_peer'         => true,
            'verify_peer_name'    => true,
            'allow_self_signed'   => false,
            'peer_name'           => trim($this->host, '[]'),
            'SNI_enabled'         => true,
            'disable_compression' => true,
            'crypto_method'       => $this->cryptoMethod(),
        ];

        if ($this->caFile !== '') {
            $options['cafile'] = $this->caFile;
        }

        return $options;
    }

    /** Solo TLS 1.2 y 1.3: nada anterior se considera seguro. */
    private function cryptoMethod(): int
    {
        $method = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;

        if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
            $method |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
        }

        return $method;
    }

    private function startTls(): void
    {
        if (!in_array('STARTTLS', $this->capabilities, true)) {
            throw new SmtpException(
                'El servidor no ofrece STARTTLS, así que no se envía nada sin cifrar. '
                . 'Prueba con KN_SMTP_ENCRYPTION=ssl y el puerto 465.'
            );
        }

        $this->command('STARTTLS', [220], 'STARTTLS');

        [$enabled, $reason] = $this->collectWarnings(
            fn () => stream_socket_enable_crypto($this->socket, true, $this->cryptoMethod())
        );

        if ($enabled !== true) {
            throw new SmtpException(
                'No se pudo cifrar la conexión con el servidor (TLS).'
                . ($reason !== '' ? ' Detalle: ' . rtrim($this->tidy($reason), '.') . '.' : '')
                . $this->certificateHint($reason)
            );
        }

        // Tras el cifrado hay que presentarse de nuevo: las capacidades
        // (en particular AUTH) pueden cambiar.
        $this->ehlo();
    }

    private function certificateHint(string $reason): string
    {
        if (preg_match('/certificate verify failed|unable to get (local )?issuer certificate|self[- ]signed/i', $reason) !== 1) {
            return '';
        }

        return ' No se puede verificar el certificado del servidor: en Windows suele faltar el paquete de '
            . 'certificados de confianza. Descarga https://curl.se/ca/cacert.pem, guárdalo (por ejemplo en '
            . 'C:\\php\\cacert.pem) y escribe su ruta en KN_SMTP_CAFILE del fichero .env.';
    }

    // -----------------------------------------------------------------
    // Diálogo SMTP
    // -----------------------------------------------------------------

    private function ehlo(): void
    {
        [, $text] = $this->command('EHLO ' . $this->ehloName, [250], 'EHLO');

        $this->capabilities = array_values(array_filter(array_map(
            static fn (string $line): string => strtoupper(trim($line)),
            explode("\n", $text)
        )));
    }

    private function authenticate(): void
    {
        if ($this->username === '') {
            return;
        }

        $mechanisms = [];

        foreach ($this->capabilities as $capability) {
            if (preg_match('/^AUTH[ =](.+)$/', $capability, $match) === 1) {
                $mechanisms = array_merge($mechanisms, preg_split('/\s+/', trim($match[1])) ?: []);
            }
        }

        if ($mechanisms === []) {
            throw new SmtpException(
                'El servidor no ofrece autenticación en esta conexión. '
                . 'Revisa KN_SMTP_PORT y KN_SMTP_ENCRYPTION (para Gmail: 587 con «tls», o 465 con «ssl»).'
            );
        }

        if (in_array('PLAIN', $mechanisms, true)) {
            $this->command('AUTH PLAIN ' . base64_encode("\0" . $this->username . "\0" . $this->password), [235], 'AUTH');

            return;
        }

        if (in_array('LOGIN', $mechanisms, true)) {
            $this->command('AUTH LOGIN', [334], 'AUTH');
            $this->command(base64_encode($this->username), [334], 'AUTH');
            $this->command(base64_encode($this->password), [235], 'AUTH');

            return;
        }

        throw new SmtpException(
            'El servidor solo ofrece métodos de autenticación que este cliente no soporta ('
            . implode(', ', $mechanisms) . '); hacen falta PLAIN o LOGIN.'
        );
    }

    /**
     * Envía una línea de comando y comprueba la respuesta. El parámetro
     * $step es lo único que acaba en los mensajes de error: así una
     * contraseña enviada en el comando nunca se filtra.
     *
     * @param list<int> $expected
     *
     * @return array{0:int, 1:string}
     */
    private function command(string $line, array $expected, string $step): array
    {
        $this->write($line . "\r\n");

        return $this->expect($this->read(), $expected, $step);
    }

    /**
     * @param array{0:int, 1:string} $reply
     * @param list<int>              $expected
     *
     * @return array{0:int, 1:string}
     */
    private function expect(array $reply, array $expected, string $step): array
    {
        if (in_array($reply[0], $expected, true)) {
            return $reply;
        }

        $what = match ($step) {
            'AUTH'      => 'El servidor rechazó el usuario o la contraseña',
            'MAIL FROM' => 'El servidor no acepta el remitente',
            'RCPT TO'   => 'El servidor rechazó al destinatario',
            'DATA'      => 'El servidor no aceptó el mensaje',
            'STARTTLS'  => 'El servidor no quiso iniciar el cifrado',
            'saludo'    => 'El servidor no acepta conexiones en este momento',
            default     => 'El servidor devolvió un error en ' . $step,
        };

        $text = rtrim($this->tidy($reply[1]), '.');

        // Con Gmail el fallo típico es usar la contraseña normal de la cuenta en
        // lugar de una contraseña de aplicación: se dice sin esperar a que el
        // usuario descifre el código 534/535 del servidor. El texto del servidor
        // se acorta para que la pista quepa siempre en el detalle que se guarda.
        $hint = '';

        if ($step === 'AUTH' && $this->isGoogleHost()) {
            $text = rtrim(mb_strimwidth($text, 0, 200, '…'), '.');
            $hint = ' Con Gmail hace falta una contraseña de aplicación (no la contraseña normal de la cuenta) '
                . 'y la verificación en dos pasos activada: consulta el README, apartado «Envío real de correos».';
        }

        throw new SmtpException(sprintf('%s (%d %s).%s', $what, $reply[0], $text, $hint));
    }

    private function isGoogleHost(): bool
    {
        $host = strtolower($this->host);

        foreach (['gmail.com', 'googlemail.com', 'google.com'] as $domain) {
            if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Lee una respuesta completa (puede ocupar varias líneas «250-…»).
     *
     * @return array{0:int, 1:string} código y texto (líneas separadas por «\n»)
     */
    private function read(): array
    {
        $lines = [];
        $code  = 0;

        do {
            $this->checkDeadline();

            $line = @fgets($this->socket, 4096);

            if ($line === false) {
                $timedOut = (bool) (stream_get_meta_data($this->socket)['timed_out'] ?? false);

                throw new SmtpException($timedOut
                    ? 'El servidor SMTP no responde (se agotó el tiempo de espera).'
                    : 'El servidor SMTP cerró la conexión de forma inesperada.');
            }

            $line = rtrim($line, "\r\n");

            if (preg_match('/^(\d{3})(?:([ -])(.*))?$/s', $line, $match) !== 1) {
                throw new SmtpException('El servidor SMTP envió una respuesta que no se entiende: «' . $this->tidy($line) . '».');
            }

            $code    = (int) $match[1];
            $lines[] = $match[3] ?? '';
            $more    = ($match[2] ?? ' ') === '-';
        } while ($more && count($lines) < 200);

        return [$code, implode("\n", $lines)];
    }

    private function write(string $data): void
    {
        $length  = strlen($data);
        $written = 0;

        while ($written < $length) {
            $this->checkDeadline();

            $bytes = @fwrite($this->socket, substr($data, $written));

            if ($bytes === false || $bytes === 0) {
                $timedOut = (bool) (stream_get_meta_data($this->socket)['timed_out'] ?? false);

                throw new SmtpException($timedOut
                    ? 'Se agotó el tiempo de espera al enviar datos al servidor SMTP.'
                    : 'Se perdió la conexión con el servidor SMTP mientras se enviaba el mensaje.');
            }

            $written += $bytes;
        }
    }

    private function checkDeadline(): void
    {
        if (microtime(true) > $this->deadline) {
            throw new SmtpException('Se agotó el tiempo máximo permitido para enviar el correo.');
        }
    }

    /**
     * Prepara el cuerpo para DATA: saltos de línea CRLF y, a toda línea que
     * empiece por «.», un punto más delante (RFC 5321, 4.5.2). Sin esto, un
     * punto solo en una línea cortaría el mensaje.
     */
    private function dotStuff(string $message): string
    {
        $message = str_replace(["\r\n", "\r"], "\n", $message);
        $message = str_replace("\n", "\r\n", $message);
        $message = (string) preg_replace('/^\./m', '..', $message);

        return str_ends_with($message, "\r\n") ? $message : $message . "\r\n";
    }

    /**
     * Ejecuta una función de sockets recogiendo los avisos que PHP emite
     * (certificado inválido, conexión rechazada…), que son la causa real del
     * fallo y no se pueden obtener de otra forma.
     *
     * @return array{0:mixed, 1:string} resultado y avisos, ya sin el prefijo de la función
     */
    private function collectWarnings(callable $call): array
    {
        $messages = [];

        set_error_handler(static function (int $severity, string $message) use (&$messages): bool {
            $message = trim((string) preg_replace('/^[a-z_]+\(\): /', '', $message));

            // Avisos genéricos que PHP añade tras la causa real: no aportan nada.
            if ($message !== ''
                && preg_match('/^(Failed to enable crypto|Unable to connect to .*\(Unknown error\))$/', $message) !== 1) {
                $messages[] = $message;
            }

            return true;
        });

        try {
            $result = $call();
        } finally {
            restore_error_handler();
        }

        return [$result, implode(' ', array_unique($messages))];
    }

    /** Una sola línea y de longitud acotada, para mensajes de error. */
    private function tidy(string $text): string
    {
        $text = trim((string) preg_replace('/\s+/', ' ', $text));

        return mb_strlen($text) > 300 ? mb_substr($text, 0, 297) . '...' : $text;
    }

    /** Cierra la conexión sin lanzar nunca excepciones (se llama desde finally). */
    private function close(): void
    {
        if (!is_resource($this->socket)) {
            $this->socket = null;

            return;
        }

        try {
            @stream_set_timeout($this->socket, 2);
            @fwrite($this->socket, "QUIT\r\n");
            @fgets($this->socket, 1024);
        } catch (Throwable) {
            // Da igual: la conexión se cierra de todos modos.
        }

        @fclose($this->socket);
        $this->socket = null;
    }
}
