<?php

declare(strict_types=1);

namespace KitsuneNotes\Controller;

use KitsuneNotes\Core\Request;
use KitsuneNotes\Core\Response;
use KitsuneNotes\Service\EventRecorder;
use KitsuneNotes\Service\OrderService;
use KitsuneNotes\Support\Money;

/**
 * Endpoints internos de integración.
 *
 * Son el punto de conexión previsto con la Tarea 2: permiten que otro
 * sistema (un ERP, un almacén de datos o un proceso de automatización)
 * consuma los eventos y el catálogo sin acceder a la base de datos.
 *
 * El acceso se protege con un token estático enviado en la cabecera
 * `X-API-Token` o en el parámetro `token`. Es suficiente para un
 * prototipo académico, pero en un entorno real debería sustituirse por
 * OAuth 2.0 o por claves rotativas por consumidor.
 */
final class ApiController extends Controller
{
    /** @param array<string, string> $args */
    public function events(Request $request, array $args = []): Response
    {
        if (!$this->authorized($request)) {
            return Response::json([
                'error'   => 'no_autorizado',
                'mensaje' => 'Falta el token de acceso o no es válido. Envíalo en la cabecera X-API-Token.',
            ], 401);
        }

        $filters = [
            'nombre' => $request->query('nombre', '') ?? '',
            'desde'  => $request->query('desde', '') ?? '',
            'hasta'  => $request->query('hasta', '') ?? '',
            'sesion' => $request->query('sesion', '') ?? '',
        ];

        $limit  = max(1, min((int) ($request->query('limite', '500') ?? 500), 5000));
        $offset = max(0, (int) ($request->query('desplazamiento', '0') ?? 0));
        $rows   = $this->app->eventsRepository()->search($filters, $limit, $offset);

        if (($request->query('formato', 'json') ?? 'json') === 'csv') {
            return Response::csv($this->toCsv($rows), 'kitsune-eventos-' . date('Ymd-His') . '.csv');
        }

        return Response::json([
            'meta' => [
                'generado_en'     => date(DATE_ATOM),
                'version_esquema' => $this->app->config('events.schema_version'),
                'origen'          => $this->app->config('events.source'),
                'total_devuelto'  => count($rows),
                'total_almacen'   => $this->app->eventsRepository()->count(),
                'filtros'         => array_filter($filters),
                'catalogo_eventos'=> EventRecorder::CATALOG,
            ],
            'eventos' => array_map(
                static function (array $row): array {
                    $payload = json_decode((string) $row['payload_json'], true);

                    return [
                        'event_id'       => $row['event_id'],
                        'event_name'     => $row['event_name'],
                        'schema_version' => $row['schema_version'],
                        'source'         => $row['source'],
                        'occurred_at'    => $row['occurred_at'],
                        'session_id'     => $row['session_id'],
                        'actor_type'     => $row['actor_type'],
                        'customer_id'    => $row['customer_id'] !== null ? (int) $row['customer_id'] : null,
                        'product_id'     => $row['product_id'] !== null ? (int) $row['product_id'] : null,
                        'product_sku'    => $row['product_sku'],
                        'order_id'       => $row['order_id'] !== null ? (int) $row['order_id'] : null,
                        'order_reference'=> $row['order_reference'],
                        'data'           => is_array($payload) ? $payload : [],
                    ];
                },
                $rows
            ),
        ]);
    }

    /** Catálogo publicado como API (datos maestros para otros sistemas). */
    public function products(Request $request, array $args = []): Response
    {
        if (!$this->authorized($request)) {
            return Response::json(['error' => 'no_autorizado'], 401);
        }

        $products = $this->app->products()->search([]);

        return Response::json([
            'meta' => [
                'generado_en' => date(DATE_ATOM),
                'total'       => count($products),
                // Los datos maestros se publican siempre en la moneda base y en
                // español; «nombre_en» es la traducción (vacía si no la tiene).
                'moneda'      => $this->app->currency()->base(),
                'nota'        => 'Datos ficticios de un prototipo académico.',
            ],
            'productos' => array_map(
                static function (array $p): array {
                    $specs = json_decode((string) $p['specs_json'], true);

                    return [
                        'sku'           => $p['sku'],
                        'nombre'        => $p['name'],
                        'nombre_en'     => (string) ($p['name_en'] ?? ''),
                        'categoria'     => $p['category_slug'],
                        'coleccion'     => $p['design_line_slug'],
                        'marca'         => $p['brand'],
                        'origen'        => $p['origin'],
                        'precio'        => Money::decimal((int) $p['price_cents']),
                        'precio_cents'  => (int) $p['price_cents'],
                        'tipo_iva'      => (float) $p['tax_rate'],
                        'stock'         => (int) $p['stock'],
                        'peso_gramos'   => (int) $p['weight_grams'],
                        'especificaciones' => is_array($specs) ? $specs : [],
                    ];
                },
                $products
            ),
        ]);
    }

    /** Comprobación de estado del servicio. */
    public function health(Request $request, array $args = []): Response
    {
        try {
            $products = $this->app->products()->countActive();
            $status   = 'ok';
        } catch (\Throwable $e) {
            $products = 0;
            $status   = 'error';
        }

        return Response::json([
            'estado'           => $status,
            'entorno'          => $this->app->config('app.env'),
            'motor_bd'         => $this->app->database()->driver(),
            'productos_activos'=> $products,
            'pedidos'          => $status === 'ok' ? $this->app->orders()->count() : 0,
            'eventos'          => $status === 'ok' ? $this->app->eventsRepository()->count() : 0,
            'hora_servidor'    => date(DATE_ATOM),
            'aviso'            => $this->app->config('app.academic_notice'),
        ], $status === 'ok' ? 200 : 503);
    }

    /**
     * Tarea 2 · escenario 2. El personal cambia el estado de un pedido en el
     * back-office y n8n lo aplica aquí. Se respetan las mismas transiciones
     * que en el panel de la tienda; repetir la misma petición no cambia nada
     * (idempotente) y una transición no permitida devuelve 409 para que el
     * back-office la marque «en conflicto» en vez de reintentarla.
     *
     * @param array<string, string> $args
     */
    public function changeOrderStatus(Request $request, array $args = []): Response
    {
        if (!$this->authorized($request)) {
            return Response::json([
                'error'   => 'no_autorizado',
                'mensaje' => 'Falta el token de acceso o no es válido. Envíalo en la cabecera X-API-Token.',
            ], 401);
        }

        $data = json_decode((string) file_get_contents('php://input'), true);

        if (!is_array($data)) {
            return Response::json(['error' => 'json_invalido', 'mensaje' => 'El cuerpo debe ser un objeto JSON.'], 400);
        }

        $reference = strtoupper(trim($args['reference'] ?? ''));
        $newStatus = (string) ($data['estado'] ?? '');
        $statuses  = array_keys(OrderService::allowedTransitions());
        $errors    = [];

        if (!in_array($newStatus, $statuses, true)) {
            $errors['estado'] = 'Estado no válido. Valores posibles: ' . implode(', ', $statuses) . '.';
        }

        foreach (['nota' => 300, 'transportista' => 60, 'seguimiento' => 60, 'operador' => 120] as $field => $max) {
            if (isset($data[$field]) && (!is_string($data[$field]) || mb_strlen($data[$field]) > $max)) {
                $errors[$field] = 'Debe ser un texto de ' . $max . ' caracteres como máximo.';
            }
        }

        if ($newStatus === 'enviado' && trim((string) ($data['seguimiento'] ?? '')) === '') {
            $errors['seguimiento'] = 'Es obligatorio para marcar el pedido como enviado.';
        }

        if ($errors !== []) {
            return Response::json(['error' => 'validacion', 'mensaje' => 'Los datos no son válidos.', 'detalles' => $errors], 422);
        }

        $order = $this->app->orders()->findByReference($reference);

        if ($order === null) {
            return Response::json(['error' => 'pedido_no_encontrado', 'mensaje' => 'No existe el pedido ' . $reference . '.'], 404);
        }

        $current = (string) $order['status'];

        if ($current === $newStatus) {
            return Response::json(['resultado' => 'sin_cambios', 'referencia' => $reference, 'estado' => $current]);
        }

        $note = implode(' · ', array_filter([
            'Desde Kitsune Gestión' . (!empty($data['operador']) ? ' (' . $data['operador'] . ')' : ''),
            !empty($data['seguimiento']) ? 'Envío: ' . trim(($data['transportista'] ?? '') . ' ' . $data['seguimiento']) : '',
            trim((string) ($data['nota'] ?? '')),
        ]));

        if (!$this->app->orderService()->changeStatus($order, $newStatus, 'integracion', $note)) {
            return Response::json([
                'error'         => 'transicion_no_permitida',
                'mensaje'       => sprintf('La tienda no permite pasar de «%s» a «%s».', $current, $newStatus),
                'estado_actual' => $current,
                'permitidas'    => OrderService::allowedTransitions()[$current] ?? [],
            ], 409);
        }

        // «origen: integracion» permite a n8n reconocer el eco de su propio cambio y no reenviarlo.
        $this->app->events()->record(
            EventRecorder::ORDER_STATUS_CHANGED,
            [
                'referencia'      => $reference,
                'estado_anterior' => $current,
                'estado_nuevo'    => $newStatus,
                'origen'          => 'integracion',
                'nota'            => $note,
            ],
            ['order_id' => (int) $order['id'], 'customer_id' => (int) $order['customer_id'], 'actor_type' => 'sistema']
        );

        $shippingMail = null;

        if ($newStatus === 'enviado') {
            try {
                $fresh        = $this->app->orders()->findById((int) $order['id']);
                $shippingMail = $fresh !== null ? $this->app->notifier()->orderShipped($fresh) : null;
            } catch (\Throwable $e) {
                error_log('[kitsune-notes] Aviso de envío desde la integración: ' . $e->getMessage());
            }
        }

        return Response::json([
            'resultado'       => 'actualizado',
            'referencia'      => $reference,
            'estado_anterior' => $current,
            'estado'          => $newStatus,
            'aviso_correo'    => $shippingMail !== null ? 'generado' : null,
        ]);
    }

	private function authorized(Request $request): bool
	{
        $expected = (string) $this->app->config('security.api_token');
        $provided = $request->header('X-API-Token');

        return $expected !== ''
            && $provided !== ''
            && hash_equals($expected, $provided);
	}

    /** @param list<array<string, mixed>> $rows */
    private function toCsv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            return '';
        }

        fputcsv($handle, [
            'event_id', 'event_name', 'occurred_at', 'session_id', 'actor_type',
            'customer_id', 'product_sku', 'order_reference', 'payload_json',
        ], ',', '"', '');

        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['event_id'],
                $row['event_name'],
                $row['occurred_at'],
                $row['session_id'],
                $row['actor_type'],
                $row['customer_id'],
                $row['product_sku'],
                $row['order_reference'],
                $row['payload_json'],
            ], ',', '"', '');
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }
}
