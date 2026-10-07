<?php

declare(strict_types=1);

namespace KitsuneNotes\Repository;

use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class OrderRepository
{
    /** Estados admitidos en el ciclo de vida del pedido. */
    public const STATUSES = [
        'creado',
        'pagado_simulado',
        'pendiente_preparacion',
        'enviado',
        'entregado',
        'cancelado',
        'incidencia',
    ];

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @param array<string, mixed> $data */
    public function insert(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO orders
                (reference, customer_id, status, currency, locale, fx_rate_micros, total_base_cents,
                 items_total_cents, discount_cents,
                 shipping_cents, giftwrap_cents, taxable_base_cents, tax_cents, total_cents, coupon_code,
                 shipping_method, shipping_name, shipping_address, shipping_postal_code, shipping_city,
                 shipping_province, shipping_country, gift_wrap, customer_notes, session_id,
                 created_at, updated_at)
             VALUES
                (:reference, :customer_id, :status, :currency, :locale, :fx_rate_micros, :total_base_cents,
                 :items_total_cents, :discount_cents,
                 :shipping_cents, :giftwrap_cents, :taxable_base_cents, :tax_cents, :total_cents, :coupon_code,
                 :shipping_method, :shipping_name, :shipping_address, :shipping_postal_code, :shipping_city,
                 :shipping_province, :shipping_country, :gift_wrap, :customer_notes, :session_id,
                 :created_at, :updated_at)'
        );

        // Un pedido sin estos datos es un pedido en euros y en español.
        $stmt->execute($data + [
            'locale'           => 'es',
            'fx_rate_micros'   => 1000000,
            'total_base_cents' => (int) ($data['total_cents'] ?? 0),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateReference(int $orderId, string $reference): void
    {
        $stmt = $this->pdo->prepare('UPDATE orders SET reference = :reference WHERE id = :id');
        $stmt->execute(['reference' => $reference, 'id' => $orderId]);
    }

    /** @param array<string, mixed> $line */
    public function insertLine(int $orderId, array $line): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO order_lines
                (order_id, product_id, sku, name, design_line, image_path,
                 unit_price_cents, quantity, tax_rate, line_total_cents)
             VALUES (:order_id, :product_id, :sku, :name, :design_line, :image_path,
                     :unit_price_cents, :quantity, :tax_rate, :line_total_cents)'
        );
        $stmt->execute($line + ['order_id' => $orderId]);
    }

    /** @return array<string, mixed>|null */
    public function findByReference(string $reference): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT o.*, c.email AS customer_email, c.full_name AS customer_name, c.phone AS customer_phone
               FROM orders o
               JOIN customers c ON c.id = o.customer_id
              WHERE o.reference = :reference'
        );
        $stmt->execute(['reference' => $reference]);

        return $stmt->fetch() ?: null;
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT o.*, c.email AS customer_email, c.full_name AS customer_name, c.phone AS customer_phone
               FROM orders o
               JOIN customers c ON c.id = o.customer_id
              WHERE o.id = :id'
        );
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    /** @return list<array<string, mixed>> */
    public function lines(int $orderId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM order_lines WHERE order_id = :id ORDER BY id');
        $stmt->execute(['id' => $orderId]);

        return $stmt->fetchAll();
    }

    /**
     * @param array{estado?:string, q?:string} $filters
     * @return list<array<string, mixed>>
     */
    public function search(array $filters = [], int $limit = 100): array
    {
        $where  = [];
        $params = [];

        if (!empty($filters['estado'])) {
            $where[]          = 'o.status = :estado';
            $params['estado'] = $filters['estado'];
        }

        if (!empty($filters['q'])) {
            $where[] = '(o.reference LIKE :q1 OR c.email LIKE :q2 OR c.full_name LIKE :q3)';
            foreach (['q1', 'q2', 'q3'] as $key) {
                $params[$key] = '%' . $filters['q'] . '%';
            }
        }

        $sql = 'SELECT o.*, c.email AS customer_email, c.full_name AS customer_name,
                       (SELECT COUNT(*) FROM order_lines l WHERE l.order_id = o.id) AS line_count
                  FROM orders o
                  JOIN customers c ON c.id = o.customer_id';

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY o.created_at DESC, o.id DESC LIMIT ' . max(1, min($limit, 500));

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function updateStatus(int $orderId, string $status): void
    {
        $now  = (new DateTimeImmutable('now', new DateTimeZone('Europe/Madrid')))->format(DATE_ATOM);
        $stmt = $this->pdo->prepare('UPDATE orders SET status = :status, updated_at = :now WHERE id = :id');
        $stmt->execute(['status' => $status, 'now' => $now, 'id' => $orderId]);
    }

    public function addHistory(int $orderId, ?string $from, string $to, string $changedBy, string $note = ''): void
    {
        $now  = (new DateTimeImmutable('now', new DateTimeZone('Europe/Madrid')))->format(DATE_ATOM);
        $stmt = $this->pdo->prepare(
            'INSERT INTO order_status_history (order_id, from_status, to_status, changed_by, note, created_at)
             VALUES (:order_id, :from_status, :to_status, :changed_by, :note, :created_at)'
        );
        $stmt->execute([
            'order_id'    => $orderId,
            'from_status' => $from,
            'to_status'   => $to,
            'changed_by'  => $changedBy,
            'note'        => $note,
            'created_at'  => $now,
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function history(int $orderId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM order_status_history WHERE order_id = :id ORDER BY created_at, id'
        );
        $stmt->execute(['id' => $orderId]);

        return $stmt->fetchAll();
    }

    /** Siguiente número de secuencia para la referencia del pedido. */
    public function nextSequence(): int
    {
        return ((int) $this->pdo->query('SELECT COALESCE(MAX(id), 0) FROM orders')->fetchColumn()) + 1;
    }

    /** @return array<string, int> recuento de pedidos por estado */
    public function countsByStatus(): array
    {
        $rows   = $this->pdo->query('SELECT status, COUNT(*) AS total FROM orders GROUP BY status')->fetchAll();
        $counts = [];

        foreach ($rows as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * Ingresos acumulados en la moneda base (euros). Se suma el contravalor
     * guardado con cada pedido, no su total: los pedidos pueden estar en
     * monedas distintas y sumar libras con euros no tendría sentido.
     */
    public function totalRevenueCents(): int
    {
        return (int) $this->pdo->query(
            "SELECT COALESCE(SUM(total_base_cents), 0) FROM orders WHERE status <> 'cancelado'"
        )->fetchColumn();
    }

    /**
     * Ventas por moneda de compra, sin los pedidos cancelados.
     *
     * @return list<array{currency:string, orders:int, total_cents:int, total_base_cents:int}>
     */
    public function revenueByCurrency(): array
    {
        $rows = $this->pdo->query(
            "SELECT currency, COUNT(*) AS orders, COALESCE(SUM(total_cents), 0) AS total_cents,
                    COALESCE(SUM(total_base_cents), 0) AS total_base_cents
               FROM orders
              WHERE status <> 'cancelado'
              GROUP BY currency
              ORDER BY currency"
        )->fetchAll();

        return array_map(static fn (array $row): array => [
            'currency'         => (string) $row['currency'],
            'orders'           => (int) $row['orders'],
            'total_cents'      => (int) $row['total_cents'],
            'total_base_cents' => (int) $row['total_base_cents'],
        ], $rows);
    }

    public function count(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
    }
}
