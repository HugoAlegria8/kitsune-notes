<?php

declare(strict_types=1);

namespace KitsuneGestion\Core;

use PDO;
use Throwable;

/**
 * Acceso a la base de datos SQLite propia del back-office.
 *
 * Todas las consultas usan sentencias preparadas. El esquema se crea solo
 * la primera vez (database/schema.sql usa CREATE TABLE IF NOT EXISTS).
 */
final class Database
{
    private PDO $pdo;

    public function __construct(string $path)
    {
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $this->pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        $this->pdo->exec('PRAGMA busy_timeout = 5000');

        try {
            $this->pdo->exec('PRAGMA journal_mode = WAL');
        } catch (Throwable) {
            // Algunos sistemas de ficheros compartidos no admiten WAL: se sigue sin él.
        }
    }

    public function migrate(string $schemaFile): void
    {
        $this->pdo->exec((string) file_get_contents($schemaFile));
    }

    public function hasTable(string $table): bool
    {
        return $this->value("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = ?", [$table]) > 0;
    }

    /** @return list<array<string, mixed>> */
    public function all(string $sql, array $params = []): array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function one(string $sql, array $params = []): ?array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    public function value(string $sql, array $params = []): mixed
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $value = $statement->fetchColumn();

        return $value === false ? null : $value;
    }

    public function run(string $sql, array $params = []): int
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->rowCount();
    }

    /** @param array<string, mixed> $data */
    public function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', array_fill(0, count($columns), '?'))
        );
        $this->run($sql, array_values($data));

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(string $table, array $data, string $where, array $params = []): int
    {
        $set = implode(', ', array_map(static fn (string $column): string => $column . ' = ?', array_keys($data)));

        return $this->run(sprintf('UPDATE %s SET %s WHERE %s', $table, $set, $where), [...array_values($data), ...$params]);
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        $this->pdo->beginTransaction();

        try {
            $result = $callback();
            $this->pdo->commit();

            return $result;
        } catch (Throwable $e) {
            $this->pdo->rollBack();

            throw $e;
        }
    }
}
