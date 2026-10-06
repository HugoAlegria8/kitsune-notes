<?php
/**
 * Comprobación del envío real de correos.
 *
 * Uso:
 *   php bin/probar-correo.php destino@ejemplo.com
 *
 * Envía un mensaje de prueba con exactamente la misma configuración y el
 * mismo código que la tienda (Mailer + SmtpClient) y explica el resultado.
 * Es la forma más rápida de comprobar el fichero .env sin hacer una compra.
 *
 * La dirección de destino debe figurar en KN_MAIL_ALLOWED_TO. El script no
 * muestra nunca la contraseña SMTP. El mensaje queda también en el buzón de
 * pruebas del back-office (plantilla «Prueba de envío (SMTP)»).
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script solo puede ejecutarse desde la línea de comandos.\n");
}

// En la consola de Windows, que los acentos salgan bien.
if (function_exists('sapi_windows_cp_set')) {
    @sapi_windows_cp_set(65001);
}

/** @var \KitsuneNotes\Core\App $app */
$app = require dirname(__DIR__) . '/src/bootstrap.php';

$to = trim((string) ($argv[1] ?? ''));

if ($to === '' || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
    fwrite(STDERR, "Uso:  php bin/probar-correo.php tu.direccion@ejemplo.com\n");
    fwrite(STDERR, "Indica la dirección a la que enviar el mensaje de prueba.\n");
    exit(2);
}

$mailer = $app->mailer();
$status = $mailer->status();

echo "Kitsune Notes · prueba del envío de correo\n";
echo "--------------------------------------------\n";

if ($status['transport'] !== 'smtp') {
    echo "[!] El envío real está desactivado (KN_MAIL_TRANSPORT no es «smtp»).\n";
    echo "    Los correos solo se guardan en el buzón de pruebas del back-office.\n";
    echo "    Para activarlo, añade al fichero .env las líneas de la sección\n";
    echo "    «Envío real de correos» de .env.example (guía completa en el README).\n";
    exit(1);
}

echo 'Servidor:      ' . ($status['host'] !== '' ? $status['host'] : '(sin configurar)')
    . ':' . $status['port'] . ' · cifrado ' . $status['encryption'] . "\n";
echo 'Remitente:     ' . $status['from'] . "\n";
echo 'Autorizadas:   ' . ($status['allowed'] === [] ? '(ninguna)' : implode(', ', $status['allowed'])) . "\n";

foreach ($status['problems'] as $problem) {
    echo '[!] ' . $problem . "\n";
}

if (!$mailer->willDeliver($to)) {
    echo "\n[ERROR] La dirección {$to} no está autorizada.\n";
    echo "        Añádela a KN_MAIL_ALLOWED_TO en el fichero .env y vuelve a probar.\n";
    exit(1);
}

echo "\nEnviando un mensaje de prueba a {$to} ...\n";

try {
    // Una comprobación de la configuración no es actividad de la tienda:
    // no emite evento. Sí queda el mensaje en el buzón de pruebas.
    $app->events()->mute();

    $mail = $app->notifier()->smtpTest($to);
} catch (Throwable $e) {
    echo "\n[ERROR] No se pudo preparar el mensaje: " . $e->getMessage() . "\n";
    echo "        ¿Has creado la base de datos con «php bin/install.php»?\n";
    exit(1);
}

if ($mail['delivery_status'] === 'enviado') {
    echo "\n[OK] El servidor ha aceptado el mensaje.\n";
    echo '     ' . $mail['delivery_detail'] . "\n\n";
    echo "Mira tu bandeja de entrada (y la carpeta de spam, por si acaso).\n";
    echo "Con esto, las compras hechas con una dirección autorizada enviarán la factura de verdad.\n";
    exit(0);
}

echo "\n[ERROR] El mensaje NO se ha enviado.\n";
echo '        ' . ($mail['delivery_detail'] !== '' ? $mail['delivery_detail'] : 'Motivo desconocido.') . "\n";
exit(1);
