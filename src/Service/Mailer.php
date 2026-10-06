<?php

declare(strict_types=1);

namespace KitsuneNotes\Service;

use DateTimeImmutable;
use DateTimeZone;
use KitsuneNotes\Repository\MailRepository;
use KitsuneNotes\Support\MimeMessage;
use KitsuneNotes\Support\SmtpClient;
use KitsuneNotes\Support\Uuid;
use Throwable;

/**
 * Envío de correos transaccionales.
 *
 * Es el único punto de la aplicación que «envía» correo, y funciona en dos
 * modos que se eligen con KN_MAIL_TRANSPORT en el fichero .env:
 *
 *  - «buzon» (por defecto): ningún mensaje sale de la aplicación. Cada correo
 *    se guarda completo (cabeceras, HTML y texto plano) en el buzón de pruebas
 *    —tabla mail_outbox—, que el personal consulta desde el back-office y
 *    puede descargar como fichero .eml. Los destinatarios de los datos de
 *    demostración usan el dominio reservado .test, que no puede recibir correo.
 *
 *  - «smtp»: además de guardarse en el buzón, el mensaje se entrega por SMTP
 *    (SmtpClient) con la cuenta de correo configurada por el equipo.
 *
 * Garantías de seguridad del envío real:
 *  - Está apagado por defecto y las credenciales solo viven en el .env local.
 *  - Solo se envía a direcciones autorizadas expresamente (KN_MAIL_ALLOWED_TO).
 *    A cualquier otra se la trata como en el modo «buzon». Así, un tercero
 *    que haga un pedido con el correo de otra persona no puede usar la tienda
 *    para escribirle.
 *  - Un fallo de entrega nunca rompe la compra: el correo queda guardado y
 *    marcado como «fallido», con el motivo, para verlo en el back-office.
 *
 * Instrumentación: cada correo emite el evento email.sent con el resultado
 * de la entrega. Por privacidad la dirección del destinatario no se incluye
 * en el evento (que se exporta a otros sistemas), ni tampoco el texto del
 * error del servidor, que puede contenerla.
 */
final class Mailer
{
    /** El mensaje solo está en el buzón de pruebas (nada ha salido de la aplicación). */
    public const DELIVERY_LOCAL = 'solo_buzon';

    /** El servidor SMTP aceptó el mensaje (no garantiza que llegue a la bandeja de entrada). */
    public const DELIVERY_SENT = 'enviado';

    /** Se intentó la entrega por SMTP y falló; el motivo está en delivery_detail. */
    public const DELIVERY_FAILED = 'fallido';

    private bool $deliveryDisabled = false;

    /**
     * @param array{
     *     from_email?:string, from_name?:string, transport?:string, allowed_to?:list<string>,
     *     smtp?:array{host?:string, port?:int, encryption?:string, username?:string, password?:string,
     *                 timeout?:int, cafile?:string, ehlo?:string}
     * } $config
     */
    public function __construct(
        private readonly MailRepository $outbox,
        private readonly EventRecorder $events,
        private readonly array $config,
    ) {
    }

    /**
     * Apaga la entrega real durante el resto de la petición. El instalador lo
     * usa al generar los correos históricos de demostración: jamás deben salir.
     */
    public function disableDelivery(): void
    {
        $this->deliveryDisabled = true;
    }

    /** ¿Está activado el envío real por SMTP? (no implica que haya destinatarios autorizados) */
    public function smtpEnabled(): bool
    {
        return !$this->deliveryDisabled && $this->transport() === 'smtp';
    }

    /** ¿Se entregaría de verdad un correo a esta dirección? Lo usan las plantillas para ajustar el pie. */
    public function willDeliver(string $email): bool
    {
        return $this->smtpEnabled() && $this->isAllowed($email);
    }

    /**
     * Estado del envío para el back-office. Las direcciones autorizadas se
     * muestran enmascaradas (d***@gmail.com).
     *
     * @return array{transport:string, host:string, port:int, encryption:string, from:string,
     *               allowed:list<string>, problems:list<string>}
     */
    public function status(): array
    {
        $smtp      = (array) ($this->config['smtp'] ?? []);
        $transport = $this->transport();
        $problems  = [];

        if ($transport === 'smtp') {
            if ((string) ($smtp['host'] ?? '') === '') {
                $problems[] = 'Falta KN_SMTP_HOST.';
            }

            if ($this->allowedEntries() === []) {
                $problems[] = 'No hay ninguna dirección autorizada en KN_MAIL_ALLOWED_TO, así que no se enviará ningún correo real.';
            }

            if (str_ends_with(strtolower((string) ($this->config['from_email'] ?? '')), '.test')) {
                $problems[] = 'KN_MAIL_FROM sigue siendo la dirección de prueba (.test): pon la dirección de tu cuenta de correo.';
            }
        }

        return [
            'transport'  => $transport,
            'host'       => (string) ($smtp['host'] ?? ''),
            'port'       => (int) ($smtp['port'] ?? 587),
            'encryption' => (string) ($smtp['encryption'] ?? 'tls'),
            'from'       => (string) ($this->config['from_email'] ?? ''),
            'allowed'    => array_map($this->mask(...), $this->allowedEntries()),
            'problems'   => $problems,
        ];
    }

    /**
     * @param array{
     *     template:string, to_email:string, to_name?:string, subject:string, html:string, text:string,
     *     order_id?:int|null, customer_id?:int|null, invoice_id?:int|null,
     *     order_reference?:string|null, invoice_number?:string|null
     * } $message
     *
     * @return array<string, mixed> el mensaje almacenado, con su identificador y el resultado de la entrega
     */
    public function send(array $message, ?DateTimeImmutable $at = null): array
    {
        $at ??= new DateTimeImmutable('now', new DateTimeZone('Europe/Madrid'));

        $fromEmail = (string) ($this->config['from_email'] ?? 'pedidos@kitsunenotes.test');
        $fromName  = (string) ($this->config['from_name'] ?? 'Kitsune Notes');
        $domain    = substr((string) strrchr($fromEmail, '@'), 1) ?: 'localhost';

        $row = [
            'message_id'      => Uuid::v4() . '@' . $domain,
            'template'        => (string) $message['template'],
            'to_email'        => $this->clean((string) $message['to_email']),
            'to_name'         => $this->clean((string) ($message['to_name'] ?? '')),
            'from_email'      => $fromEmail,
            'from_name'       => $fromName,
            'subject'         => $this->clean((string) $message['subject']),
            'body_html'       => (string) $message['html'],
            'body_text'       => (string) $message['text'],
            'order_id'        => isset($message['order_id']) ? (int) $message['order_id'] : null,
            'invoice_id'      => isset($message['invoice_id']) ? (int) $message['invoice_id'] : null,
            'created_at'      => $at->format(DATE_ATOM),
            'read_at'         => null,
            'delivery_status' => self::DELIVERY_LOCAL,
            'delivery_detail' => '',
            'delivered_at'    => null,
        ];

        // Primero se guarda en el buzón: pase lo que pase con el envío real,
        // el correo queda registrado y el personal puede verlo.
        $row['id'] = $this->outbox->insert($row);

        [$status, $detail, $deliveredAt] = $this->deliver($row);

        if ($status !== self::DELIVERY_LOCAL || $detail !== '') {
            $this->outbox->markDelivery((int) $row['id'], $status, $detail, $deliveredAt);
            $row['delivery_status'] = $status;
            $row['delivery_detail'] = $detail;
            $row['delivered_at']    = $deliveredAt;
        }

        $this->events->record(
            EventRecorder::EMAIL_SENT,
            [
                'plantilla'         => $row['template'],
                'asunto'            => $row['subject'],
                'id_mensaje'        => $row['message_id'],
                'referencia_pedido' => $message['order_reference'] ?? null,
                'numero_factura'    => $message['invoice_number'] ?? null,
                'canal'             => $status === self::DELIVERY_LOCAL ? 'buzon_pruebas' : 'smtp',
                'entrega'           => $status,
            ],
            [
                'order_id'    => $row['order_id'],
                'customer_id' => isset($message['customer_id']) ? (int) $message['customer_id'] : null,
                'actor_type'  => 'sistema',
            ]
        );

        return $row;
    }

    /**
     * Decide si hay que entregar el mensaje por SMTP y, en tal caso, lo hace.
     * Nunca lanza excepciones: cualquier fallo se convierte en un estado.
     *
     * @param array<string, mixed> $row
     *
     * @return array{0:string, 1:string, 2:?string} estado, detalle y fecha de entrega
     */
    private function deliver(array $row): array
    {
        if (!$this->smtpEnabled()) {
            return [self::DELIVERY_LOCAL, '', null];
        }

        if (!$this->isAllowed((string) $row['to_email'])) {
            return [
                self::DELIVERY_LOCAL,
                'Esta dirección no está en KN_MAIL_ALLOWED_TO, así que el correo solo se guarda en el buzón de pruebas.',
                null,
            ];
        }

        $smtp = (array) ($this->config['smtp'] ?? []);

        try {
            $client = new SmtpClient(
                (string) ($smtp['host'] ?? ''),
                (int) ($smtp['port'] ?? 587),
                (string) ($smtp['encryption'] ?? 'tls'),
                (string) ($smtp['username'] ?? ''),
                (string) ($smtp['password'] ?? ''),
                max(3, (int) ($smtp['timeout'] ?? 10)),
                (string) ($smtp['ehlo'] ?? 'localhost'),
                (string) ($smtp['cafile'] ?? ''),
            );

            $reply = $client->send(
                (string) $row['from_email'],
                [(string) $row['to_email']],
                MimeMessage::build($row, simulated: false)
            );

            return [
                self::DELIVERY_SENT,
                'Aceptado por ' . ($smtp['host'] ?? '') . ': ' . $reply,
                (new DateTimeImmutable('now', new DateTimeZone('Europe/Madrid')))->format(DATE_ATOM),
            ];
        } catch (Throwable $e) {
            error_log('[kitsune-notes] Fallo al enviar el correo ' . $row['message_id'] . ' por SMTP: ' . $e->getMessage());

            return [self::DELIVERY_FAILED, mb_substr($e->getMessage(), 0, 480), null];
        }
    }

    private function transport(): string
    {
        return strtolower(trim((string) ($this->config['transport'] ?? 'buzon'))) === 'smtp' ? 'smtp' : 'buzon';
    }

    /** @return list<string> direcciones («a@b.es») y dominios («@b.es») autorizados, en minúsculas */
    private function allowedEntries(): array
    {
        $entries = [];

        foreach ((array) ($this->config['allowed_to'] ?? []) as $entry) {
            $entry = strtolower(trim((string) $entry));

            if ($entry !== '') {
                $entries[] = $entry;
            }
        }

        return array_values(array_unique($entries));
    }

    /**
     * Una dirección está autorizada si coincide exactamente con una entrada de
     * KN_MAIL_ALLOWED_TO o si la entrada es un dominio («@dominio.es»).
     */
    private function isAllowed(string $email): bool
    {
        $email = strtolower(trim($email));

        if ($email === '' || substr_count($email, '@') !== 1) {
            return false;
        }

        foreach ($this->allowedEntries() as $entry) {
            if ($entry === $email) {
                return true;
            }

            if (str_starts_with($entry, '@') && str_ends_with($email, $entry)) {
                return true;
            }
        }

        return false;
    }

    /** d***@gmail.com — los dominios autorizados («@dominio.es») se muestran completos. */
    private function mask(string $entry): string
    {
        if (str_starts_with($entry, '@')) {
            return $entry;
        }

        $at = strpos($entry, '@');

        return $at === false ? '***' : substr($entry, 0, 1) . '***' . substr($entry, $at);
    }

    /** Una sola línea, sin caracteres de control (evita inyectar cabeceras). */
    private function clean(string $value): string
    {
        return trim((string) preg_replace('/[\x00-\x1F\x7F]+/', ' ', $value));
    }
}
