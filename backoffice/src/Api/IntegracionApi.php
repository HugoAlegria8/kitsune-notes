<?php

declare(strict_types=1);

namespace KitsuneGestion\Api;

use KitsuneGestion\Core\Request;
use KitsuneGestion\Core\Response;
use KitsuneGestion\Core\View;
use KitsuneGestion\Service\Propuestas;
use KitsuneGestion\Support\Validador;

/** Eventos, registro, propuestas del agente, Telegram, métricas y salud. */
final class IntegracionApi extends ApiController
{
    /** GET /api/v1/salud · sin clave; también sirve para mantener despierto el servicio. */
    public function salud(Request $request, array $args): Response
    {
        $this->app->db();

        return Response::json([
            'ok'       => true,
            'servicio' => 'kitsune-gestion',
            'version'  => '1.0',
            'hora'     => date(DATE_ATOM),
            'n8n'      => $this->app->sincronizador()->configurado() ? 'configurado' : 'sin_configurar',
        ]);
    }

    /** POST /api/v1/eventos · copia mínima de un evento de la tienda (embudo y deduplicación). */
    public function evento(Request $request, array $args): Response
    {
        if (($error = $this->autorizar($request, 'eventos:escribir')) !== null) {
            return $error;
        }

        $datos = $this->cuerpo($request);

        if ($datos instanceof Response) {
            return $datos;
        }

        $v = (new Validador($datos))
            ->patron('event_id', self::UUID, 'Debe ser un UUID.')
            ->patron('event_name', '/^[a-z_]+\.[a-z_]+$/', 'Nombre de evento no válido.')
            ->fecha('occurred_at')
            ->texto('session_id', 128, false)
            ->patron('pedido_ref', self::REFERENCIA, 'Formato KN-AAAA-NNNNNN.', false)
            ->entero('importe_eur_cents', 0, 10_000_000, false)
            ->texto('resultado', 20, false);

        if ($v->falla()) {
            return $this->invalido($request, $v->errores(), $datos['event_id'] ?? null);
        }

        $insertadas = $this->app->db()->run(
            'INSERT OR IGNORE INTO eventos_recibidos
                (event_id, event_name, occurred_at, session_id, pedido_ref, importe_eur_cents, resultado, recibido_en)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $datos['event_id'], $datos['event_name'], $datos['occurred_at'], $datos['session_id'] ?? null,
                $datos['pedido_ref'] ?? null, $datos['importe_eur_cents'] ?? null, $datos['resultado'] ?? null, date(DATE_ATOM),
            ]
        );

        return $this->ok(
            $request,
            ['resultado' => $insertadas > 0 ? 'registrado' : 'duplicado', 'event_id' => $datos['event_id']],
            200,
            (string) $datos['event_name'],
            $insertadas > 0 ? 'ok' : 'duplicado',
            false
        );
    }

    /** POST /api/v1/registro · n8n anota el resultado de cada ejecución. */
    public function registro(Request $request, array $args): Response
    {
        if (($error = $this->autorizar($request, 'registro:escribir')) !== null) {
            return $error;
        }

        $datos = $this->cuerpo($request);

        if ($datos instanceof Response) {
            return $datos;
        }

        $v = (new Validador($datos))
            ->texto('flujo', 80)
            ->texto('operacion', 120)
            ->en('resultado', ['ok', 'error', 'duplicado', 'ignorado'])
            ->texto('ejecucion', 80, false)
            ->texto('tipo_error', 40, false)
            ->texto('event_id', 80, false)
            ->texto('entidad', 80, false)
            ->texto('mensaje', 2000, false)
            ->entero('duracion_ms', 0, null, false);

        if ($v->falla()) {
            return $this->invalido($request, $v->errores());
        }

        $id = $this->app->registro()->anotar([...$datos, 'origen' => 'n8n']);

        return $this->ok($request, ['resultado' => 'anotado', 'id' => $id], 201, null, 'ok', false);
    }

    /**
     * POST /api/v1/propuestas · n8n guarda la salida estructurada del agente.
     * Siempre queda «pendiente de revisión» (o «error del agente»): el
     * back-office no ejecuta nada sin que una persona la apruebe.
     */
    public function propuesta(Request $request, array $args): Response
    {
        if (($error = $this->autorizar($request, 'propuestas:escribir')) !== null) {
            return $error;
        }

        $datos = $this->cuerpo($request);

        if ($datos instanceof Response) {
            return $datos;
        }

        $esError = ($datos['estado'] ?? '') === 'error_agente';
        $v = (new Validador($datos))
            ->entero('incidencia_id', 1)
            ->en('estado', ['pendiente_revision', 'error_agente'])
            ->texto('tipo_caso', 60, !$esError)
            ->en('accion_propuesta', array_keys(Propuestas::ACCIONES), !$esError)
            ->texto('mensaje_cliente', 1000, false)
            ->texto('justificacion', 1000)
            ->decimal('confianza', 0, 1, !$esError)
            ->en('prioridad', ['alta', 'media', 'baja'], !$esError)
            ->texto('modelo', 80, false)
            ->texto('ejecucion', 80, false);

        foreach (['riesgos', 'datos_consultados'] as $lista) {
            if (isset($datos[$lista])) {
                $v->lista($lista, 0, 20);
            }
        }

        if ($v->falla()) {
            return $this->invalido($request, $v->errores(), 'incidencia ' . ($datos['incidencia_id'] ?? '?'));
        }

        if ($this->app->incidencias()->buscar((int) $datos['incidencia_id']) === null) {
            return $this->error($request, 404, 'incidencia_no_encontrada', 'La incidencia no existe.', 'no_encontrado', 'incidencia ' . $datos['incidencia_id']);
        }

        $id = $this->app->propuestas()->registrar($datos);

        return $this->ok($request, [
            'resultado' => 'registrada',
            'id'        => $id,
            'estado'    => $datos['estado'],
            'enlace'    => $this->app->absoluteUrl('/propuestas#propuesta-' . $id),
        ], 201, 'incidencia ' . $datos['incidencia_id']);
    }

    /** POST /api/v1/telegram/vinculos · el cliente abrió el enlace de su pedido en Telegram. */
    public function vincular(Request $request, array $args): Response
    {
        if (($error = $this->autorizar($request, 'telegram:escribir')) !== null) {
            return $error;
        }

        $datos = $this->cuerpo($request);

        if ($datos instanceof Response) {
            return $datos;
        }

        $v = (new Validador($datos))
            ->patron('pedido_referencia', self::REFERENCIA, 'Formato KN-AAAA-NNNNNN.')
            ->patron('chat_id', '/^-?\d{1,20}$/', 'Identificador de chat no válido.');

        if ($v->falla()) {
            return $this->invalido($request, $v->errores());
        }

        $pedido = $this->app->pedidos()->buscar((string) $datos['pedido_referencia']);

        if ($pedido === null || $pedido['cliente_id'] === null) {
            return $this->error($request, 404, 'pedido_no_encontrado', 'El back-office aún no tiene ese pedido.', 'orden_eventos', (string) $datos['pedido_referencia']);
        }

        $this->app->pedidos()->vincularTelegram($pedido, (string) $datos['chat_id']);

        return $this->ok($request, [
            'resultado'         => 'vinculado',
            'cliente_nombre'    => explode(' ', trim((string) $pedido['cliente_nombre']))[0] ?? '',
            'pedido_referencia' => $pedido['referencia'],
            'estado_pedido'     => View::ETIQUETAS[$pedido['estado']] ?? $pedido['estado'],
            'idioma'            => $pedido['idioma'],
        ], 200, (string) $pedido['referencia']);
    }

    /** POST /api/v1/telegram/mensajes · el cliente escribe al bot. */
    public function mensajeTelegram(Request $request, array $args): Response
    {
        if (($error = $this->autorizar($request, 'telegram:escribir')) !== null) {
            return $error;
        }

        $datos = $this->cuerpo($request);

        if ($datos instanceof Response) {
            return $datos;
        }

        $v = (new Validador($datos))
            ->patron('chat_id', '/^-?\d{1,20}$/', 'Identificador de chat no válido.')
            ->texto('texto', 2000);

        if ($v->falla()) {
            return $this->invalido($request, $v->errores());
        }

        $resultado = $this->app->incidencias()->mensajeTelegram((string) $datos['chat_id'], (string) $datos['texto']);

        if ($resultado['resultado'] === 'cliente_desconocido') {
            return $this->error($request, 404, 'cliente_no_vinculado', 'Este chat no está vinculado a ningún cliente.', 'no_encontrado', 'telegram');
        }

        return $this->ok($request, $resultado, 200, 'incidencia ' . $resultado['incidencia_id']);
    }

    /** GET /api/v1/telegram/{chat_id}/pedidos · para el comando /estado del bot. */
    public function pedidosTelegram(Request $request, array $args): Response
    {
        if (($error = $this->autorizar($request, 'pedidos:leer')) !== null) {
            return $error;
        }

        $chat    = (string) ($args['chat_id'] ?? '');
        $cliente = $this->app->db()->one('SELECT * FROM clientes WHERE telegram_chat_id = ?', [$chat]);

        if ($cliente === null) {
            return $this->error($request, 404, 'cliente_no_vinculado', 'Este chat no está vinculado a ningún cliente.', 'no_encontrado', 'telegram');
        }

        $pedidos = array_map(fn (array $p): array => [
            'referencia'  => $p['referencia'],
            'estado'      => View::ETIQUETAS[$p['estado']] ?? $p['estado'],
            'total'       => $this->app->view()->money((int) $p['total_cents'], (string) $p['moneda']),
            'fecha'       => substr((string) $p['creado_en_tienda'], 0, 10),
        ], $this->app->pedidos()->deCliente((int) $cliente['id'], 5));

        return $this->ok($request, ['cliente_nombre' => explode(' ', (string) $cliente['nombre'])[0] ?? '', 'pedidos' => $pedidos], 200, 'telegram');
    }

    /** GET /api/v1/metricas · los mismos datos del cuadro de mando, en JSON. */
    public function metricas(Request $request, array $args): Response
    {
        if (($error = $this->autorizar($request, 'metricas:leer')) !== null) {
            return $error;
        }

        return $this->ok($request, ['metricas' => $this->app->metricas()->todas()], 200, null, 'ok', false);
    }
}
