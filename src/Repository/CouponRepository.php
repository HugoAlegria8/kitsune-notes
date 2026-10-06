<?php

declare(strict_types=1);

namespace KitsuneNotes\Repository;

use PDO;

final class CouponRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string, mixed>|null */
    public function findActive(string $code): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM coupons
              WHERE UPPER(code) = UPPER(:code)
                AND is_active = 1
                AND (valid_until IS NULL OR valid_until >= :now)'
        );
        $stmt->execute([
            'code' => $code,
            'now'  => (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Madrid')))->format(DATE_ATOM),
        ]);

        return $stmt->fetch() ?: null;
    }

    /** @return list<array<string, mixed>> */
    public function active(): array
    {
        return $this->pdo->query('SELECT * FROM coupons WHERE is_active = 1 ORDER BY code')->fetchAll();
    }
}
