<?php

declare(strict_types=1);

namespace KitsuneNotes\Repository;

use PDO;

final class PaymentRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @param array<string, mixed> $data */
    public function insert(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO payments
                (order_id, reference, provider, method, status, amount_cents, currency,
                 card_brand, card_last4, authorization_code, decline_reason, response_json, processed_at)
             VALUES
                (:order_id, :reference, :provider, :method, :status, :amount_cents, :currency,
                 :card_brand, :card_last4, :authorization_code, :decline_reason, :response_json, :processed_at)'
        );
        $stmt->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function forOrder(int $orderId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM payments WHERE order_id = :id ORDER BY id');
        $stmt->execute(['id' => $orderId]);

        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function lastAuthorizedForOrder(int $orderId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM payments WHERE order_id = :id AND status = 'autorizado' ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute(['id' => $orderId]);

        return $stmt->fetch() ?: null;
    }

    public function countByStatus(string $status): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM payments WHERE status = :status');
        $stmt->execute(['status' => $status]);

        return (int) $stmt->fetchColumn();
    }
}
