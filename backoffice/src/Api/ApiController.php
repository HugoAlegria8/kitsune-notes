<?php

declare(strict_types=1);

namespace KitsuneGestion\Api;

use KitsuneGestion\Core\App;
use KitsuneGestion\Core\Request;
use KitsuneGestion\Core\Response;

/**
 * Base de la API REST del back-office (/api/v1).
 *
 * Autenticación por clave en la cabecera X-Api-Key. Hay dos claves, cada
 * una con sus permisos (mínimo privilegio):
 *   - «integracion» (KG_CLAVE_N8N): la usan los flujos de n8n.
 *   - «agente» (KG_CLAVE_AGENTE): la usan las herramientas del agente de IA;
 *     solo puede consultar y clasificar, nunca crear propuestas ni escribir
 *     al cliente.
 * Cada llamada queda en el registro de integración con su resultado, su
 * código HTTP, su duración y, si n8n los envía, la ejecución
 * (X-Correlation-Id) y el evento de origen (Idempotency-Key).
 */
abstract class ApiController
{
    public const PERMISOS = [
        'integracion' => [
            'pedidos:leer', 'pedidos:escribir', 'clientes:leer', 'incidencias:leer', 'incidencias:escribir',
            'incidencias:clasificar', 'propuestas:escribir', 'telegram:escribir', 'eventos:escribir',
            'registro:escribir', 'metricas:leer',
        ],
        'agente' => ['pedidos:leer', 'clientes:leer', 'incidencias:leer', 'incidencias:clasificar'],
    ];

    public const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public const REFERENCIA = '/^KN-\d{4}-\d{6}$/';

    protected ?string $consumidor = null;

    private float $inicio;

    public function __construct(protected readonly App $app)
    {
        $this->inicio = microtime(true);
    }

    /** Devuelve una respuesta de error si la clave no vale o no tiene el permiso. */
    protected function autorizar(Request $request, string $permiso): ?Response
    {
        $clave = (string) $request->header('X-Api-Key');

        foreach (['integracion' => 'CLAVE_N8N', 'agente' => 'CLAVE_AGENTE'] as $consumidor => $variable) {
            $valida = (string) $this->app->config($variable, '');

            if ($valida !== '' && $clave !== '' && hash_equals($valida, $clave)) {
                $this->consumidor = $consumidor;
                break;
            }
        }

        if ($this->consumidor === null) {
            return $this->error($request, 401, 'no_autorizado', 'Falta la cabecera X-Api-Key o la clave no es válida.', 'autenticacion');
        }

        if (!in_array($permiso, self::PERMISOS[$this->consumidor], true)) {
            return $this->error($request, 403, 'sin_permiso', sprintf('La clave «%s» no tiene el permiso %s.', $this->consumidor, $permiso), 'permiso');
        }

        return null;
    }

    /** @return array<string, mixed>|Response */
    protected function cuerpo(Request $request): array|Response
    {
        return $request->json() ?? $this->error($request, 400, 'json_invalido', 'El cuerpo debe ser un objeto JSON válido.', 'formato');
    }

    /** @param array<string, mixed> $datos */
    protected function ok(Request $request, array $datos, int $codigo = 200, ?string $entidad = null, string $resultado = 'ok', bool $anotar = true): Response
    {
        if ($anotar) {
            $this->anotar($request, $resultado, null, $codigo, $entidad, (string) ($datos['resultado'] ?? ''));
        }

        return Response::json(['ok' => true, ...$datos], $codigo);
    }

    /** @param array<string, string> $detalles */
    protected function error(Request $request, int $codigo, string $error, string $mensaje, string $tipo, ?string $entidad = null, array $detalles = []): Response
    {
        $this->anotar(
            $request,
            'error',
            $tipo,
            $codigo,
            $entidad,
            $mensaje . ($detalles !== [] ? ' ' . json_encode($detalles, JSON_UNESCAPED_UNICODE) : '')
        );

        return Response::json(
            ['ok' => false, 'error' => $error, 'mensaje' => $mensaje] + ($detalles !== [] ? ['detalles' => $detalles] : []),
            $codigo
        );
    }

    /** @param array<string, string> $errores */
    protected function invalido(Request $request, array $errores, ?string $entidad = null): Response
    {
        return $this->error($request, 422, 'validacion', 'Los datos no superan la validación del back-office.', 'validacion', $entidad, $errores);
    }

    private function anotar(Request $request, string $resultado, ?string $tipo, int $codigo, ?string $entidad, string $mensaje): void
    {
        $this->app->registro()->anotar([
            'origen'      => 'api',
            'flujo'       => $request->header('X-N8n-Flujo') ?? ($this->consumidor !== null ? 'clave ' . $this->consumidor : null),
            'ejecucion'   => $request->header('X-Correlation-Id'),
            'operacion'   => $request->method . ' ' . $request->path,
            'event_id'    => $request->header('Idempotency-Key'),
            'entidad'     => $entidad,
            'resultado'   => $resultado,
            'tipo_error'  => $tipo,
            'codigo_http' => $codigo,
            'mensaje'     => $mensaje !== '' ? $mensaje : null,
            'duracion_ms' => (int) round((microtime(true) - $this->inicio) * 1000),
        ]);
    }
}
