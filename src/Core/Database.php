<?php

declare(strict_types=1);

namespace KitsuneNotes\Core;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Punto único de acceso a la base de datos.
 *
 * Toda la persistencia pasa por PDO, de modo que el motor concreto
 * (SQLite en desarrollo, MySQL/MariaDB en un hosting compartido) es una
 * decisión de configuración y no afecta al resto de la aplicación.
 */
final class Database
{
    private ?PDO $connection = null;

    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config)
    {
    }

    public function pdo(): PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        $driver = (string) ($this->config['driver'] ?? 'sqlite');

        try {
            $this->connection = match ($driver) {
                'sqlite' => $this->connectSqlite(),
                'mysql'  => $this->connectMysql(),
                default  => throw new RuntimeException("Motor de base de datos no soportado: {$driver}"),
            };
        } catch (PDOException $e) {
            throw new RuntimeException(
                'No se ha podido conectar con la base de datos. '
                . '¿Has ejecutado «php bin/install.php»? Detalle: ' . $e->getMessage(),
                previous: $e
            );
        }

        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

        // Una base creada con una versión anterior recibe las columnas nuevas
        // sin perder datos (véase SchemaUpgrade).
        SchemaUpgrade::apply($this->connection, $driver);

        return $this->connection;
    }

    private function connectSqlite(): PDO
    {
        $path = (string) $this->config['sqlite']['path'];
        $dir  = dirname($path);

        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException("No se puede crear el directorio de la base de datos: {$dir}");
        }

        $pdo = new PDO('sqlite:' . $path);
        // Las claves ajenas no están activas por defecto en SQLite.
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');

        return $pdo;
    }

    private function connectMysql(): PDO
    {
        $c   = $this->config['mysql'];
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $c['host'],
            $c['port'],
            $c['database'],
            $c['charset']
        );

        return new PDO($dsn, (string) $c['username'], (string) $c['password']);
    }

    public function driver(): string
    {
        return (string) ($this->config['driver'] ?? 'sqlite');
    }
}
