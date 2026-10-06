<?php

declare(strict_types=1);

namespace KitsuneNotes\Support;

/**
 * Generador de identificadores UUID v4 (RFC 4122).
 *
 * Se usa para el identificador único de cada evento de negocio, de modo
 * que un consumidor externo pueda deduplicar eventos reenviados.
 */
final class Uuid
{
    public static function v4(): string
    {
        $bytes = random_bytes(16);

        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40); // versión 4
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80); // variante RFC 4122

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
