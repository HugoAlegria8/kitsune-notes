<?php

declare(strict_types=1);

namespace KitsuneGestion\Api;

use KitsuneGestion\Core\Request;
use KitsuneGestion\Core\Response;
use KitsuneGestion\Core\View;
use KitsuneGestion\Service\Pedidos;
use KitsuneGestion\Support\Validador;

/** Pedidos, pagos y clientes (escenarios 1 y 2, y consultas del agente). */
final class PedidosApi extends ApiController
{
    /** PUT /api/v1/pedidos/{referencia} · alta idempotente de un pedido de la tienda. */
    public function guardar(Request $request, array $args): Response
    {
        if (($error = $this->autorizar($request, 'pedidos:escribir')) !== null) {
            return $error;
        }

        $datos = $this->cuerpo($request);

        if ($datos instanceof Response) {
            return $datos;
        }

        $referencia = strtoupper($args['referencia'] ?? '');
        $v = (new Validador($datos))
            ->patron('referencia', self::REFERENCIA, 'Formato KN-AAAA-NNNNNN.')
            ->en('estado', Pedidos::ESTADOS)
            ->fecha('creado_en')
            ->en('moneda', ['EUR', 'GBP'])
            ->entero('total_cents', 0, 10_000_000)
            ->entero('total_eur_cents', 0, 10_000_000)
            ->entero('unidades', 1, 500)
            ->lista('lineas', 1, 50)
            ->texto('cliente.nombre', 120)
            ->email('cliente.email')
            ->entero('cliente.tienda_id', 1, null, false)
            ->en('metodo_envio', ['estandar', 'express'], false)
            ->texto('provincia_envio', 80, false)
            ->en('idioma', ['es', 'en'], false)
            ->texto('cupon', 40, false)
            ->patron('evento_origen', self::UUID, 'Debe ser el UUID del evento.', false);

        if (($datos['referencia'] ?? null) !== $referencia) {
            $v->anadir(['referencia' => 'No coincide con la referencia de la dirección.']);
        }

        $unidades = 0;

        foreach ((array) ($datos['lineas'] ?? []) as $i => $linea) {
            $l = (new Validador(is_array($linea) ? $linea : []))
                ->patron('sku', '/^[A-Z0-9-]{3,20}$/', 'SKU no válido.')
                ->texto('nombre', 190)
                ->entero('cantidad', 1, 10)
                ->entero('precio_cents', 0, 1_000_000)
                ->entero('precio_eur_cents', 0, 1_000_000, false);

            foreach ($l->errores() as $campo => $mensaje) {
                $v->anadir(['lineas.' . $i . '.' . $campo => $mensaje]);
            }

            $unidades += is_array($linea) && is_int($linea['cantidad'] ?? null) ? $linea['cantidad'] : 0;
        }

        if (!$v->falla() && $unidades !== $datos['unidades']) {
            $v->anadir(['unidades' => sprintf('No coincide con la suma de las líneas (%d).', $unidades)]);
        }

        if ($v->falla()) {
            return $this->invalido($request, $v->errores(), $referencia);
        }

        $resultado = $this->app->pedidos()->registrarDesdeTienda($datos);

        return $this->ok(
            $request,
            [...$resultado, 'referencia' => $referencia, 'enlace' => $this->app->absoluteUrl('/pedidos/' . $referencia)],
            $resultado['resultado'] === 'creado' ? 201 : 200,
            $referencia,
            $resultado['resultado'] === 'creado' ? 'ok' : 'duplicado'
        );
    }

    /** GET /api/v1/pedidos/{referencia} · ficha compacta (la usa el agente). Sin correo ni datos de contacto. */
    public function ver(Request $request, array $args): Response
    {
        if (($error = $this->autorizar($request, 'pedidos:leer')) !== null) {
            return $error;
        }

        $referencia = strtoupper($args['referencia'] ?? '');
        $pedido     = $this->app->pedidos()->buscar($referencia);

        if ($pedido === null) {
            return $this->error($request, 404, 'pedido_no_encontrado', 'No hay ningún pedido ' . $referencia . ' en el back-office.', 'no_encontrado', $referencia);
        }

        $id   = (int) $pedido['id'];
        $dias = (int) floor((time() - strtotime((string) $pedido['creado_en_tienda'])) / 86400);

        return $this->ok($request, [
            'pedido' => [
                'referencia'          => $pedido['referencia'],
                'estado'              => $pedido['estado'],
                'estado_texto'        => View::ETIQUETAS[$pedido['estado']] ?? $pedido['estado'],
                'estado_pago'         => $pedido['estado_pago'],
                'total'               => number_format($pedido['total_cents'] / 100, 2, ',', '.') . ' ' . $pedido['moneda'],
                'creado_en'           => $pedido['creado_en_tienda'],
                'dias_desde_la_compra'=> $dias,
                'metodo_envio'        => $pedido['metodo_envio'],
                'transportista'       => $pedido['transportista'],
                'seguimiento'         => $pedido['seguimiento'],
                'cliente_id'          => $pedido['cliente_id'] !== null ? (int) $pedido['cliente_id'] : null,
                'cliente_nombre'      => $pedido['cliente_nombre'],
                'telegram_vinculado'  => !empty($pedido['telegram_chat_id']),
                'lineas'              => array_map(
                    static fn (array $l): array => ['sku' => $l['sku'], 'nombre' => $l['nombre'], 'cantidad' => (int) $l['cantidad']],
                    $this->app->pedidos()->lineas($id)
                ),
                'historial'           => array_map(
                    static fn (array $h): array => ['estado' => $h['estado_nuevo'], 'fecha' => $h['creado_en'], 'origen' => $h['origen']],
                    array_slice($this->app->pedidos()->historial($id), 0, 4)
                ),
            ],
        ], 200, $referencia);
    }

    /** POST /api/v1/pedidos/{referencia}/pagos · resultado del cobro simulado. */
    public function pago(Request $request, array $args): Response
    {
        if (($error = $this->autorizar($request, 'pedidos:escribir')) !== null) {
            return $error;
        }

        $datos = $this->cuerpo($request);

        if ($datos instanceof Response) {
            return $datos;
        }

        $referencia = strtoupper($args['referencia'] ?? '');
        $v = (new Validador($datos))
            ->en('resultado', ['autorizado', 'rechazado'])
            ->entero('importe_cents', 0, 10_000_000)
            ->en('moneda', ['EUR', 'GBP'])
            ->patron('evento_origen', self::UUID, 'Debe ser el UUID del evento.')
            ->fecha('ocurrido_en')
            ->texto('referencia_pago', 60, false)
            ->texto('motivo_rechazo', 200, false);

        if ($v->falla()) {
            return $this->invalido($request, $v->errores(), $referencia);
        }

        $pedido = $this->app->pedidos()->buscar($referencia);

        if ($pedido === null) {
            // Puede que el pedido aún no haya llegado (eventos fuera de orden): n8n lo reintentará.
            return $this->error($request, 404, 'pedido_no_encontrado', 'El pago es de un pedido que el back-office todavía no conoce.', 'orden_eventos', $referencia);
        }

        $resultado = $this->app->pedidos()->registrarPago($pedido, $datos);

        return $this->ok($request, [...$resultado, 'referencia' => $referencia], 200, $referencia, $resultado['resultado'] === 'duplicado' ? 'duplicado' : 'ok');
    }

    /** POST /api/v1/pedidos/{referencia}/estado · cambio hecho en la tienda (p. ej., incidencia del cliente). */
    public function estadoTienda(Request $request, array $args): Response
    {
        if (($error = $this->autorizar($request, 'pedidos:escribir')) !== null) {
            return $error;
        }

        $datos = $this->cuerpo($request);

        if ($datos instanceof Response) {
            return $datos;
        }

        $referencia = strtoupper($args['referencia'] ?? '');
        $v = (new Validador($datos))
            ->en('estado_nuevo', Pedidos::ESTADOS)
            ->texto('origen', 40)
            ->texto('nota', 500, false)
            ->patron('evento_origen', self::UUID, 'Debe ser el UUID del evento.');

        if ($v->falla()) {
            return $this->invalido($request, $v->errores(), $referencia);
        }

        $pedido = $this->app->pedidos()->buscar($referencia);

        if ($pedido === null) {
            return $this->error($request, 404, 'pedido_no_encontrado', 'El back-office todavía no conoce ese pedido.', 'orden_eventos', $referencia);
        }

        $resultado = $this->app->pedidos()->reflejarEstadoTienda($pedido, (string) $datos['estado_nuevo'], (string) $datos['origen'], (string) ($datos['nota'] ?? ''));

        return $this->ok($request, [...$resultado, 'referencia' => $referencia], 200, $referencia, $resultado['resultado'] === 'sin_cambios' ? 'duplicado' : 'ok');
    }

    /** GET /api/v1/clientes/{id}/historial · pedidos e incidencias anteriores (lo usa el agente). */
    public function historialCliente(Request $request, array $args): Response
    {
        if (($error = $this->autorizar($request, 'clientes:leer')) !== null) {
            return $error;
        }

        $id      = (int) ($args['id'] ?? 0);
        $cliente = $this->app->pedidos()->cliente($id);

        if ($cliente === null) {
            return $this->error($request, 404, 'cliente_no_encontrado', 'No existe el cliente ' . $id . '.', 'no_encontrado', 'cliente ' . $id);
        }

        $pedidos     = $this->app->pedidos()->deCliente($id);
        $incidencias = $this->app->incidencias()->deCliente($id);

        return $this->ok($request, [
            'cliente' => [
                'id'                 => $id,
                'nombre'             => $cliente['nombre'],
                'cliente_desde'      => $cliente['creado_en'],
                'telegram_vinculado' => !empty($cliente['telegram_chat_id']),
                'pedidos'            => count($pedidos),
                'importe_total_eur'  => number_format(array_sum(array_column($pedidos, 'total_eur_cents')) / 100, 2, ',', '.'),
                'pedidos_recientes'  => array_map(
                    static fn (array $p): array => ['referencia' => $p['referencia'], 'estado' => $p['estado'], 'fecha' => substr((string) $p['creado_en_tienda'], 0, 10)],
                    array_slice($pedidos, 0, 5)
                ),
                'incidencias_previas' => $incidencias,
            ],
        ], 200, 'cliente ' . $id);
    }
}
