<?php

declare(strict_types=1);

namespace KitsuneNotes\Core;

use PDO;
use Throwable;

/**
 * Actualización automática, idempotente y sin pérdida de datos de una base
 * de datos creada con una versión anterior del esquema.
 *
 * Cuando una versión nueva añade columnas, quien ya tenía la tienda
 * instalada no debería perder sus pedidos de prueba ni encontrarse errores
 * por haber copiado el código nuevo encima de la carpeta antigua. Al abrir
 * la conexión se comprueba, con una sola consulta por tabla, si falta alguna
 * columna y se añade con su valor por defecto. No toca ningún dato.
 *
 * Si algo falla (permisos, dos peticiones a la vez…) se traza en el registro
 * de errores y la aplicación sigue: «php bin/install.php --fresh» continúa
 * siendo la vía limpia para empezar de cero.
 */
final class SchemaUpgrade
{
    /**
     * Columnas añadidas después de la primera versión del esquema:
     * tabla => [columna => [definición SQLite, definición MySQL]].
     *
     * @var array<string, array<string, array{0:string, 1:string}>>
     */
    private const ADDED_COLUMNS = [
        // Resultado de la entrega real de cada correo (envío por SMTP opcional).
        'mail_outbox' => [
            'delivery_status' => ["TEXT NOT NULL DEFAULT 'solo_buzon'", "VARCHAR(20) NOT NULL DEFAULT 'solo_buzon'"],
            'delivery_detail' => ["TEXT NOT NULL DEFAULT ''",            "VARCHAR(500) NOT NULL DEFAULT ''"],
            'delivered_at'    => ['TEXT',                                'VARCHAR(40) NULL'],
        ],
    ];

    public static function apply(PDO $pdo, string $driver): void
    {
        foreach (self::ADDED_COLUMNS as $table => $columns) {
            try {
                $existing = self::columnsOf($pdo, $driver, $table);

                // Sin columnas = la tabla no existe todavía (base sin instalar).
                if ($existing === []) {
                    continue;
                }

                foreach ($columns as $name => [$sqlite, $mysql]) {
                    if (!in_array($name, $existing, true)) {
                        $pdo->exec(sprintf(
                            'ALTER TABLE %s ADD COLUMN %s %s',
                            $table,
                            $name,
                            $driver === 'mysql' ? $mysql : $sqlite
                        ));
                    }
                }
            } catch (Throwable $e) {
                error_log('[kitsune-notes] No se pudo actualizar la tabla ' . $table . ': ' . $e->getMessage());
            }
        }
    }

    /** @return list<string> nombres de columna, o lista vacía si la tabla no existe */
    private static function columnsOf(PDO $pdo, string $driver, string $table): array
    {
        if ($driver === 'mysql') {
            $stmt = $pdo->prepare(
                'SELECT COLUMN_NAME FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table'
            );
            $stmt->execute(['table' => $table]);

            return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        }

        $rows = $pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC);

        return array_map(static fn (array $row): string => (string) $row['name'], $rows);
    }
}
