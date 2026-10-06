<?php

declare(strict_types=1);

namespace KitsuneNotes\Repository;

use PDO;

/**
 * Acceso a los datos maestros de catálogo.
 *
 * Todas las consultas usan sentencias preparadas: ningún valor
 * procedente del usuario se concatena en el SQL. Cada marcador aparece
 * una sola vez por sentencia porque MySQL, con preparación nativa, no
 * admite reutilizar el mismo parámetro con nombre.
 */
final class ProductRepository
{
    private const SELECT = '
        SELECT p.*,
               c.name AS category_name,  c.slug AS category_slug,
               d.name AS design_line_name, d.slug AS design_line_slug,
               d.mascot AS design_line_mascot, d.native_name AS design_line_native,
               d.color_primary AS design_line_color, d.color_soft AS design_line_soft
          FROM products p
          JOIN categories   c ON c.id = p.category_id
          JOIN design_lines d ON d.id = p.design_line_id
    ';

    /** Campos que el back-office puede escribir. */
    private const WRITABLE = [
        'sku', 'slug', 'name', 'category_id', 'design_line_id', 'brand', 'origin',
        'summary', 'description', 'specs_json', 'price_cents', 'compare_at_cents',
        'stock', 'weight_grams', 'image_path', 'is_active', 'is_featured',
    ];

    public function __construct(private readonly PDO $pdo)
    {
    }

    // -----------------------------------------------------------------
    // Tienda
    // -----------------------------------------------------------------

    /**
     * Listado filtrable del catálogo público (solo productos activos).
     *
     * @param array{categoria?:string, coleccion?:string, q?:string, orden?:string} $filters
     * @return list<array<string, mixed>>
     */
    public function search(array $filters = []): array
    {
        $where  = ['p.is_active = 1'];
        $params = [];

        if (!empty($filters['categoria'])) {
            $where[]             = 'c.slug = :categoria';
            $params['categoria'] = $filters['categoria'];
        }

        if (!empty($filters['coleccion'])) {
            $where[]             = 'd.slug = :coleccion';
            $params['coleccion'] = $filters['coleccion'];
        }

        if (!empty($filters['q'])) {
            $where[] = '(p.name LIKE :q1 OR p.summary LIKE :q2 OR p.brand LIKE :q3 OR p.sku LIKE :q4 OR d.name LIKE :q5)';
            foreach (['q1', 'q2', 'q3', 'q4', 'q5'] as $key) {
                $params[$key] = '%' . $filters['q'] . '%';
            }
        }

        $order = match ($filters['orden'] ?? '') {
            'precio_asc'  => 'p.price_cents ASC',
            'precio_desc' => 'p.price_cents DESC',
            'nombre'      => 'p.name ASC',
            'novedades'   => 'p.created_at DESC',
            default       => 'p.is_featured DESC, p.name ASC',
        };

        $sql  = self::SELECT . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $order;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare(self::SELECT . ' WHERE p.slug = :slug AND p.is_active = 1');
        $stmt->execute(['slug' => $slug]);

        return $stmt->fetch() ?: null;
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(self::SELECT . ' WHERE p.id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    /**
     * @param list<int> $ids
     * @return array<int, array<string, mixed>> indexado por id de producto
     */
    public function findManyByIds(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));

        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt         = $this->pdo->prepare(self::SELECT . " WHERE p.id IN ({$placeholders})");
        $stmt->execute($ids);

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[(int) $row['id']] = $row;
        }

        return $result;
    }

    /** @return list<array<string, mixed>> */
    public function featured(int $limit = 4): array
    {
        $stmt = $this->pdo->prepare(
            self::SELECT . ' WHERE p.is_active = 1 AND p.is_featured = 1 ORDER BY p.name LIMIT :limit'
        );
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function relatedTo(array $product, int $limit = 3): array
    {
        $stmt = $this->pdo->prepare(
            self::SELECT . ' WHERE p.is_active = 1 AND p.id <> :id
                             AND (p.design_line_id = :line1 OR p.category_id = :category)
                           ORDER BY (p.design_line_id = :line2) DESC, p.name
                           LIMIT :limit'
        );
        $stmt->bindValue('id', (int) $product['id'], PDO::PARAM_INT);
        $stmt->bindValue('line1', (int) $product['design_line_id'], PDO::PARAM_INT);
        $stmt->bindValue('line2', (int) $product['design_line_id'], PDO::PARAM_INT);
        $stmt->bindValue('category', (int) $product['category_id'], PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** Descuenta existencias al confirmar un pedido. */
    public function decreaseStock(int $productId, int $quantity): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE products SET stock = CASE WHEN stock > :qty1 THEN stock - :qty2 ELSE 0 END WHERE id = :id'
        );
        $stmt->execute(['qty1' => $quantity, 'qty2' => $quantity, 'id' => $productId]);
    }

    public function countActive(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM products WHERE is_active = 1')->fetchColumn();
    }

    /** @return list<array<string, mixed>> */
    public function lowStock(int $threshold = 20): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT sku, name, stock FROM products WHERE is_active = 1 AND stock <= :t ORDER BY stock ASC'
        );
        $stmt->execute(['t' => $threshold]);

        return $stmt->fetchAll();
    }

    // -----------------------------------------------------------------
    // Back-office: mantenimiento del catálogo
    // -----------------------------------------------------------------

    /**
     * Todos los productos, activos o retirados, con su historial comercial.
     *
     * @param array{q?:string, estado?:string, categoria?:string, coleccion?:string} $filters
     * @return list<array<string, mixed>>
     */
    public function adminList(array $filters = []): array
    {
        $where  = [];
        $params = [];

        if (($filters['estado'] ?? '') === 'activos') {
            $where[] = 'p.is_active = 1';
        } elseif (($filters['estado'] ?? '') === 'retirados') {
            $where[] = 'p.is_active = 0';
        }

        if (!empty($filters['categoria'])) {
            $where[]             = 'c.slug = :categoria';
            $params['categoria'] = $filters['categoria'];
        }

        if (!empty($filters['coleccion'])) {
            $where[]             = 'd.slug = :coleccion';
            $params['coleccion'] = $filters['coleccion'];
        }

        if (!empty($filters['q'])) {
            $where[]      = '(p.name LIKE :q1 OR p.sku LIKE :q2)';
            $params['q1'] = '%' . $filters['q'] . '%';
            $params['q2'] = '%' . $filters['q'] . '%';
        }

        $sql = 'SELECT p.*,
                       c.name AS category_name, c.slug AS category_slug,
                       d.name AS design_line_name, d.slug AS design_line_slug,
                       d.color_primary AS design_line_color, d.color_soft AS design_line_soft,
                       (SELECT COUNT(*) FROM order_lines l WHERE l.product_id = p.id) AS sales_lines,
                       (SELECT COALESCE(SUM(l.quantity), 0) FROM order_lines l WHERE l.product_id = p.id) AS units_sold
                  FROM products p
                  JOIN categories   c ON c.id = p.category_id
                  JOIN design_lines d ON d.id = p.design_line_id';

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY p.is_active DESC, p.sku ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /** @param array<string, mixed> $data */
    public function insert(array $data): int
    {
        $data    = array_intersect_key($data, array_flip(self::WRITABLE));
        $columns = array_keys($data);

        $sql = sprintf(
            'INSERT INTO products (%s, tax_rate, created_at, updated_at) VALUES (%s, 0.21, :created_at, :updated_at)',
            implode(', ', $columns),
            implode(', ', array_map(static fn (string $c): string => ':' . $c, $columns))
        );

        $now  = (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Madrid')))->format(DATE_ATOM);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data + ['created_at' => $now, 'updated_at' => $now]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $data = array_intersect_key($data, array_flip(self::WRITABLE));

        if ($data === []) {
            return;
        }

        $assignments = implode(', ', array_map(
            static fn (string $c): string => "{$c} = :{$c}",
            array_keys($data)
        ));

        $now  = (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Madrid')))->format(DATE_ATOM);
        $stmt = $this->pdo->prepare("UPDATE products SET {$assignments}, updated_at = :updated_at WHERE id = :id");
        $stmt->execute($data + ['updated_at' => $now, 'id' => $id]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM products WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /** Número de líneas de pedido que referencian el producto. */
    public function salesLineCount(int $id): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM order_lines WHERE product_id = :id');
        $stmt->execute(['id' => $id]);

        return (int) $stmt->fetchColumn();
    }

    /** Número de eventos de clientes registrados sobre el producto. */
    public function eventCount(int $id): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM events WHERE product_id = :id');
        $stmt->execute(['id' => $id]);

        return (int) $stmt->fetchColumn();
    }

    public function skuExists(string $sku, ?int $exceptId = null): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM products WHERE UPPER(sku) = UPPER(:sku) AND id <> :id');
        $stmt->execute(['sku' => $sku, 'id' => $exceptId ?? 0]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM products WHERE slug = :slug AND id <> :id');
        $stmt->execute(['slug' => $slug, 'id' => $exceptId ?? 0]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /** Siguiente SKU libre para un prefijo de categoría (KN-CUA-004…). */
    public function nextSku(string $prefix): string
    {
        $stmt = $this->pdo->prepare('SELECT sku FROM products WHERE sku LIKE :p');
        $stmt->execute(['p' => $prefix . '-%']);

        $max = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $sku) {
            if (preg_match('/-(\d+)$/', (string) $sku, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return sprintf('%s-%03d', $prefix, $max + 1);
    }
}
