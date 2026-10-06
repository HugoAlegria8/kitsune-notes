<?php

declare(strict_types=1);

namespace KitsuneNotes\Core;

/**
 * Lector mínimo de variables de entorno con soporte para ficheros .env.
 *
 * Se evita deliberadamente una dependencia externa: el formato admitido
 * es CLAVE=valor, una por línea, con comentarios que empiezan por '#'.
 */
final class Env
{
    /** @var array<string, string> */
    private static array $values = [];

    private static bool $loaded = false;

    public static function load(string $file): void
    {
        self::$loaded = true;

        if (!is_readable($file)) {
            return;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value);

            // Permite valores entrecomillados: KEY="mi valor"
            if (strlen($value) > 1
                && ($value[0] === '"' || $value[0] === "'")
                && $value[0] === substr($value, -1)) {
                $value = substr($value, 1, -1);
            }

            self::$values[$key] = $value;
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        if (!self::$loaded) {
            self::load(dirname(__DIR__, 2) . '/.env');
        }

        if (array_key_exists($key, self::$values)) {
            return self::$values[$key];
        }

        $fromServer = $_SERVER[$key] ?? getenv($key);

        if ($fromServer !== false && $fromServer !== null && $fromServer !== '') {
            return (string) $fromServer;
        }

        return $default;
    }
}
