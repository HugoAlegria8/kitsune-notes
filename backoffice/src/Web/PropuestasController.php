<?php

declare(strict_types=1);

namespace KitsuneGestion\Web;

use KitsuneGestion\Core\Request;
use KitsuneGestion\Core\Response;
use KitsuneGestion\Service\Propuestas;

/**
 * Revisión humana de las propuestas del agente de IA.
 *
 * Aprobar registra quién y cuándo, permite corregir el texto y solo entonces
 * pide a n8n que lo envíe al cliente. Rechazar exige un motivo.
 */
final class PropuestasController extends WebController
{
    public function lista(Request $request, array $args): Response
    {
        if (($redireccion = $this->exigirSesion()) !== null) {
            return $redireccion;
        }

        $estado = $request->query('estado', 'pendiente_revision');
        $estado = in_array($estado, Propuestas::ESTADOS, true) ? $estado : null;

        return $this->vista('propuestas/lista', [
            'titulo'     => 'Propuestas del agente',
            'propuestas' => $this->app->propuestas()->listar($estado),
            'estado'     => $estado,
            'conteo'     => $this->app->propuestas()->contarPorEstado(),
        ]);
    }

    public function aprobar(Request $request, array $args): Response
    {
        if (($redireccion = $this->exigirSesion()) !== null) {
            return $redireccion;
        }

        $id     = (int) ($args['id'] ?? 0);
        $vuelta = (string) $request->input('volver', '/propuestas');
        $vuelta = str_starts_with($vuelta, '/') && !str_starts_with($vuelta, '//') ? $vuelta : '/propuestas';

        if (!$this->csrfValido($request)) {
            return $this->volver($vuelta, 'error', 'La sesión ha caducado.');
        }

        $error = $this->app->propuestas()->aprobar(
            $id,
            $this->usuario(),
            (string) $request->input('mensaje_final', ''),
            (string) $request->input('comentario', '')
        );

        if ($error !== null) {
            return $this->volver($vuelta, 'error', $error);
        }

        $propuesta = (array) $this->app->propuestas()->buscar($id);

        if (empty($propuesta['mensaje_final'])) {
            // Acción interna (escalar o cerrar): no hay nada que enviar al cliente.
            $this->app->propuestas()->marcarResultado($id, 'ejecutada_simulada', 'Acción interna registrada; no hay mensaje para el cliente.');

            return $this->volver($vuelta, 'exito', 'Propuesta aprobada y registrada.');
        }

        $fila = $this->app->sincronizador()->emitir('propuesta.aprobada', 'propuesta ' . $id, [
            'propuesta_id'      => $id,
            'incidencia_id'     => (int) $propuesta['incidencia_id'],
            'pedido_referencia' => $propuesta['pedido_referencia'],
            'accion'            => $propuesta['accion_propuesta'],
            'mensaje'           => $propuesta['mensaje_final'],
            'idioma'            => $propuesta['idioma'],
            'telegram_chat_id'  => $propuesta['telegram_chat_id'] ?: null,
            'aprobada_por'      => $this->usuario(),
        ]);

        [$tipo, $texto] = $this->resumenSincronizacion($fila, 'Propuesta aprobada; envío al cliente');

        return $this->volver($vuelta, $tipo, $texto);
    }

    public function rechazar(Request $request, array $args): Response
    {
        if (($redireccion = $this->exigirSesion()) !== null) {
            return $redireccion;
        }

        $id     = (int) ($args['id'] ?? 0);
        $vuelta = (string) $request->input('volver', '/propuestas');
        $vuelta = str_starts_with($vuelta, '/') && !str_starts_with($vuelta, '//') ? $vuelta : '/propuestas';

        if (!$this->csrfValido($request)) {
            return $this->volver($vuelta, 'error', 'La sesión ha caducado.');
        }

        $error = $this->app->propuestas()->rechazar($id, $this->usuario(), (string) $request->input('motivo', ''));

        return $error !== null
            ? $this->volver($vuelta, 'error', $error)
            : $this->volver($vuelta, 'exito', 'Propuesta rechazada. No se ha enviado nada al cliente.');
    }
}
