<?php

declare(strict_types=1);

namespace KitsuneNotes\Repository;

use PDO;

/**
 * Almacén de eventos de negocio.
 *
 * La tabla es de solo inserción (append-only): los eventos nunca se
 * modifican ni se borran, de forma que el histórico es reproducible y
 * puede reprocesarse desde el principio por un consumidor externo.
 */
final class EventRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @param array<string, mixed> $event */
    public function insert(array $event): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO events
                (event_id, event_name, schema_version, source, occurred_at, session_id, actor_type,
                 customer_id, product_id, order_id, payload_json, ip_hash, user_agent, created_at)
             VALUES
                (:event_id, :event_name, :schema_version, :source, :occurred_at, :session_id, :actor_type,
                 :customer_id, :product_id, :order_id, :payload_json, :ip_hash, :user_agent, :created_at)'
        );
        $stmt->execute($event);
    }

    /**
     * @param array{nombre?:string, desde?:string, hasta?:string, sesion?:string, pedido?:string} $filters
     * @return list<array<string, mixed>>
     */
    public function search(array $filters = [], int $limit = 200, int $offset = 0): array
    {
        $where  = [];
        $params = [];

        if (!empty($filters['nombre'])) {
            $where[]          = 'e.event_name = :nombre';
            $params['nombre'] = $filters['nombre'];
        }

        if (!empty($filters['desde'])) {
            $where[]         = 'e.occurred_at >= :desde';
            $params['desde'] = $filters['desde'];
        }

        if (!empty($filters['hasta'])) {
            $where[]         = 'e.occurred_at <= :hasta';
            $params['hasta'] = $filters['hasta'];
        }

        if (!empty($filters['sesion'])) {
            $where[]          = 'e.session_id = :sesion';
            $params['sesion'] = $filters['sesion'];
        }

        $sql = 'SELECT e.*, o.reference AS order_reference, p.name AS product_name, p.sku AS product_sku
                  FROM events e
             LEFT JOIN orders   o ON o.id = e.order_id
             LEFT JOIN products p ON p.id = e.product_id';

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY e.occurred_at DESC, e.id DESC'
              . ' LIMIT ' . max(1, min($limit, 5000))
              . ' OFFSET ' . max(0, $offset);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /** @return array<string, int> */
    public function countsByName(): array
    {
        $rows   = $this->pdo->query(
            'SELECT event_name, COUNT(*) AS total FROM events GROUP BY event_name ORDER BY total DESC'
        )->fetchAll();
        $counts = [];

        foreach ($rows as $row) {
            $counts[(string) $row['event_name']] = (int) $row['total'];
        }

        return $counts;
    }

    public function count(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM events')->fetchColumn();
    }

    /** @return list<string> nombres de evento distintos presentes en el almacén */
    public function distinctNames(): array
    {
        return array_map(
            static fn (array $row): string => (string) $row['event_name'],
            $this->pdo->query('SELECT DISTINCT event_name FROM events ORDER BY event_name')->fetchAll()
        );
    }
}
