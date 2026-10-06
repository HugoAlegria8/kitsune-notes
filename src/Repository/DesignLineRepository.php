<?php

declare(strict_types=1);

namespace KitsuneNotes\Repository;

use PDO;

final class DesignLineRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->pdo->query(
            'SELECT d.*, (SELECT COUNT(*) FROM products p WHERE p.design_line_id = d.id AND p.is_active = 1) AS product_count
               FROM design_lines d
              ORDER BY d.sort_order'
        )->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM design_lines WHERE slug = :slug');
        $stmt->execute(['slug' => $slug]);

        return $stmt->fetch() ?: null;
    }
}
