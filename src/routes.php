<?php
/**
 * Tabla de rutas de la aplicación.
 *
 * Mantener las rutas en un único fichero facilita revisar de un vistazo
 * la superficie pública del canal digital y qué controlador atiende cada
 * parte del flujo de compra.
 */

declare(strict_types=1);

use KitsuneNotes\Controller\AdminController;
use KitsuneNotes\Controller\AdminMailController;
use KitsuneNotes\Controller\AdminProductController;
use KitsuneNotes\Controller\ApiController;
use KitsuneNotes\Controller\CartController;
use KitsuneNotes\Controller\CatalogController;
use KitsuneNotes\Controller\CheckoutController;
use KitsuneNotes\Controller\HomeController;
use KitsuneNotes\Controller\OrderController;
use KitsuneNotes\Controller\PageController;
use KitsuneNotes\Controller\SupportController;
use KitsuneNotes\Core\Router;

$router = new Router();

// --- Tienda ----------------------------------------------------------
$router->get('/',                    [HomeController::class, 'index']);
$router->get('/catalogo',            [CatalogController::class, 'index']);
$router->get('/categoria/{slug}',    [CatalogController::class, 'category']);
$router->get('/coleccion/{slug}',    [CatalogController::class, 'designLine']);
$router->get('/producto/{slug}',     [CatalogController::class, 'product']);

// --- Carrito ---------------------------------------------------------
$router->get('/carrito',             [CartController::class, 'index']);
$router->post('/carrito/anadir',     [CartController::class, 'add']);
$router->post('/carrito/actualizar', [CartController::class, 'update']);
$router->post('/carrito/eliminar',   [CartController::class, 'remove']);
$router->post('/carrito/cupon',      [CartController::class, 'coupon']);

// --- Checkout y pago simulado ---------------------------------------
$router->get('/checkout',            [CheckoutController::class, 'index']);
$router->post('/checkout',           [CheckoutController::class, 'submit']);
$router->get('/pago',                [CheckoutController::class, 'payment']);
$router->post('/pago',               [CheckoutController::class, 'processPayment']);

// --- Pedidos ---------------------------------------------------------
$router->get('/pedidos',             [OrderController::class, 'lookupForm']);
$router->post('/pedidos',            [OrderController::class, 'lookup']);
$router->get('/pedido/{reference}',  [OrderController::class, 'show']);
$router->get('/pedido/{reference}/factura', [OrderController::class, 'invoice']);

// --- Postventa -------------------------------------------------------
$router->get('/soporte',             [SupportController::class, 'form']);
$router->post('/soporte',            [SupportController::class, 'submit']);

// --- Páginas informativas -------------------------------------------
$router->get('/colecciones',         [PageController::class, 'collections']);
$router->get('/sobre-kitsune',       [PageController::class, 'about']);
$router->get('/aviso-academico',     [PageController::class, 'academic']);
$router->get('/envios',              [PageController::class, 'shipping']);
$router->post('/cookies/entendido',  [PageController::class, 'acknowledgeCookies']);

// --- Back-office -----------------------------------------------------
$router->get('/admin/login',                    [AdminController::class, 'loginForm']);
$router->post('/admin/login',                   [AdminController::class, 'login']);
$router->post('/admin/logout',                  [AdminController::class, 'logout']);
$router->get('/admin',                          [AdminController::class, 'dashboard']);
$router->get('/admin/pedidos',                  [AdminController::class, 'orders']);
$router->get('/admin/pedidos/{reference}',      [AdminController::class, 'orderDetail']);
$router->post('/admin/pedidos/{reference}/estado', [AdminController::class, 'updateStatus']);
$router->get('/admin/pedidos/{reference}/factura',  [AdminController::class, 'invoice']);
$router->post('/admin/pedidos/{reference}/factura', [AdminController::class, 'issueInvoice']);
$router->get('/admin/correos',                      [AdminMailController::class, 'index']);
$router->get('/admin/correos/{id}',                 [AdminMailController::class, 'show']);
$router->get('/admin/correos/{id}/descargar',       [AdminMailController::class, 'download']);
$router->get('/admin/productos',                     [AdminProductController::class, 'index']);
$router->get('/admin/productos/nuevo',               [AdminProductController::class, 'create']);
$router->post('/admin/productos',                    [AdminProductController::class, 'store']);
$router->get('/admin/productos/{id}/editar',         [AdminProductController::class, 'edit']);
$router->post('/admin/productos/{id}',               [AdminProductController::class, 'update']);
$router->post('/admin/productos/{id}/retirar',       [AdminProductController::class, 'archive']);
$router->post('/admin/productos/{id}/reactivar',     [AdminProductController::class, 'restore']);
$router->post('/admin/productos/{id}/eliminar',      [AdminProductController::class, 'destroy']);
$router->get('/admin/eventos',                  [AdminController::class, 'events']);
$router->get('/admin/incidencias',              [AdminController::class, 'tickets']);
$router->post('/admin/incidencias/{reference}/estado', [AdminController::class, 'updateTicket']);

// --- API interna (integración con la Tarea 2) ------------------------
$router->get('/api/eventos',         [ApiController::class, 'events']);
$router->get('/api/productos',       [ApiController::class, 'products']);
$router->get('/api/salud',           [ApiController::class, 'health']);

return $router;
