<?php

declare(strict_types=1);

/**
 * Controlador frontal de Kitsune Gestión. Es el único punto que envía la
 * respuesta; después, sin hacer esperar a nadie, reintenta las
 * sincronizaciones con n8n que hayan quedado pendientes.
 */

require dirname(__DIR__) . '/src/bootstrap.php';

use KitsuneGestion\Core\App;
use KitsuneGestion\Core\Request;
use KitsuneGestion\Core\Response;

$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$basePath = in_array($basePath, ['', '.', '/'], true) ? '' : $basePath;

$secure  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
$app     = new App(dirname(__DIR__), $basePath, $secure);
$request = Request::fromGlobals($basePath);

try {
    $router   = require dirname(__DIR__) . '/src/routes.php';
    $response = $router->dispatch($request);

    if ($response === null) {
        $response = $request->isApi()
            ? Response::json(['ok' => false, 'error' => 'no_encontrado', 'mensaje' => 'Esa ruta de la API no existe.'], 404)
            : Response::html($app->view()->render('error', [
                'titulo'  => 'Página no encontrada',
                'mensaje' => 'La dirección no existe en Kitsune Gestión.',
            ]), 404);
    }
} catch (Throwable $e) {
    error_log('[kitsune-gestion] ' . $e::class . ': ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());

    $detalle  = $app->debug() ? $e->getMessage() : 'Error inesperado; el detalle está en el registro del servidor.';
    $response = $request->isApi()
        ? Response::json(['ok' => false, 'error' => 'error_interno', 'mensaje' => $detalle], 500)
        : Response::html('<h1>Error</h1><p>' . htmlspecialchars($detalle, ENT_QUOTES, 'UTF-8') . '</p>', 500);
}

$response = $response
    ->withHeader('X-Content-Type-Options', 'nosniff')
    ->withHeader('X-Frame-Options', 'DENY')
    ->withHeader('Referrer-Policy', 'same-origin')
    ->withHeader('Content-Security-Policy', "default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; form-action 'self'; frame-ancestors 'none'");

$response->send();

// ---- Después de responder: reintentos pendientes hacia n8n -------------
if (!$request->isApi()) {
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } elseif (function_exists('litespeed_finish_request')) {
        litespeed_finish_request();
    }

    try {
        $app->sincronizador()->reintentarPendientes(2);
    } catch (Throwable $e) {
        error_log('[kitsune-gestion] Reintento de sincronización: ' . $e->getMessage());
    }
}
