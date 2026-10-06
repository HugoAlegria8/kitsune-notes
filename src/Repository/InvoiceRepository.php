<?php

declare(strict_types=1);

namespace KitsuneNotes\Repository;

use PDO;

/**
 * Facturas expedidas.
 *
 * Es un registro de solo inserción: una factura emitida no se modifica ni
 * se borra (si hubiera un error se expediría una rectificativa, algo que
 * queda fuera del alcance del prototipo).
 */
final class InvoiceRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @param array<string, mixed> $data */
    public function insert(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO invoices
                (number, series_year, serial_no, order_id, issued_at, base_cents, tax_cents, total_cents, data_json)
             VALUES
                (:number, :series_year, :serial_no, :order_id, :issued_at, :base_cents, :tax_cents, :total_cents, :data_json)'
        );
        $stmt->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    /** Siguiente número correlativo dentro del año de la serie. */
    public function nextSerial(int $year): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(serial_no), 0) FROM invoices WHERE series_year = :year');
        $stmt->execute(['year' => $year]);

        return ((int) $stmt->fetchColumn()) + 1;
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM invoices WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    /** @return array<string, mixed>|null */
    public function findByOrderId(int $orderId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM invoices WHERE order_id = :order_id');
        $stmt->execute(['order_id' => $orderId]);

        return $stmt->fetch() ?: null;
    }

    public function count(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM invoices')->fetchColumn();
    }
}
