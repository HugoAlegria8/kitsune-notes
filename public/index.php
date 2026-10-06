<?php
/**
 * Front controller: único punto de entrada de la aplicación.
 *
 * Toda petición HTTP pasa por aquí, se resuelve con el enrutador y
 * devuelve un objeto Response. Es también el único lugar donde se
 * envían cabeceras al navegador.
 */

declare(strict_types=1);

use KitsuneNotes\Core\App;
use KitsuneNotes\Core\Request;
use KitsuneNotes\Core\Response;
use KitsuneNotes\Core\Router;

/** @var App $app */
$app = require dirname(__DIR__) . '/src/bootstrap.php';

$request = Request::capture();
$app->setRequest($request);

// Cabeceras de seguridad básicas.
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');

try {
    $app->session()->start();

    // Contexto de la petición para la instrumentación: la IP se
    // seudonimiza mediante hash, nunca se almacena en claro.
    $app->events()->withRequestContext($request->ip(), $request->userAgent());

    // Datos compartidos por todas las plantillas.
    $app->view()->share([
        'appName'        => $app->config('app.name'),
        'academicNotice' => $app->config('app.academic_notice'),
        'navCategories'  => $app->categories()->all(),
        'navDesignLines' => $app->designLines()->all(),
        'cartUnits'      => $app->cart()->unitCount(),
        'flashMessages'  => $app->session()->pullFlash(),
        'staffUser'      => $app->auth()->user(),
        'title'          => $app->config('app.name'),
    ]);

    /** @var Router $router */
    $router = require dirname(__DIR__) . '/src/routes.php';

    $router->notFound(static function (Request $request, App $app): Response {
        return Response::html(
            $app->view()->render('page/error', [
                'title'   => 'Página no encontrada',
                'code'    => '404',
                'message' => 'La dirección ' . htmlspecialchars($request->path(), ENT_QUOTES) . ' no existe en la tienda.',
            ]),
            404
        );
    });

    $response = $router->dispatch($request, $app);
} catch (Throwable $e) {
    error_log('[kitsune-notes] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());

    $debug   = (bool) $app->config('app.debug');
    $details = $debug
        ? $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')'
        : 'Se ha producido un error inesperado. Vuelve a intentarlo en unos minutos.';

    try {
        $response = Response::html(
            $app->view()->render('page/error', [
                'title'   => 'Error del servidor',
                'code'    => '500',
                'message' => $details,
            ]),
            500
        );
    } catch (Throwable) {
        $response = Response::html(
            '<!doctype html><html lang="es"><meta charset="utf-8">'
            . '<title>Error</title><h1>Error del servidor</h1><p>'
            . htmlspecialchars($details, ENT_QUOTES) . '</p>',
            500
        );
    }
}

$response->send();
