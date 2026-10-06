<?php

declare(strict_types=1);

namespace KitsuneNotes\Repository;

use DateTimeImmutable;
use DateTimeZone;
use PDO;

/**
 * Buzón de correos de prueba (tabla mail_outbox).
 *
 * Los mensajes se guardan una sola vez y su contenido no se modifica: solo
 * cambian la marca de lectura que usa el back-office y el resultado de la
 * entrega (solo buzón, enviado por SMTP o fallido).
 */
final class MailRepository
{
    private const LIST_COLUMNS = 'm.id, m.message_id, m.template, m.to_email, m.to_name, m.subject,
                                  m.order_id, m.invoice_id, m.created_at, m.read_at,
                                  m.delivery_status, m.delivered_at,
                                  o.reference AS order_reference';

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @param array<string, mixed> $data */
    public function insert(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO mail_outbox
                (message_id, template, to_email, to_name, from_email, from_name, subject,
                 body_html, body_text, order_id, invoice_id, created_at, read_at,
                 delivery_status, delivery_detail, delivered_at)
             VALUES
                (:message_id, :template, :to_email, :to_name, :from_email, :from_name, :subject,
                 :body_html, :body_text, :order_id, :invoice_id, :created_at, :read_at,
                 :delivery_status, :delivery_detail, :delivered_at)'
        );
        $stmt->execute([
            'message_id' => $data['message_id'],
            'template'   => $data['template'],
            'to_email'   => $data['to_email'],
            'to_name'    => $data['to_name'],
            'from_email' => $data['from_email'],
            'from_name'  => $data['from_name'],
            'subject'    => $data['subject'],
            'body_html'  => $data['body_html'],
            'body_text'  => $data['body_text'],
            'order_id'   => $data['order_id'],
            'invoice_id' => $data['invoice_id'],
            'created_at' => $data['created_at'],
            'read_at'    => $data['read_at'],
            'delivery_status' => $data['delivery_status'] ?? 'solo_buzon',
            'delivery_detail' => $data['delivery_detail'] ?? '',
            'delivered_at'    => $data['delivered_at'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** Anota el resultado de la entrega real (el contenido del mensaje no cambia). */
    public function markDelivery(int $id, string $status, string $detail, ?string $deliveredAt): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE mail_outbox
                SET delivery_status = :status, delivery_detail = :detail, delivered_at = :delivered_at
              WHERE id = :id'
        );
        $stmt->execute([
            'status'       => $status,
            'detail'       => $detail,
            'delivered_at' => $deliveredAt,
            'id'           => $id,
        ]);
    }

    /** @return array<string, int> número de correos por estado de entrega */
    public function countsByDelivery(): array
    {
        $rows = $this->pdo->query(
            'SELECT delivery_status, COUNT(*) AS total FROM mail_outbox GROUP BY delivery_status'
        )->fetchAll();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['delivery_status']] = (int) $row['total'];
        }

        return $counts;
    }

    /** @return array<string, mixed>|null mensaje completo, con cuerpos */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT m.*, o.reference AS order_reference
               FROM mail_outbox m
          LEFT JOIN orders o ON o.id = m.order_id
              WHERE m.id = :id'
        );
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Listado sin cuerpos (son pesados y no hacen falta en la tabla).
     *
     * @param array{q?:string, plantilla?:string} $filters
     *
     * @return list<array<string, mixed>>
     */
    public function search(array $filters = [], int $limit = 200): array
    {
        $where  = [];
        $params = [];

        if (!empty($filters['plantilla'])) {
            $where[]             = 'm.template = :plantilla';
            $params['plantilla'] = $filters['plantilla'];
        }

        if (!empty($filters['q'])) {
            $where[] = '(m.to_email LIKE :q1 OR m.to_name LIKE :q2 OR m.subject LIKE :q3 OR o.reference LIKE :q4)';
            foreach (['q1', 'q2', 'q3', 'q4'] as $key) {
                $params[$key] = '%' . $filters['q'] . '%';
            }
        }

        $sql = 'SELECT ' . self::LIST_COLUMNS . '
                  FROM mail_outbox m
             LEFT JOIN orders o ON o.id = m.order_id';

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY m.created_at DESC, m.id DESC LIMIT ' . max(1, min($limit, 500));

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /** @return list<array<string, mixed>> correos asociados a un pedido, el más reciente primero */
    public function forOrder(int $orderId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ' . self::LIST_COLUMNS . '
               FROM mail_outbox m
          LEFT JOIN orders o ON o.id = m.order_id
              WHERE m.order_id = :order_id
           ORDER BY m.created_at DESC, m.id DESC'
        );
        $stmt->execute(['order_id' => $orderId]);

        return $stmt->fetchAll();
    }

    public function markRead(int $id, ?string $at = null): void
    {
        $at ??= (new DateTimeImmutable('now', new DateTimeZone('Europe/Madrid')))->format(DATE_ATOM);

        $stmt = $this->pdo->prepare('UPDATE mail_outbox SET read_at = :at WHERE id = :id AND read_at IS NULL');
        $stmt->execute(['at' => $at, 'id' => $id]);
    }

    public function count(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM mail_outbox')->fetchColumn();
    }

    public function countUnread(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM mail_outbox WHERE read_at IS NULL')->fetchColumn();
    }
}
