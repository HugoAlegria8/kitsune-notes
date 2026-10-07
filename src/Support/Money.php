<?php

declare(strict_types=1);

namespace KitsuneNotes\Support;

/**
 * Utilidades monetarias.
 *
 * Todos los importes de la aplicación se manejan como enteros en
 * céntimos: nunca se opera con números en coma flotante para evitar
 * errores de redondeo acumulados en el cálculo de totales e impuestos.
 *
 * La tienda trabaja con una moneda base (el euro, en la que están los
 * precios del catálogo) y puede vender en otras aplicando un tipo de
 * cambio. El tipo también es un entero: se expresa en millonésimas
 * (850000 = 0,85 unidades de la otra moneda por cada euro).
 */
final class Money
{
    /** Moneda en la que se guardan los precios del catálogo. */
    public const BASE = 'EUR';

    /** Un tipo de cambio de 1 000 000 equivale a 1,000000. */
    public const RATE_UNIT = 1_000_000;

    /**
     * Símbolo de cada moneda y dónde se escribe: el euro va siempre detrás
     * del importe (12,90 €) y la libra siempre delante (£12.90).
     */
    private const CURRENCIES = [
        'EUR' => ['symbol' => '€', 'before' => false],
        'GBP' => ['symbol' => '£', 'before' => true],
    ];

    /** Separador decimal y de miles según el idioma de la página. */
    private const SEPARATORS = [
        'es' => [',', '.'],
        'en' => ['.', ','],
    ];

    /**
     * Importe listo para mostrar. La posición del símbolo depende de la
     * moneda; los separadores de decimales y miles, del idioma.
     */
    public static function format(int $cents, string $currency = self::BASE, string $locale = 'es'): string
    {
        [$decimal, $thousands] = self::SEPARATORS[$locale] ?? self::SEPARATORS['es'];

        $number = number_format(abs($cents) / 100, 2, $decimal, $thousands);
        $sign   = $cents < 0 ? '-' : '';
        $info   = self::CURRENCIES[$currency] ?? null;

        if ($info === null) {
            return $sign . $number . ' ' . $currency;
        }

        return $info['before']
            ? $sign . $info['symbol'] . $number
            : $sign . $number . ' ' . $info['symbol'];
    }

    public static function symbol(string $currency): string
    {
        return self::CURRENCIES[$currency]['symbol'] ?? $currency;
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

    /**
     * Convierte un importe de la moneda base a otra moneda. Redondea al
     * céntimo más cercano (las mitades, hacia arriba) usando solo enteros.
     */
    public static function convert(int $baseCents, int $rateMicros): int
    {
        return intdiv($baseCents * $rateMicros + intdiv(self::RATE_UNIT, 2), self::RATE_UNIT);
    }

    /** Operación inversa: contravalor en la moneda base de un importe en otra moneda. */
    public static function revert(int $cents, int $rateMicros): int
    {
        if ($rateMicros <= 0) {
            return $cents;
        }

        return intdiv($cents * self::RATE_UNIT + intdiv($rateMicros, 2), $rateMicros);
    }

    /**
     * Tipo de cambio escrito por una persona («0.85», «0,85») en millonésimas.
     * Un valor vacío o no válido devuelve 0 para que quien llama pueda rechazarlo.
     */
    public static function rateToMicros(string $rate): int
    {
        $rate = str_replace(',', '.', trim($rate));

        if (preg_match('/^\d{1,4}(\.\d{1,6})?$/', $rate) !== 1) {
            return 0;
        }

        [$units, $decimals] = array_pad(explode('.', $rate), 2, '');

        return (int) $units * self::RATE_UNIT + (int) str_pad($decimals, 6, '0');
    }

    /** Tipo de cambio en millonésimas como número decimal sin ceros de más («0.85»). */
    public static function rateToDecimal(int $rateMicros): string
    {
        $text = number_format($rateMicros / self::RATE_UNIT, 6, '.', '');
        $text = rtrim(rtrim($text, '0'), '.');

        return str_contains($text, '.') && strlen(explode('.', $text)[1]) >= 2
            ? $text
            : number_format((float) $text, 2, '.', '');
    }
}
