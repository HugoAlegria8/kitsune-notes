<?php

declare(strict_types=1);

namespace KitsuneNotes\Service;

use KitsuneNotes\Core\Translator;
use KitsuneNotes\Support\Money;

/**
 * Monedas de la tienda y conversión entre ellas.
 *
 * Reglas de negocio:
 *  - Los precios del catálogo, los gastos de envío y los cupones se definen
 *    siempre en la moneda base (euros).
 *  - Cada idioma de la tienda compra en una moneda: en español, euros; en
 *    inglés, libras. La moneda activa es la del idioma activo.
 *  - El cambio se hace con un tipo FIJO de demostración que se lee de la
 *    configuración (KN_FX_EUR_GBP). No es un tipo oficial ni se actualiza.
 *  - Cuando se crea un pedido, el tipo aplicado se guarda en el propio
 *    pedido: cambiar la configuración no altera los pedidos ya hechos.
 */
final class CurrencyService
{
    /** Para avisar una sola vez por petición de un tipo de cambio mal configurado. */
    private bool $warned = false;

    /** @param array<string, mixed> $commerce sección «commerce» de la configuración */
    public function __construct(
        private readonly array $commerce,
        private readonly Translator $translator,
    ) {
    }

    /** Moneda base: en ella están los precios del catálogo. */
    public function base(): string
    {
        return (string) ($this->commerce['currency'] ?? Money::BASE);
    }

    /**
     * Moneda en la que compra el visitante: la del idioma activo. Si su tipo
     * de cambio no es válido (KN_FX_EUR_GBP no es un número, vale cero…), se vende
     * en la moneda base y se deja constancia en el registro de errores.
     */
    public function current(): string
    {
        $currency = $this->translator->info('currency');

        if ($currency === '' || $this->supports($currency)) {
            return $currency !== '' ? $currency : $this->base();
        }

        if (!$this->warned) {
            $this->warned = true;
            error_log(sprintf(
                '[kitsune-notes] El tipo de cambio de %s no es válido: la tienda vende en %s también en ese idioma.',
                $currency,
                $this->base()
            ));
        }

        return $this->base();
    }

    public function supports(string $currency): bool
    {
        return $currency === $this->base() || $this->rateMicros($currency) > 0;
    }

    /**
     * Unidades de $currency por cada unidad de la moneda base, en
     * millonésimas (850000 = 0,85). Devuelve 0 si la moneda no está
     * configurada o su tipo no es válido.
     */
    public function rateMicros(string $currency): int
    {
        if ($currency === $this->base()) {
            return Money::RATE_UNIT;
        }

        $rate = $this->commerce['currencies'][$currency]['rate'] ?? '';

        return Money::rateToMicros((string) $rate);
    }

    /** Convierte un importe de la moneda base a $currency (por defecto, la moneda activa). */
    public function fromBase(int $baseCents, ?string $currency = null): int
    {
        $currency ??= $this->current();

        if ($currency === $this->base()) {
            return $baseCents;
        }

        return Money::convert($baseCents, $this->rateMicros($currency));
    }

    /**
     * Contravalor en la moneda base. Para un pedido ya creado se pasa el tipo
     * que se guardó con él; si no, se usa el de la configuración.
     */
    public function toBase(int $cents, string $currency, ?int $rateMicros = null): int
    {
        if ($currency === $this->base()) {
            return $cents;
        }

        return Money::revert($cents, $rateMicros ?? $this->rateMicros($currency));
    }

    /**
     * Importe listo para mostrar. Sin moneda se usa la activa; los
     * separadores de decimales y miles son los del idioma activo.
     */
    public function format(int $cents, ?string $currency = null, ?string $locale = null): string
    {
        return Money::format(
            $cents,
            $currency !== null && $currency !== '' ? $currency : $this->current(),
            $locale ?? $this->translator->locale()
        );
    }

    /** Texto del tipo de cambio para facturas y back-office: «1 € = £0.85». */
    public function rateLabel(string $currency, ?int $rateMicros = null): string
    {
        $rateMicros ??= $this->rateMicros($currency);
        $decimal = Money::rateToDecimal($rateMicros);

        if ($this->translator->locale() !== 'en') {
            $decimal = str_replace('.', ',', $decimal);
        }

        $base  = Money::symbol($this->base());
        $other = Money::symbol($currency);

        return $currency === 'GBP'
            ? sprintf('1 %s = %s%s', $base, $other, $decimal)
            : sprintf('1 %s = %s %s', $base, $decimal, $other);
    }
}
