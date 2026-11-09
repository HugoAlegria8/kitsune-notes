<?php

declare(strict_types=1);

/**
 * Envía a n8n un payload de prueba firmado exactamente como lo firman la
 * tienda y el back-office (HMAC-SHA256 de «marca_de_tiempo.cuerpo»).
 *
 *   php integracion/scripts/enviar-evento.php FICHERO URL_DEL_WEBHOOK SECRETO [--nuevo]
 *
 * Ejemplos (los secretos son los del .env de n8n; nunca se suben a GitHub):
 *   php integracion/scripts/enviar-evento.php integracion/payloads/escenario-1-pedido-creado.json \
 *       https://TU-N8N.onrender.com/webhook/kitsune/tienda SECRETO_TIENDA --nuevo
 *   php integracion/scripts/enviar-evento.php integracion/payloads/error-pedido-sin-lineas.json \
 *       https://TU-N8N.onrender.com/webhook/kitsune/tienda SECRETO_TIENDA --nuevo
 *
 * --nuevo cambia el identificador del evento y la fecha (y, en un pedido, la
 * referencia) para que n8n no lo trate como un duplicado. Sin --nuevo se
 * puede comprobar la idempotencia: el segundo envío responde «duplicado».
 *
 * También se pueden dar la dirección y el secreto en las variables de entorno
 * KITSUNE_URL y KITSUNE_SECRETO.
 */

$args   = array_values(array_filter(array_slice($argv, 1), static fn (string $a): bool => $a !== '--nuevo'));
$nuevo  = in_array('--nuevo', $argv, true);
$file   = $args[0] ?? '';
$url    = $args[1] ?? (getenv('KITSUNE_URL') ?: '');
$secret = $args[2] ?? (getenv('KITSUNE_SECRETO') ?: '');

if ($file === '' || !is_file($file) || $url === '' || $secret === '') {
    fwrite(STDERR, "Uso: php enviar-evento.php FICHERO URL_DEL_WEBHOOK SECRETO [--nuevo]\n");
    exit(2);
}

$data = json_decode((string) file_get_contents($file), true);

if (!is_array($data)) {
    fwrite(STDERR, "El fichero no contiene un objeto JSON válido.\n");
    exit(2);
}

if ($nuevo) {
    if (isset($data['event_id'])) {
        // Evento de la tienda: identificador y fecha nuevos.
        $bytes            = random_bytes(16);
        $bytes[6]         = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8]         = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $data['event_id'] = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
        $data['occurred_at'] = date(DATE_ATOM);

        if (($data['event_name'] ?? '') === 'order.created' && !empty($data['data']['lineas'])) {
            $data['data']['referencia'] = sprintf('KN-%s-9%05d', date('Y'), random_int(0, 99999));
        }
    } elseif (isset($data['sincronizacion_id'])) {
        // Cambio del back-office: identificador nuevo.
        $data['sincronizacion_id'] = random_int(100000, 999999);
        $data['emitido_en']        = date(DATE_ATOM);
    }
}

$body      = (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$timestamp = (string) time();
$headers   = [
    'Content-Type: application/json',
    'X-Kitsune-Timestamp: ' . $timestamp,
    'X-Kitsune-Firma: sha256=' . hash_hmac('sha256', $timestamp . '.' . $body, $secret),
];

if (isset($data['event_id'])) {
    $headers[] = 'X-Kitsune-Evento: ' . $data['event_id'];
}

$context  = stream_context_create(['http' => [
    'method'        => 'POST',
    'header'        => implode("\r\n", $headers),
    'content'       => $body,
    'timeout'       => 90,
    'ignore_errors' => true,
]]);
$response = @file_get_contents($url, false, $context);
$status   = 0;

foreach ($http_response_header ?? [] as $line) {
    if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m) === 1) {
        $status = (int) $m[1];
    }
}

echo 'Enviado: ' . ($data['event_name'] ?? $data['tipo'] ?? '?') . ' ' . ($data['event_id'] ?? $data['sincronizacion_id'] ?? '') . PHP_EOL;
echo 'Respuesta de n8n: HTTP ' . ($status ?: 'sin respuesta') . PHP_EOL;
echo ($response === false ? '(n8n no ha respondido; si está en Render gratuito puede tardar un minuto en despertar)' : $response) . PHP_EOL;
exit($status >= 200 && $status < 300 ? 0 : 1);
