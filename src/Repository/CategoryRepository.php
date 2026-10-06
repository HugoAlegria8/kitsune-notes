<?php

declare(strict_types=1);

namespace KitsuneNotes\Repository;

use KitsuneNotes\Service\CatalogLocalizer;
use PDO;

final class CategoryRepository
{
    /** Con $localizer, los textos salen en el idioma activo si tienen traducción. */
    public function __construct(
        private readonly PDO $pdo,
        private readonly ?CatalogLocalizer $localizer = null,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function localized(array $row): array
    {
        return $this->localizer !== null ? $this->localizer->category($row) : $row;
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return array_map($this->localized(...), $this->pdo->query(
            'SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.is_active = 1) AS product_count
               FROM categories c
              ORDER BY c.sort_order'
        )->fetchAll());
    }

    /** @return array<string, mixed>|null */
    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM categories WHERE slug = :slug');
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();

        return $row !== false ? $this->localized($row) : null;
    }
}
