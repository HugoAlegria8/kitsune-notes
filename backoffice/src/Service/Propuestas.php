<?php

declare(strict_types=1);

namespace KitsuneGestion\Service;

use KitsuneGestion\Core\Database;

/**
 * Propuestas del agente de IA y su revisión humana.
 *
 * El agente nunca escribe al cliente: deja aquí una propuesta con su salida
 * estructurada. Una persona del equipo la aprueba (puede corregir el texto)
 * o la rechaza, y queda registrado quién, cuándo y por qué. Solo después de
 * aprobarla, n8n envía el mensaje por Telegram.
 */
final class Propuestas
{
    public const ACCIONES = [
        'responder_cliente'     => 'Responder al cliente',
        'solicitar_informacion' => 'Pedir más información al cliente',
        'proponer_reposicion'   => 'Proponer reposición o devolución',
        'escalar_equipo'        => 'Escalar al equipo (sin mensaje al cliente)',
        'cerrar_sin_accion'     => 'Cerrar sin acción',
    ];

    public const ESTADOS = [
        'pendiente_revision', 'aprobada', 'rechazada', 'ejecutada', 'ejecutada_simulada', 'error_agente', 'fallo_ejecucion',
    ];

    public function __construct(private readonly Database $db, private readonly Incidencias $incidencias)
    {
    }

    /** @param array<string, mixed> $datos salida del agente ya validada */
    public function registrar(array $datos): int
    {
        $id = $this->db->insert('propuestas', [
            'incidencia_id'     => (int) $datos['incidencia_id'],
            'tipo_caso'         => (string) ($datos['tipo_caso'] ?? ''),
            'datos_consultados' => json_encode($datos['datos_consultados'] ?? [], JSON_UNESCAPED_UNICODE),
            'accion_propuesta'  => (string) ($datos['accion_propuesta'] ?? ''),
            'mensaje_cliente'   => (string) ($datos['mensaje_cliente'] ?? ''),
            'justificacion'     => (string) ($datos['justificacion'] ?? ''),
            'confianza'         => isset($datos['confianza']) ? (float) $datos['confianza'] : null,
            'prioridad'         => $datos['prioridad'] ?? null,
            'riesgos'           => json_encode($datos['riesgos'] ?? [], JSON_UNESCAPED_UNICODE),
            'estado'            => (string) $datos['estado'],
            'salida_agente'     => json_encode($datos['salida_agente'] ?? $datos, JSON_UNESCAPED_UNICODE),
            'modelo'            => $datos['modelo'] ?? null,
            'ejecucion_n8n'     => $datos['ejecucion'] ?? null,
            'creada_en'         => date(DATE_ATOM),
        ]);

        $texto = $datos['estado'] === 'error_agente'
            ? 'El agente de IA no pudo analizar la incidencia: ' . ($datos['justificacion'] ?? 'error desconocido') . '. Requiere revisión manual.'
            : sprintf('El agente de IA propone «%s» (propuesta n.º %d, pendiente de revisión).', self::ACCIONES[$datos['accion_propuesta']] ?? $datos['accion_propuesta'], $id);
        $this->incidencias->anadirMensaje((int) $datos['incidencia_id'], 'agente', 'interno', $texto);

        return $id;
    }

    /** @return array<string, mixed>|null */
    public function buscar(int $id): ?array
    {
        return $this->db->one(
            'SELECT pr.*, i.asunto, i.tipo AS incidencia_tipo, i.idioma, i.cliente_id,
                    p.referencia AS pedido_referencia, c.nombre AS cliente_nombre, c.telegram_chat_id
               FROM propuestas pr
               JOIN incidencias i ON i.id = pr.incidencia_id
               LEFT JOIN pedidos p ON p.id = i.pedido_id
               LEFT JOIN clientes c ON c.id = i.cliente_id
              WHERE pr.id = ?',
            [$id]
        );
    }

    /** @return list<array<string, mixed>> */
    public function listar(?string $estado = null, int $limite = 100): array
    {
        return $this->db->all(
            'SELECT pr.*, i.asunto, p.referencia AS pedido_referencia, c.nombre AS cliente_nombre, c.telegram_chat_id
               FROM propuestas pr
               JOIN incidencias i ON i.id = pr.incidencia_id
               LEFT JOIN pedidos p ON p.id = i.pedido_id
               LEFT JOIN clientes c ON c.id = i.cliente_id
              WHERE (:estado IS NULL OR pr.estado = :estado)
              ORDER BY pr.id DESC LIMIT ' . max(1, $limite),
            ['estado' => $estado]
        );
    }

    /** @return list<array<string, mixed>> */
    public function deIncidencia(int $incidenciaId): array
    {
        return $this->db->all(
            'SELECT pr.*, c.telegram_chat_id FROM propuestas pr
               JOIN incidencias i ON i.id = pr.incidencia_id
               LEFT JOIN clientes c ON c.id = i.cliente_id
              WHERE pr.incidencia_id = ? ORDER BY pr.id DESC',
            [$incidenciaId]
        );
    }

    /** Aprobación humana. Devuelve el motivo si no se puede aprobar. */
    public function aprobar(int $id, string $usuario, string $mensajeFinal, string $comentario): ?string
    {
        $propuesta = $this->buscar($id);

        if ($propuesta === null || $propuesta['estado'] !== 'pendiente_revision') {
            return 'Esta propuesta ya no está pendiente de revisión.';
        }

        $necesitaMensaje = !in_array($propuesta['accion_propuesta'], ['escalar_equipo', 'cerrar_sin_accion'], true);

        if ($necesitaMensaje && trim($mensajeFinal) === '') {
            return 'El mensaje para el cliente no puede quedar vacío.';
        }

        $this->db->update('propuestas', [
            'estado'              => 'aprobada',
            'revisado_por'        => $usuario,
            'revisado_en'         => date(DATE_ATOM),
            'comentario_revision' => mb_substr($comentario, 0, 500),
            'mensaje_final'       => $necesitaMensaje ? mb_substr(trim($mensajeFinal), 0, 1000) : null,
        ], 'id = ?', [$id]);

        $editado = $necesitaMensaje && trim($mensajeFinal) !== trim((string) $propuesta['mensaje_cliente']);
        $this->incidencias->anadirMensaje(
            (int) $propuesta['incidencia_id'],
            'equipo',
            'interno',
            sprintf('%s aprueba la propuesta n.º %d%s.', $usuario, $id, $editado ? ' corrigiendo el texto' : '')
        );

        return null;
    }

    public function rechazar(int $id, string $usuario, string $motivo): ?string
    {
        $propuesta = $this->buscar($id);

        if ($propuesta === null || $propuesta['estado'] !== 'pendiente_revision') {
            return 'Esta propuesta ya no está pendiente de revisión.';
        }

        if (trim($motivo) === '') {
            return 'Indica el motivo del rechazo: queda registrado y ayuda a mejorar el agente.';
        }

        $this->db->update('propuestas', [
            'estado'              => 'rechazada',
            'revisado_por'        => $usuario,
            'revisado_en'         => date(DATE_ATOM),
            'comentario_revision' => mb_substr(trim($motivo), 0, 500),
        ], 'id = ?', [$id]);

        $this->incidencias->anadirMensaje((int) $propuesta['incidencia_id'], 'equipo', 'interno', sprintf('%s rechaza la propuesta n.º %d: %s', $usuario, $id, trim($motivo)));

        return null;
    }

    /** Resultado de la ejecución que informa n8n (o el propio back-office si no hay mensaje). */
    public function marcarResultado(int $id, string $estado, string $resultado): void
    {
        $propuesta = $this->buscar($id);

        if ($propuesta === null || !in_array($estado, ['ejecutada', 'ejecutada_simulada', 'fallo_ejecucion'], true)) {
            return;
        }

        if (in_array($propuesta['estado'], ['ejecutada', 'ejecutada_simulada'], true)) {
            return; // ya estaba ejecutada: idempotente
        }

        $this->db->update('propuestas', [
            'estado'       => $estado,
            'resultado'    => mb_substr($resultado, 0, 500),
            'ejecutada_en' => $estado === 'fallo_ejecucion' ? null : date(DATE_ATOM),
        ], 'id = ?', [$id]);

        if ($estado !== 'fallo_ejecucion' && !empty($propuesta['mensaje_final'])) {
            $this->incidencias->anadirMensaje(
                (int) $propuesta['incidencia_id'],
                'equipo',
                $estado === 'ejecutada' ? 'telegram' : 'simulado',
                (string) $propuesta['mensaje_final']
            );

            if (in_array($propuesta['accion_propuesta'], ['solicitar_informacion'], true)) {
                $this->incidencias->cambiarEstado((int) $propuesta['incidencia_id'], 'pendiente_cliente', 'propuesta ' . $id);
            }
        }
    }

    /** @return array<string, int> */
    public function contarPorEstado(): array
    {
        $filas = $this->db->all('SELECT estado, COUNT(*) AS total FROM propuestas GROUP BY estado');

        return array_column($filas, 'total', 'estado');
    }
}
