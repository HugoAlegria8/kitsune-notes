<?php

declare(strict_types=1);

namespace KitsuneGestion\Web;

use KitsuneGestion\Core\Request;
use KitsuneGestion\Core\Response;
use KitsuneGestion\Service\Incidencias;

/** Incidencias de postventa: listado, conversación, clasificación y estado. */
final class IncidenciasController extends WebController
{
    public function lista(Request $request, array $args): Response
    {
        if (($redireccion = $this->exigirSesion()) !== null) {
            return $redireccion;
        }

        $estado = $request->query('estado');
        $estado = in_array($estado, Incidencias::ESTADOS, true) ? $estado : null;

        return $this->vista('incidencias/lista', [
            'titulo'      => 'Incidencias',
            'incidencias' => $this->app->incidencias()->listar($estado),
            'estado'      => $estado,
        ]);
    }

    public function detalle(Request $request, array $args): Response
    {
        if (($redireccion = $this->exigirSesion()) !== null) {
            return $redireccion;
        }

        $id         = (int) ($args['id'] ?? 0);
        $incidencia = $this->app->incidencias()->buscar($id);

        if ($incidencia === null) {
            return $this->vista('error', ['titulo' => 'Incidencia no encontrada', 'mensaje' => 'No existe la incidencia ' . $id . '.'], 404);
        }

        return $this->vista('incidencias/detalle', [
            'titulo'     => 'Incidencia ' . $id,
            'incidencia' => $incidencia,
            'mensajes'   => $this->app->incidencias()->mensajes($id),
            'propuestas' => $this->app->propuestas()->deIncidencia($id),
        ]);
    }

    /** Clasificación manual: corrige (revierte) la que haya puesto el agente. */
    public function clasificar(Request $request, array $args): Response
    {
        if (($redireccion = $this->exigirSesion()) !== null) {
            return $redireccion;
        }

        $id   = (int) ($args['id'] ?? 0);
        $ruta = '/incidencias/' . $id;

        if (!$this->csrfValido($request)) {
            return $this->volver($ruta, 'error', 'La sesión ha caducado.');
        }

        $categoria = (string) $request->input('categoria', '');
        $prioridad = (string) $request->input('prioridad', '');

        if (!array_key_exists($categoria, Incidencias::CATEGORIAS) || !in_array($prioridad, Incidencias::PRIORIDADES, true)) {
            return $this->volver($ruta, 'error', 'Elige una categoría y una prioridad válidas.');
        }

        $this->app->incidencias()->clasificar($id, $categoria, $prioridad, 'corrección manual', $this->usuario());

        return $this->volver($ruta, 'exito', 'Clasificación actualizada.');
    }

    public function cambiarEstado(Request $request, array $args): Response
    {
        if (($redireccion = $this->exigirSesion()) !== null) {
            return $redireccion;
        }

        $id     = (int) ($args['id'] ?? 0);
        $ruta   = '/incidencias/' . $id;
        $estado = (string) $request->input('estado', '');

        if (!$this->csrfValido($request) || !in_array($estado, Incidencias::ESTADOS, true)) {
            return $this->volver($ruta, 'error', 'No se ha podido cambiar el estado.');
        }

        $this->app->incidencias()->cambiarEstado($id, $estado, $this->usuario());

        return $this->volver($ruta, 'exito', 'Estado de la incidencia actualizado.');
    }
}
