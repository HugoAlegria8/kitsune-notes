<?php

declare(strict_types=1);

namespace KitsuneNotes\Repository;

use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class CustomerRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string, mixed>|null */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM customers WHERE email = :email');
        $stmt->execute(['email' => mb_strtolower($email)]);

        return $stmt->fetch() ?: null;
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM customers WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Crea el cliente si no existe y, si existe, actualiza sus datos de
     * contacto con los últimos facilitados en el checkout.
     *
     * @param array<string, mixed> $data
     */
    public function upsert(array $data): int
    {
        $email    = mb_strtolower((string) $data['email']);
        $existing = $this->findByEmail($email);
        $now      = (new DateTimeImmutable('now', new DateTimeZone('Europe/Madrid')))->format(DATE_ATOM);

        if ($existing !== null) {
            $stmt = $this->pdo->prepare(
                'UPDATE customers
                    SET full_name = :full_name, phone = :phone, address_line = :address_line,
                        postal_code = :postal_code, city = :city, province = :province
                  WHERE id = :id'
            );
            $stmt->execute([
                'full_name'    => $data['full_name'],
                'phone'        => $data['phone'] ?? '',
                'address_line' => $data['address_line'] ?? '',
                'postal_code'  => $data['postal_code'] ?? '',
                'city'         => $data['city'] ?? '',
                'province'     => $data['province'] ?? '',
                'id'           => (int) $existing['id'],
            ]);

            return (int) $existing['id'];
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO customers
                (email, full_name, phone, address_line, postal_code, city, province, country, is_demo, created_at)
             VALUES (:email, :full_name, :phone, :address_line, :postal_code, :city, :province, :country, 1, :created_at)'
        );
        $stmt->execute([
            'email'        => $email,
            'full_name'    => $data['full_name'],
            'phone'        => $data['phone'] ?? '',
            'address_line' => $data['address_line'] ?? '',
            'postal_code'  => $data['postal_code'] ?? '',
            'city'         => $data['city'] ?? '',
            'province'     => $data['province'] ?? '',
            'country'      => $data['country'] ?? 'ES',
            'created_at'   => $now,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function count(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn();
    }
}
