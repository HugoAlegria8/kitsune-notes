<?php

declare(strict_types=1);

/**
 * Reintenta las sincronizaciones pendientes con n8n. Pensado para una tarea
 * programada (cron) del hosting, por ejemplo cada 5 minutos:
 *
 *   php /ruta/al/backoffice/bin/reintentar-sincronizaciones.php
 *
 * No es imprescindible: el back-office también reintenta al cargar sus
 * páginas y desde el botón «Reintentar pendientes ahora».
 */

require dirname(__DIR__) . '/src/bootstrap.php';

$app = new KitsuneGestion\Core\App(dirname(__DIR__));
$n   = $app->sincronizador()->reintentarPendientes(20);

echo date(DATE_ATOM) . ' · reintentadas ' . $n . " sincronizaciones pendientes\n";
