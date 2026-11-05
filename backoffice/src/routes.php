<?php

declare(strict_types=1);

/**
 * Tabla de rutas de Kitsune Gestión.
 *
 * @var KitsuneGestion\Core\App $app
 */

use KitsuneGestion\Api\IncidenciasApi;
use KitsuneGestion\Api\IntegracionApi;
use KitsuneGestion\Api\PedidosApi;
use KitsuneGestion\Core\Router;
use KitsuneGestion\Web\AuthController;
use KitsuneGestion\Web\IncidenciasController;
use KitsuneGestion\Web\IntegracionController;
use KitsuneGestion\Web\PanelController;
use KitsuneGestion\Web\PedidosController;
use KitsuneGestion\Web\PropuestasController;

$router = new Router($app);

// ---- Pantallas del equipo ---------------------------------------------
$router->get('/login', [AuthController::class, 'formulario']);
$router->post('/login', [AuthController::class, 'entrar']);
$router->post('/salir', [AuthController::class, 'salir']);

$router->get('/', [PanelController::class, 'index']);

$router->get('/pedidos', [PedidosController::class, 'lista']);
$router->get('/pedidos/{referencia}', [PedidosController::class, 'detalle']);
$router->post('/pedidos/{referencia}/estado', [PedidosController::class, 'cambiarEstado']);

$router->get('/incidencias', [IncidenciasController::class, 'lista']);
$router->get('/incidencias/{id}', [IncidenciasController::class, 'detalle']);
$router->post('/incidencias/{id}/clasificacion', [IncidenciasController::class, 'clasificar']);
$router->post('/incidencias/{id}/estado', [IncidenciasController::class, 'cambiarEstado']);

$router->get('/propuestas', [PropuestasController::class, 'lista']);
$router->post('/propuestas/{id}/aprobar', [PropuestasController::class, 'aprobar']);
$router->post('/propuestas/{id}/rechazar', [PropuestasController::class, 'rechazar']);

$router->get('/integracion', [IntegracionController::class, 'index']);
$router->post('/integracion/reintentar', [IntegracionController::class, 'reintentar']);

// ---- API REST para n8n (cabecera X-Api-Key) ----------------------------
$router->get('/api/v1/salud', [IntegracionApi::class, 'salud']);

$router->put('/api/v1/pedidos/{referencia}', [PedidosApi::class, 'guardar']);
$router->get('/api/v1/pedidos/{referencia}', [PedidosApi::class, 'ver']);
$router->post('/api/v1/pedidos/{referencia}/pagos', [PedidosApi::class, 'pago']);
$router->post('/api/v1/pedidos/{referencia}/estado', [PedidosApi::class, 'estadoTienda']);
$router->get('/api/v1/clientes/{id}/historial', [PedidosApi::class, 'historialCliente']);

$router->post('/api/v1/incidencias', [IncidenciasApi::class, 'crear']);
$router->get('/api/v1/incidencias/{id}', [IncidenciasApi::class, 'ver']);
$router->post('/api/v1/incidencias/{id}/clasificacion', [IncidenciasApi::class, 'clasificar']);

$router->post('/api/v1/propuestas', [IntegracionApi::class, 'propuesta']);
$router->post('/api/v1/eventos', [IntegracionApi::class, 'evento']);
$router->post('/api/v1/registro', [IntegracionApi::class, 'registro']);
$router->post('/api/v1/telegram/vinculos', [IntegracionApi::class, 'vincular']);
$router->post('/api/v1/telegram/mensajes', [IntegracionApi::class, 'mensajeTelegram']);
$router->get('/api/v1/telegram/{chat_id}/pedidos', [IntegracionApi::class, 'pedidosTelegram']);
$router->get('/api/v1/metricas', [IntegracionApi::class, 'metricas']);

return $router;
