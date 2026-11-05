<?php

declare(strict_types=1);

namespace KitsuneGestion\Api;

use KitsuneGestion\Core\Request;
use KitsuneGestion\Core\Response;
use KitsuneGestion\Service\Incidencias;
use KitsuneGestion\Support\Validador;

/** Incidencias de postventa (escenario 3) y la herramienta de clasificación del agente. */
final class IncidenciasApi extends ApiController
{
    /** POST /api/v1/incidencias · alta idempotente (por la referencia del ticket). */
    public function crear(Request $request, array $args): Response
    {
        if (($error = $this->autorizar($request, 'incidencias:escribir')) !== null) {
            return $error;
        }

        $datos = $this->cuerpo($request);

        if ($datos instanceof Response) {
            return $datos;
        }

        $v = (new Validador($datos))
            ->en('tipo', array_keys(Incidencias::TIPOS))
            ->texto('asunto', 160)
            ->texto('mensaje', 4000)
            ->en('canal', ['formulario_web', 'telegram'])
            ->en('idioma', ['es', 'en'], false)
            ->patron('pedido_referencia', self::REFERENCIA, 'Formato KN-AAAA-NNNNNN.', false)
            ->texto('referencia_ticket', 40, false)
            ->texto('cliente.nombre', 120, false)
            ->email('cliente.email', false)
            ->entero('cliente.tienda_id', 1, null, false)
            ->fecha('ocurrido_en', false)
            ->patron('evento_origen', self::UUID, 'Debe ser el UUID del evento.', false);

        if (empty($datos['cliente']['email']) && empty($datos['cliente']['tienda_id']) && empty($datos['pedido_referencia'])) {
            $v->anadir(['cliente' => 'Hace falta el correo del cliente o el pedido para poder asociar la incidencia.']);
        }

        if ($v->falla()) {
            return $this->invalido($request, $v->errores(), $datos['referencia_ticket'] ?? null);
        }

        $resultado = $this->app->incidencias()->registrar($datos);

        return $this->ok(
            $request,
            [...$resultado, 'enlace' => $this->app->absoluteUrl('/incidencias/' . $resultado['id'])],
            $resultado['resultado'] === 'creada' ? 201 : 200,
            'incidencia ' . $resultado['id'],
            $resultado['resultado'] === 'creada' ? 'ok' : 'duplicado'
        );
    }

    /** GET /api/v1/incidencias/{id} */
    public function ver(Request $request, array $args): Response
    {
        if (($error = $this->autorizar($request, 'incidencias:leer')) !== null) {
            return $error;
        }

        $id         = (int) ($args['id'] ?? 0);
        $incidencia = $this->app->incidencias()->buscar($id);

        if ($incidencia === null) {
            return $this->error($request, 404, 'incidencia_no_encontrada', 'No existe la incidencia ' . $id . '.', 'no_encontrado', 'incidencia ' . $id);
        }

        return $this->ok($request, [
            'incidencia' => [
                'id'                => $id,
                'tipo'              => $incidencia['tipo'],
                'asunto'            => $incidencia['asunto'],
                'estado'            => $incidencia['estado'],
                'categoria'         => $incidencia['categoria'],
                'prioridad'         => $incidencia['prioridad'],
                'pedido_referencia' => $incidencia['pedido_referencia'],
                'cliente_id'        => $incidencia['cliente_id'] !== null ? (int) $incidencia['cliente_id'] : null,
                'idioma'            => $incidencia['idioma'],
                'mensajes'          => array_map(
                    static fn (array $m): array => ['autor' => $m['autor'], 'canal' => $m['canal'], 'texto' => $m['texto'], 'fecha' => $m['creado_en']],
                    $this->app->incidencias()->mensajes($id)
                ),
            ],
        ], 200, 'incidencia ' . $id);
    }

    /**
     * POST /api/v1/incidencias/{id}/clasificacion · herramienta de acción del
     * agente. Es reversible (el personal puede cambiarla) y queda anotada en
     * la conversación de la incidencia.
     */
    public function clasificar(Request $request, array $args): Response
    {
        if (($error = $this->autorizar($request, 'incidencias:clasificar')) !== null) {
            return $error;
        }

        $datos = $this->cuerpo($request);

        if ($datos instanceof Response) {
            return $datos;
        }

        $id = (int) ($args['id'] ?? 0);
        $v  = (new Validador($datos))
            ->en('categoria', array_keys(Incidencias::CATEGORIAS))
            ->en('prioridad', Incidencias::PRIORIDADES)
            ->texto('motivo', 300, false);

        if ($v->falla()) {
            return $this->invalido($request, $v->errores(), 'incidencia ' . $id);
        }

        if ($this->app->incidencias()->buscar($id) === null) {
            return $this->error($request, 404, 'incidencia_no_encontrada', 'No existe la incidencia ' . $id . '.', 'no_encontrado', 'incidencia ' . $id);
        }

        $this->app->incidencias()->clasificar(
            $id,
            (string) $datos['categoria'],
            (string) $datos['prioridad'],
            (string) ($datos['motivo'] ?? ''),
            $this->consumidor === 'agente' ? 'agente-ia' : 'integracion'
        );

        return $this->ok($request, [
            'resultado' => 'clasificada',
            'id'        => $id,
            'categoria' => $datos['categoria'],
            'prioridad' => $datos['prioridad'],
        ], 200, 'incidencia ' . $id);
    }
}
