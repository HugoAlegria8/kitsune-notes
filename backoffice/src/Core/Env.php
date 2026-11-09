<?php

declare(strict_types=1);

namespace KitsuneGestion\Core;

/**
 * Lectura de la configuración desde el fichero .env (que no se versiona).
 *
 * Las variables de entorno reales tienen prioridad sobre el fichero: así
 * las pruebas pueden usar una base de datos desechable sin tocar el .env.
 */
final class Env
{
    /** @var array<string, string> */
    private static array $values = [];

    public static function load(string $file): void
    {
        if (!is_file($file)) {
            return;
        }

        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value);

            if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[-1] === $value[0]) {
                $value = substr($value, 1, -1);
            }

            self::$values[$key] = $value;
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $real = getenv($key);

        if ($real !== false && $real !== '') {
            return $real;
        }

        $value = self::$values[$key] ?? null;

        return $value === null || $value === '' ? $default : $value;
    }
}
