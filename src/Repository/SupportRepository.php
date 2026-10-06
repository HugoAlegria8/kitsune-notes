<?php

declare(strict_types=1);

namespace KitsuneNotes\Repository;

use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class SupportRepository
{
    public const TYPES = [
        'incidencia_envio'  => 'Incidencia con el envío',
        'producto_danado'   => 'Producto dañado o incorrecto',
        'devolucion'        => 'Solicitud de devolución',
        'consulta_pedido'   => 'Consulta sobre un pedido',
        'otra_consulta'     => 'Otra consulta',
    ];

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @param array<string, mixed> $data */
    public function insert(array $data): string
    {
        $now       = (new DateTimeImmutable('now', new DateTimeZone('Europe/Madrid')));
        $sequence  = ((int) $this->pdo->query('SELECT COALESCE(MAX(id), 0) FROM support_tickets')->fetchColumn()) + 1;
        $reference = sprintf('INC-%s-%06d', $now->format('Y'), $sequence);

        $stmt = $this->pdo->prepare(
            'INSERT INTO support_tickets
                (reference, order_reference, customer_name, customer_email, type, subject, message, status, created_at)
             VALUES (:reference, :order_reference, :customer_name, :customer_email, :type, :subject, :message, :status, :created_at)'
        );
        $stmt->execute([
            'reference'       => $reference,
            'order_reference' => $data['order_reference'] ?? '',
            'customer_name'   => $data['customer_name'],
            'customer_email'  => $data['customer_email'],
            'type'            => $data['type'],
            'subject'         => $data['subject'],
            'message'         => $data['message'],
            'status'          => 'abierta',
            'created_at'      => $now->format(DATE_ATOM),
        ]);

        return $reference;
    }

    /** @return list<array<string, mixed>> */
    public function all(?string $status = null): array
    {
        if ($status !== null && $status !== '') {
            $stmt = $this->pdo->prepare('SELECT * FROM support_tickets WHERE status = :s ORDER BY created_at DESC');
            $stmt->execute(['s' => $status]);

            return $stmt->fetchAll();
        }

        return $this->pdo->query('SELECT * FROM support_tickets ORDER BY created_at DESC')->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findByReference(string $reference): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM support_tickets WHERE reference = :r');
        $stmt->execute(['r' => $reference]);

        return $stmt->fetch() ?: null;
    }

    public function updateStatus(string $reference, string $status): void
    {
        $stmt = $this->pdo->prepare('UPDATE support_tickets SET status = :s WHERE reference = :r');
        $stmt->execute(['s' => $status, 'r' => $reference]);
    }

    public function countOpen(): int
    {
        return (int) $this->pdo->query(
            "SELECT COUNT(*) FROM support_tickets WHERE status = 'abierta'"
        )->fetchColumn();
    }
}
