<?php

declare(strict_types=1);

namespace KitsuneNotes\Support;

/**
 * Utilidades monetarias.
 *
 * Todos los importes de la aplicación se manejan como enteros en
 * céntimos: nunca se opera con números en coma flotante para evitar
 * errores de redondeo acumulados en el cálculo de totales e impuestos.
 */
final class Money
{
    public static function format(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '.') . ' €';
    }

    /** Representación decimal con punto, apta para CSV/JSON. */
    public static function decimal(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    /**
     * Separa un importe con IVA incluido en base imponible y cuota.
     *
     * @return array{base:int, tax:int}
     */
    public static function splitTax(int $grossCents, float $rate): array
    {
        $base = (int) round($grossCents / (1 + $rate));

        return ['base' => $base, 'tax' => $grossCents - $base];
    }
}
