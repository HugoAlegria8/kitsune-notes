<?php

declare(strict_types=1);

/**
 * Tarea 2 · envía a n8n los eventos pendientes de la bandeja de salida.
 *
 * No es imprescindible (la tienda los envía al final de cada petición), pero
 * sirve para una tarea programada del hosting, por ejemplo cada 5 minutos:
 *
 *   php /ruta/a/la/tienda/bin/despachar-webhooks.php
 */

$app = require dirname(__DIR__) . '/src/bootstrap.php';

if (!$app->webhooks()->enabled()) {
    echo "La integración está apagada (faltan KN_WEBHOOK_URL o KN_WEBHOOK_SECRET en el .env).\n";
    exit(0);
}

$sent = $app->webhooks()->dispatchDue(50, 50.0);

echo date(DATE_ATOM) . ' · enviados ' . $sent . " eventos pendientes\n";
