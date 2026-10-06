<?php

declare(strict_types=1);

namespace KitsuneNotes\Core;

use KitsuneNotes\Support\CatalogTranslations;
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
 * Algunas columnas nuevas necesitan además un valor inicial que no puede
 * ser un simple valor por defecto (por ejemplo, el contravalor en euros de
 * los pedidos que ya existían). Ese relleno se hace UNA vez, en la misma
 * petición en la que se crea la columna (véase migrateData()).
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
     * En MySQL las columnas TEXT nuevas admiten NULL porque ese tipo no
     * puede llevar valor por defecto; la aplicación trata NULL como vacío.
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

        // Internacionalización: traducción al inglés de los datos maestros.
        'categories' => [
            'name_en'        => ["TEXT NOT NULL DEFAULT ''", "VARCHAR(120) NOT NULL DEFAULT ''"],
            'tagline_en'     => ["TEXT NOT NULL DEFAULT ''", "VARCHAR(190) NOT NULL DEFAULT ''"],
            'description_en' => ["TEXT NOT NULL DEFAULT ''", 'TEXT NULL'],
        ],
        'design_lines' => [
            'mascot_en'      => ["TEXT NOT NULL DEFAULT ''", "VARCHAR(60) NOT NULL DEFAULT ''"],
            'tagline_en'     => ["TEXT NOT NULL DEFAULT ''", "VARCHAR(190) NOT NULL DEFAULT ''"],
            'description_en' => ["TEXT NOT NULL DEFAULT ''", 'TEXT NULL'],
        ],
        'products' => [
            'name_en'        => ["TEXT NOT NULL DEFAULT ''", "VARCHAR(190) NOT NULL DEFAULT ''"],
            'origin_en'      => ["TEXT NOT NULL DEFAULT ''", "VARCHAR(80) NOT NULL DEFAULT ''"],
            'summary_en'     => ["TEXT NOT NULL DEFAULT ''", "VARCHAR(400) NOT NULL DEFAULT ''"],
            'description_en' => ["TEXT NOT NULL DEFAULT ''", 'TEXT NULL'],
            'specs_json_en'  => ["TEXT NOT NULL DEFAULT ''", 'TEXT NULL'],
        ],

        // Pedidos en varias monedas: idioma de la compra, tipo de cambio
        // aplicado (en millonésimas) y contravalor del total en euros.
        'orders' => [
            'locale'           => ["TEXT NOT NULL DEFAULT 'es'",        "VARCHAR(5) NOT NULL DEFAULT 'es'"],
            'fx_rate_micros'   => ['INTEGER NOT NULL DEFAULT 1000000',  'INT NOT NULL DEFAULT 1000000'],
            'total_base_cents' => ['INTEGER NOT NULL DEFAULT 0',        'INT NOT NULL DEFAULT 0'],
        ],

        // Idioma en que el cliente escribió la solicitud (para responderle en él).
        'support_tickets' => [
            'locale' => ["TEXT NOT NULL DEFAULT 'es'", "VARCHAR(5) NOT NULL DEFAULT 'es'"],
        ],
    ];

    public static function apply(PDO $pdo, string $driver): void
    {
        /** @var list<string> $added columnas creadas en esta petición, como «tabla.columna» */
        $added = [];

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

                        $added[] = $table . '.' . $name;
                    }
                }
            } catch (Throwable $e) {
                error_log('[kitsune-notes] No se pudo actualizar la tabla ' . $table . ': ' . $e->getMessage());
            }
        }

        if ($added !== []) {
            self::migrateData($pdo, $added);
        }
    }

    /**
     * Valores iniciales de las columnas recién creadas. Cada paso es
     * idempotente por sí mismo, por si dos peticiones coinciden.
     *
     * @param list<string> $added
     */
    private static function migrateData(PDO $pdo, array $added): void
    {
        try {
            // Los pedidos anteriores a las monedas se hicieron todos en euros:
            // su contravalor en la moneda base es el propio total.
            if (in_array('orders.total_base_cents', $added, true)) {
                $pdo->exec('UPDATE orders SET total_base_cents = total_cents WHERE total_base_cents = 0');
            }

            // El catálogo de prueba recibe su traducción al inglés. Solo se
            // rellenan campos vacíos; los productos dados de alta por el equipo
            // se quedan en español hasta que se traduzcan desde el back-office.
            foreach (['categories.name_en', 'design_lines.mascot_en', 'products.name_en'] as $trigger) {
                if (in_array($trigger, $added, true)) {
                    CatalogTranslations::apply($pdo);
                    break;
                }
            }
        } catch (Throwable $e) {
            error_log('[kitsune-notes] No se pudieron inicializar las columnas nuevas: ' . $e->getMessage());
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
