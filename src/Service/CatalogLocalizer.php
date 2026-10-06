<?php

declare(strict_types=1);

namespace KitsuneNotes\Service;

use KitsuneNotes\Core\Translator;

/**
 * Adapta los datos maestros del catálogo al idioma y a la moneda activos.
 *
 * Los repositorios leen las filas tal como están en la base de datos (textos
 * en español, precios en euros) y las pasan por aquí antes de devolverlas:
 *
 *  - Textos: si el idioma activo no es el original y la fila tiene su
 *    traducción (columnas terminadas en «_en»), la traducción sustituye al
 *    texto. Una traducción vacía deja el texto en español: es preferible un
 *    producto en español a un producto sin nombre.
 *  - Precios: se convierten a la moneda activa. El precio original en euros
 *    se conserva en «price_base_cents».
 *
 * Al hacerse en un único punto, el resto de la aplicación (tarjetas de
 * producto, carrito, motor de precios, pedido) no necesita saber en qué
 * idioma ni en qué moneda está comprando el cliente. En el back-office el
 * idioma siempre es español y la moneda el euro, así que las filas no cambian.
 */
final class CatalogLocalizer
{
    private const PRODUCT_TEXTS = ['name', 'summary', 'description', 'origin', 'specs_json'];

    /** Textos de la categoría y de la colección que llegan unidos a cada producto. */
    private const PRODUCT_JOINED_TEXTS = ['category_name', 'design_line_mascot'];

    private const CATEGORY_TEXTS = ['name', 'tagline', 'description'];

    /** El nombre de la colección es un nombre propio (Kitsune, Neko…): no se traduce. */
    private const DESIGN_LINE_TEXTS = ['mascot', 'tagline', 'description'];

    public function __construct(
        private readonly Translator $translator,
        private readonly CurrencyService $currency,
    ) {
    }

    /** ¿Hay que sustituir los textos por su traducción? */
    public function translating(): bool
    {
        return !$this->translator->isDefault();
    }

    /** Sufijo de las columnas traducidas para el idioma activo («_en»). */
    public function suffix(): string
    {
        return '_' . $this->translator->locale();
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function product(array $row): array
    {
        // Una fila ya adaptada no se vuelve a convertir.
        if (array_key_exists('price_base_cents', $row)) {
            return $row;
        }

        $row['price_base_cents']      = (int) $row['price_cents'];
        $row['compare_at_base_cents'] = $row['compare_at_cents'] !== null ? (int) $row['compare_at_cents'] : null;
        $row['currency']              = $this->currency->current();

        if ($row['currency'] !== $this->currency->base()) {
            $row['price_cents'] = $this->currency->fromBase($row['price_base_cents']);

            if ($row['compare_at_base_cents'] !== null) {
                $row['compare_at_cents'] = $this->currency->fromBase($row['compare_at_base_cents']);
            }
        }

        return $this->translate($row, [...self::PRODUCT_TEXTS, ...self::PRODUCT_JOINED_TEXTS]);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public function products(array $rows): array
    {
        return array_map(fn (array $row): array => $this->product($row), $rows);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function category(array $row): array
    {
        return $this->translate($row, self::CATEGORY_TEXTS);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function designLine(array $row): array
    {
        return $this->translate($row, self::DESIGN_LINE_TEXTS);
    }

    /**
     * @param array<string, mixed> $row
     * @param list<string>         $fields
     * @return array<string, mixed>
     */
    private function translate(array $row, array $fields): array
    {
        if (!$this->translating()) {
            return $row;
        }

        $suffix = $this->suffix();

        foreach ($fields as $field) {
            $translated = $row[$field . $suffix] ?? null;

            if (is_string($translated) && trim($translated) !== '') {
                $row[$field] = $translated;
            }
        }

        return $row;
    }
}
