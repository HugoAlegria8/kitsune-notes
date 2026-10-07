<?php

declare(strict_types=1);

namespace KitsuneNotes\Support;

use PDO;

/**
 * Carga en la base de datos la traducción de los datos maestros de prueba
 * (database/translations_en.php).
 *
 * Regla única: solo se rellenan los campos que están VACÍOS. Así se puede
 * ejecutar las veces que haga falta sin pisar una traducción que el equipo
 * haya corregido desde el back-office, y un producto que ya no exista se
 * ignora sin error.
 */
final class CatalogTranslations
{
    /** Tabla => columna que identifica la fila en el fichero de traducciones. */
    private const KEYS = [
        'categories'   => 'slug',
        'design_lines' => 'slug',
        'products'     => 'sku',
    ];

    /** Columnas que se pueden rellenar en cada tabla (lista cerrada). */
    private const COLUMNS = [
        'categories'   => ['name_en', 'tagline_en', 'description_en'],
        'design_lines' => ['mascot_en', 'tagline_en', 'description_en'],
        'products'     => ['name_en', 'origin_en', 'summary_en', 'description_en', 'specs_json_en'],
    ];

    public static function defaultFile(): string
    {
        return dirname(__DIR__, 2) . '/database/translations_en.php';
    }

    /**
     * Aplica las traducciones del fichero y devuelve cuántos campos ha rellenado.
     */
    public static function apply(PDO $pdo, ?string $file = null): int
    {
        $file ??= self::defaultFile();

        if (!is_readable($file)) {
            return 0;
        }

        $data = require $file;

        if (!is_array($data)) {
            return 0;
        }

        $filled = 0;

        foreach (self::KEYS as $table => $keyColumn) {
            foreach ((array) ($data[$table] ?? []) as $key => $fields) {
                if (!is_array($fields)) {
                    continue;
                }

                // La ficha técnica se escribe como array y se guarda en JSON.
                if (isset($fields['specs']) && is_array($fields['specs'])) {
                    $fields['specs_json_en'] = (string) json_encode($fields['specs'], JSON_UNESCAPED_UNICODE);
                }

                foreach (self::COLUMNS[$table] as $column) {
                    $value = $fields[$column] ?? null;

                    if (!is_string($value) || $value === '') {
                        continue;
                    }

                    // Tabla y columna salen de las listas cerradas de esta clase,
                    // nunca de datos externos; los valores van como parámetros.
                    $stmt = $pdo->prepare(
                        "UPDATE {$table} SET {$column} = :value
                          WHERE {$keyColumn} = :row_key AND ({$column} IS NULL OR {$column} = '')"
                    );
                    $stmt->execute(['value' => $value, 'row_key' => (string) $key]);

                    $filled += $stmt->rowCount();
                }
            }
        }

        return $filled;
    }
}
