<?php

declare(strict_types=1);

namespace KitsuneGestion\Service;

use KitsuneGestion\Core\Database;

/**
 * Incidencias y consultas de postventa (escenario 3).
 *
 * Llegan desde el formulario de soporte de la tienda o desde Telegram,
 * quedan asociadas al cliente y al pedido y guardan la conversación.
 */
final class Incidencias
{
    public const TIPOS = [
        'incidencia_envio' => 'Incidencia con el envío',
        'producto_danado'  => 'Producto dañado o incorrecto',
        'devolucion'       => 'Solicitud de devolución',
        'consulta_pedido'  => 'Consulta sobre un pedido',
        'otra_consulta'    => 'Otra consulta',
        'mensaje_telegram' => 'Mensaje recibido por Telegram',
    ];

    public const CATEGORIAS = [
        'envio_retrasado' => 'Envío retrasado o perdido',
        'producto_danado' => 'Producto dañado o incorrecto',
        'devolucion'      => 'Devolución o cambio',
        'error_pedido'    => 'Error en el pedido o en el cobro',
        'consulta'        => 'Consulta general',
        'otro'            => 'Otro',
    ];

    public const PRIORIDADES = ['alta', 'media', 'baja'];

    public const ESTADOS = ['abierta', 'en_curso', 'pendiente_cliente', 'resuelta', 'cerrada'];

    public function __construct(private readonly Database $db, private readonly Pedidos $pedidos)
    {
    }

    /**
     * Registra una incidencia llegada de la tienda. Idempotente por la
     * referencia del ticket.
     *
     * @param array<string, mixed> $datos
     * @return array{resultado:string, id:int, pedido_encontrado:bool, cliente_id:int|null}
     */
    public function registrar(array $datos): array
    {
        return $this->db->transaction(function () use ($datos): array {
            $ticket = (string) ($datos['referencia_ticket'] ?? '');

            if ($ticket !== '' && ($id = $this->db->value('SELECT id FROM incidencias WHERE referencia_ticket = ?', [$ticket])) !== null) {
                $clienteExistente = $this->db->value('SELECT cliente_id FROM incidencias WHERE id = ?', [(int) $id]);

                return ['resultado' => 'duplicado', 'id' => (int) $id, 'pedido_encontrado' => true, 'cliente_id' => $clienteExistente !== null ? (int) $clienteExistente : null];
            }

            $pedido    = !empty($datos['pedido_referencia']) ? $this->pedidos->buscar((string) $datos['pedido_referencia']) : null;
            $clienteId = $this->pedidos->guardarCliente((array) ($datos['cliente'] ?? []));
            $clienteId ??= $pedido !== null && $pedido['cliente_id'] !== null ? (int) $pedido['cliente_id'] : null;
            $creada    = (string) ($datos['ocurrido_en'] ?? date(DATE_ATOM));

            $id = $this->db->insert('incidencias', [
                'referencia_ticket' => $ticket !== '' ? $ticket : null,
                'pedido_id'         => $pedido['id'] ?? null,
                'cliente_id'        => $clienteId,
                'tipo'              => $datos['tipo'],
                'asunto'            => mb_substr((string) ($datos['asunto'] ?? ''), 0, 160),
                'estado'            => 'abierta',
                'canal'             => $datos['canal'] ?? 'formulario_web',
                'idioma'            => $datos['idioma'] ?? 'es',
                'creada_en'         => $creada,
                'actualizada_en'    => date(DATE_ATOM),
                'evento_origen'     => $datos['evento_origen'] ?? null,
            ]);

            $this->anadirMensaje($id, 'cliente', (string) ($datos['canal'] ?? 'formulario_web'), (string) $datos['mensaje'], $creada);

            return ['resultado' => 'creada', 'id' => $id, 'pedido_encontrado' => $pedido !== null, 'cliente_id' => $clienteId];
        });
    }

    /** @return array<string, mixed>|null */
    public function buscar(int $id): ?array
    {
        return $this->db->one(
            'SELECT i.*, p.referencia AS pedido_referencia, p.estado AS pedido_estado,
                    c.nombre AS cliente_nombre, c.email AS cliente_email, c.telegram_chat_id
               FROM incidencias i
               LEFT JOIN pedidos p ON p.id = i.pedido_id
               LEFT JOIN clientes c ON c.id = i.cliente_id
              WHERE i.id = ?',
            [$id]
        );
    }

    /** @return list<array<string, mixed>> */
    public function listar(?string $estado = null, int $limite = 200): array
    {
        return $this->db->all(
            "SELECT i.*, p.referencia AS pedido_referencia, c.nombre AS cliente_nombre,
                    (SELECT COUNT(*) FROM propuestas pr
                      WHERE pr.incidencia_id = i.id AND pr.estado = 'pendiente_revision') AS propuestas_pendientes
               FROM incidencias i
               LEFT JOIN pedidos p ON p.id = i.pedido_id
               LEFT JOIN clientes c ON c.id = i.cliente_id
              WHERE (:estado IS NULL OR i.estado = :estado)
              ORDER BY CASE i.prioridad WHEN 'alta' THEN 0 WHEN 'media' THEN 1 WHEN 'baja' THEN 2 ELSE 3 END, i.id DESC
              LIMIT " . max(1, $limite),
            ['estado' => $estado]
        );
    }

    /** @return list<array<string, mixed>> */
    public function mensajes(int $id): array
    {
        return $this->db->all('SELECT * FROM mensajes_incidencia WHERE incidencia_id = ? ORDER BY id', [$id]);
    }

    /** @return list<array<string, mixed>> incidencias anteriores del mismo cliente */
    public function deCliente(int $clienteId, int $limite = 10): array
    {
        return $this->db->all(
            'SELECT id, tipo, categoria, prioridad, estado, creada_en, resuelta_en
               FROM incidencias WHERE cliente_id = ? ORDER BY id DESC LIMIT ' . max(1, $limite),
            [$clienteId]
        );
    }

    public function anadirMensaje(int $id, string $autor, string $canal, string $texto, ?string $cuando = null): void
    {
        $this->db->insert('mensajes_incidencia', [
            'incidencia_id' => $id,
            'autor'         => $autor,
            'canal'         => $canal,
            'texto'         => mb_substr($texto, 0, 4000),
            'creado_en'     => $cuando ?? date(DATE_ATOM),
        ]);
        $this->db->update('incidencias', ['actualizada_en' => date(DATE_ATOM)], 'id = ?', [$id]);
    }

    /**
     * Clasificación (categoría y prioridad). Es una acción reversible: la
     * puede hacer el agente de IA y el personal la puede cambiar después.
     */
    public function clasificar(int $id, string $categoria, string $prioridad, string $motivo, string $actor): void
    {
        $this->db->update('incidencias', [
            'categoria'      => $categoria,
            'prioridad'      => $prioridad,
            'actualizada_en' => date(DATE_ATOM),
        ], 'id = ?', [$id]);

        $texto = sprintf(
            'Clasificada como «%s», prioridad %s%s.',
            self::CATEGORIAS[$categoria] ?? $categoria,
            $prioridad,
            $motivo !== '' ? '. Motivo: ' . $motivo : ''
        );
        $this->anadirMensaje($id, $actor === 'agente-ia' ? 'agente' : 'equipo', 'interno', $texto);
    }

    public function cambiarEstado(int $id, string $estado, string $actor): void
    {
        $actual = $this->buscar($id);

        if ($actual === null || $actual['estado'] === $estado) {
            return;
        }

        $cambios = ['estado' => $estado, 'actualizada_en' => date(DATE_ATOM)];

        if (in_array($estado, ['resuelta', 'cerrada'], true) && $actual['resuelta_en'] === null) {
            $cambios['resuelta_en'] = date(DATE_ATOM);
        } elseif (!in_array($estado, ['resuelta', 'cerrada'], true)) {
            $cambios['resuelta_en'] = null;
        }

        $this->db->update('incidencias', $cambios, 'id = ?', [$id]);
        $this->anadirMensaje($id, 'sistema', 'interno', sprintf('Estado: %s → %s (%s).', $actual['estado'], $estado, $actor));
    }

    /**
     * Mensaje que un cliente escribe al bot de Telegram. Se añade a su
     * incidencia abierta o, si no tiene ninguna, abre una nueva consulta.
     *
     * @return array<string, mixed>
     */
    public function mensajeTelegram(string $chatId, string $texto): array
    {
        $cliente = $this->db->one('SELECT * FROM clientes WHERE telegram_chat_id = ?', [$chatId]);

        if ($cliente === null) {
            return ['resultado' => 'cliente_desconocido'];
        }

        $abierta = $this->db->one(
            "SELECT * FROM incidencias WHERE cliente_id = ? AND estado NOT IN ('resuelta', 'cerrada') ORDER BY id DESC LIMIT 1",
            [(int) $cliente['id']]
        );

        if ($abierta !== null) {
            $this->anadirMensaje((int) $abierta['id'], 'cliente', 'telegram', $texto);

            if ($abierta['estado'] === 'pendiente_cliente') {
                $this->cambiarEstado((int) $abierta['id'], 'en_curso', 'cliente por Telegram');
            }

            return ['resultado' => 'anadido', 'incidencia_id' => (int) $abierta['id'], 'nueva' => false, 'cliente_nombre' => $this->nombrePila((string) $cliente['nombre'])];
        }

        $ultimoPedido = $this->db->one('SELECT referencia FROM pedidos WHERE cliente_id = ? ORDER BY id DESC LIMIT 1', [(int) $cliente['id']]);
        $alta = $this->registrar([
            'tipo'              => 'mensaje_telegram',
            'asunto'            => mb_substr($texto, 0, 60),
            'mensaje'           => $texto,
            'canal'             => 'telegram',
            'pedido_referencia' => $ultimoPedido['referencia'] ?? null,
            'cliente'           => ['tienda_id' => $cliente['tienda_cliente_id'], 'email' => $cliente['email']],
        ]);

        return ['resultado' => 'creada', 'incidencia_id' => $alta['id'], 'nueva' => true, 'cliente_nombre' => $this->nombrePila((string) $cliente['nombre'])];
    }

    public function contarAbiertas(): int
    {
        return (int) $this->db->value("SELECT COUNT(*) FROM incidencias WHERE estado NOT IN ('resuelta', 'cerrada')");
    }

    private function nombrePila(string $nombre): string
    {
        return explode(' ', trim($nombre))[0] ?? '';
    }
}
