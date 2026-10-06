<?php

declare(strict_types=1);

namespace KitsuneNotes\Repository;

use PDO;

final class StaffRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string, mixed>|null */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM staff_users WHERE email = :email');
        $stmt->execute(['email' => mb_strtolower($email)]);

        return $stmt->fetch() ?: null;
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM staff_users WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }
}
