<?php

declare(strict_types=1);

namespace KitsuneGestion\Web;

use KitsuneGestion\Core\Request;
use KitsuneGestion\Core\Response;

/** Trazabilidad: registro de integración, bandeja de salida y eventos recibidos. */
final class IntegracionController extends WebController
{
    public function index(Request $request, array $args): Response
    {
        if (($redireccion = $this->exigirSesion()) !== null) {
            return $redireccion;
        }

        $resultado = $request->query('resultado');
        $resultado = in_array($resultado, ['ok', 'error', 'duplicado', 'ignorado'], true) ? $resultado : null;
        $origen    = $request->query('origen');
        $origen    = in_array($origen, ['api', 'n8n', 'backoffice'], true) ? $origen : null;

        return $this->vista('integracion', [
            'titulo'           => 'Integración y trazabilidad',
            'registro'         => $this->app->registro()->listar($resultado, $origen),
            'sincronizaciones' => $this->app->sincronizador()->listar(null, 50),
            'eventos'          => $this->app->db()->all('SELECT * FROM eventos_recibidos ORDER BY recibido_en DESC LIMIT 40'),
            'resultado'        => $resultado,
            'origen'           => $origen,
            'configurado'      => $this->app->sincronizador()->configurado(),
        ]);
    }

    public function reintentar(Request $request, array $args): Response
    {
        if (($redireccion = $this->exigirSesion()) !== null) {
            return $redireccion;
        }

        if (!$this->csrfValido($request)) {
            return $this->volver('/integracion', 'error', 'La sesión ha caducado.');
        }

        $id = (int) $request->input('id', '0');

        if ($id > 0) {
            $fila = $this->app->sincronizador()->reintentarAhora($id);

            if ($fila === null) {
                return $this->volver('/integracion#salida', 'aviso', 'Esa sincronización ya estaba confirmada.');
            }

            [$tipo, $texto] = $this->resumenSincronizacion($fila, 'Reintento de la sincronización n.º ' . $id);

            return $this->volver('/integracion#salida', $tipo, $texto);
        }

        $n = $this->app->sincronizador()->reintentarPendientes(20, true);

        return $this->volver('/integracion#salida', 'exito', 'Reintentadas ' . $n . ' sincronizaciones pendientes.');
    }
}
