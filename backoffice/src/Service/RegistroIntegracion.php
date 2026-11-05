<?php

declare(strict_types=1);

namespace KitsuneGestion\Service;

use KitsuneGestion\Core\Database;

/**
 * Registro de integración: la traza técnica y funcional de todo lo que entra
 * y sale. La API anota cada llamada que recibe; n8n anota el resultado de
 * cada ejecución; el back-office anota cada envío a n8n. Las métricas del
 * cuadro de mando salen de aquí, no de cifras escritas a mano.
 */
final class RegistroIntegracion
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @param array<string, mixed> $datos */
    public function anotar(array $datos): int
    {
        return $this->db->insert('registro_integracion', [
            'creado_en'   => date(DATE_ATOM),
            'origen'      => mb_substr((string) ($datos['origen'] ?? 'api'), 0, 20),
            'flujo'       => isset($datos['flujo']) ? mb_substr((string) $datos['flujo'], 0, 80) : null,
            'ejecucion'   => isset($datos['ejecucion']) ? mb_substr((string) $datos['ejecucion'], 0, 80) : null,
            'operacion'   => mb_substr((string) ($datos['operacion'] ?? ''), 0, 120),
            'event_id'    => isset($datos['event_id']) ? mb_substr((string) $datos['event_id'], 0, 80) : null,
            'entidad'     => isset($datos['entidad']) ? mb_substr((string) $datos['entidad'], 0, 80) : null,
            'resultado'   => mb_substr((string) ($datos['resultado'] ?? 'ok'), 0, 20),
            'tipo_error'  => isset($datos['tipo_error']) && $datos['tipo_error'] !== '' ? mb_substr((string) $datos['tipo_error'], 0, 40) : null,
            'codigo_http' => isset($datos['codigo_http']) ? (int) $datos['codigo_http'] : null,
            'mensaje'     => isset($datos['mensaje']) ? mb_substr((string) $datos['mensaje'], 0, 500) : null,
            'duracion_ms' => isset($datos['duracion_ms']) ? (int) $datos['duracion_ms'] : null,
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function listar(?string $resultado = null, ?string $origen = null, int $limite = 150): array
    {
        return $this->db->all(
            'SELECT * FROM registro_integracion
              WHERE (:resultado IS NULL OR resultado = :resultado)
                AND (:origen IS NULL OR origen = :origen)
              ORDER BY id DESC LIMIT ' . max(1, $limite),
            ['resultado' => $resultado, 'origen' => $origen]
        );
    }

    /** @return list<array<string, mixed>> */
    public function deEntidad(string $entidad, int $limite = 30): array
    {
        return $this->db->all(
            'SELECT * FROM registro_integracion WHERE entidad = ? ORDER BY id DESC LIMIT ' . max(1, $limite),
            [$entidad]
        );
    }
}
