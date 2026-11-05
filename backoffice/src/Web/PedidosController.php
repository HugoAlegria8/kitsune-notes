<?php

declare(strict_types=1);

namespace KitsuneGestion\Web;

use KitsuneGestion\Core\Request;
use KitsuneGestion\Core\Response;
use KitsuneGestion\Service\Pedidos;

/** Pedidos: listado, ficha y cambio de estado (escenario 2). */
final class PedidosController extends WebController
{
    public function lista(Request $request, array $args): Response
    {
        if (($redireccion = $this->exigirSesion()) !== null) {
            return $redireccion;
        }

        $estado = $request->query('estado');
        $estado = in_array($estado, Pedidos::ESTADOS, true) ? $estado : null;

        return $this->vista('pedidos/lista', [
            'titulo'  => 'Pedidos',
            'pedidos' => $this->app->pedidos()->listar($estado),
            'estado'  => $estado,
        ]);
    }

    public function detalle(Request $request, array $args): Response
    {
        if (($redireccion = $this->exigirSesion()) !== null) {
            return $redireccion;
        }

        $referencia = strtoupper($args['referencia'] ?? '');
        $pedido     = $this->app->pedidos()->buscar($referencia);

        if ($pedido === null) {
            return $this->vista('error', ['titulo' => 'Pedido no encontrado', 'mensaje' => 'El pedido ' . $referencia . ' no ha llegado al back-office.'], 404);
        }

        $id = (int) $pedido['id'];

        return $this->vista('pedidos/detalle', [
            'titulo'          => 'Pedido ' . $referencia,
            'pedido'          => $pedido,
            'lineas'          => $this->app->pedidos()->lineas($id),
            'historial'       => $this->app->pedidos()->historial($id),
            'pagos'           => $this->app->pedidos()->pagos($id),
            'sincronizaciones'=> $this->app->sincronizador()->deEntidad($referencia),
            'incidencias'     => $this->app->db()->all('SELECT id, asunto, estado, prioridad FROM incidencias WHERE pedido_id = ? ORDER BY id DESC', [$id]),
            'transiciones'    => Pedidos::TRANSICIONES[$pedido['estado']] ?? [],
        ]);
    }

    /**
     * El personal cambia el estado. Se guarda aquí y se comunica a la tienda
     * a través de n8n; si no se puede, queda pendiente de sincronización.
     */
    public function cambiarEstado(Request $request, array $args): Response
    {
        if (($redireccion = $this->exigirSesion()) !== null) {
            return $redireccion;
        }

        $referencia = strtoupper($args['referencia'] ?? '');
        $ruta       = '/pedidos/' . $referencia;

        if (!$this->csrfValido($request)) {
            return $this->volver($ruta, 'error', 'La sesión ha caducado. Vuelve a intentarlo.');
        }

        $pedido = $this->app->pedidos()->buscar($referencia);

        if ($pedido === null) {
            return $this->volver('/pedidos', 'error', 'Ese pedido no existe.');
        }

        $nuevo         = (string) $request->input('estado', '');
        $nota          = mb_substr((string) $request->input('nota', ''), 0, 300);
        $transportista = (string) $request->input('transportista', '');
        $seguimiento   = (string) $request->input('seguimiento', '');
        $anterior      = (string) $pedido['estado'];
        $error         = $this->app->pedidos()->cambiarEstado($pedido, $nuevo, $this->usuario(), $nota, $transportista, $seguimiento);

        if ($error !== null) {
            return $this->volver($ruta, 'error', $error);
        }

        $fila = $this->app->sincronizador()->emitir('pedido.estado_cambiado', $referencia, [
            'pedido_referencia' => $referencia,
            'estado_anterior'   => $anterior,
            'estado_nuevo'      => $nuevo,
            'nota'              => $nota,
            'transportista'     => $nuevo === 'enviado' ? trim($transportista) : null,
            'seguimiento'       => $nuevo === 'enviado' ? trim($seguimiento) : null,
            'operador'          => $this->usuario(),
            'idioma'            => $pedido['idioma'],
            'telegram_chat_id'  => $pedido['telegram_chat_id'] ?: null,
        ]);

        [$tipo, $texto] = $this->resumenSincronizacion($fila, 'Cambio a «' . $nuevo . '» guardado; sincronización con la tienda');

        return $this->volver($ruta, $tipo, $texto);
    }
}
