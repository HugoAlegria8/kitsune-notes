<?php

declare(strict_types=1);

namespace KitsuneNotes\Service;

use KitsuneNotes\Core\Translator;
use KitsuneNotes\Repository\CouponRepository;
use KitsuneNotes\Support\Money;

/**
 * Reglas de negocio económicas del canal de venta.
 *
 * Criterio fiscal aplicado (venta B2C en España): los precios de
 * catálogo se muestran y se almacenan con el IVA incluido, tal y como
 * exige la normativa de protección al consumidor. La base imponible y
 * la cuota de IVA se obtienen por desglose del total y se persisten en
 * el pedido para que la factura sea reproducible aunque el tipo
 * impositivo cambie en el futuro.
 *
 * Moneda: todo lo que se configura (gastos de envío, umbral de envío
 * gratis, envoltorio, cupones) está en la moneda base. Cuando el cliente
 * compra en otra moneda, esos importes se convierten primero y TODO el
 * cálculo se hace ya en la moneda de la compra, de modo que las líneas, los
 * gastos y el IVA suman exactamente el total que se cobra y se factura.
 * Los artículos llegan ya convertidos (véase CatalogLocalizer).
 */
final class PricingService
{
    /** @param array<string, mixed> $commerce */
    public function __construct(
        private readonly array $commerce,
        private readonly CouponRepository $coupons,
        private readonly CurrencyService $currency,
        private readonly Translator $translator,
    ) {
    }

    /**
     * Calcula el desglose económico completo de un carrito.
     *
     * @param list<array{product: array<string, mixed>, quantity:int, line_total_cents:int}> $items
     *
     * @return array<string, mixed>
     */
    public function summary(
        array $items,
        ?string $couponCode = null,
        string $shippingMethod = 'estandar',
        bool $giftWrap = false,
    ): array {
        $taxRate    = (float) ($this->commerce['tax_rate'] ?? 0.21);
        $currency   = $this->currency->current();
        $itemsTotal = array_sum(array_column($items, 'line_total_cents'));
        $unitCount  = array_sum(array_column($items, 'quantity'));

        // --- Descuento -------------------------------------------------
        $discount    = 0;
        $coupon      = null;
        $couponError = null;

        if ($couponCode !== null && $couponCode !== '') {
            $coupon  = $this->coupons->findActive($couponCode);
            $minimum = $coupon !== null ? $this->currency->fromBase((int) $coupon['min_items_total_cents']) : 0;

            if ($coupon === null) {
                $couponError = $this->translator->get('El código de descuento no existe o ha caducado.');
            } elseif ($itemsTotal < $minimum) {
                $couponError = $this->translator->get(
                    'El código {codigo} requiere un importe mínimo de {importe} en artículos.',
                    ['codigo' => $coupon['code'], 'importe' => $this->currency->format($minimum)]
                );
                $coupon = null;
            } else {
                $discount = $coupon['type'] === 'percent'
                    ? (int) round($itemsTotal * ((int) $coupon['value'] / 100))
                    : $this->currency->fromBase((int) $coupon['value']);

                $discount = min($discount, $itemsTotal);
            }
        }

        $afterDiscount = $itemsTotal - $discount;

        // --- Envío -----------------------------------------------------
        $methods  = $this->shippingMethods();
        $method   = $methods[$shippingMethod] ?? $methods['estandar'];
        $freeFrom = $method['free_from_cents'] ?? null;

        $shipping = 0;
        if ($items !== []) {
            $shipping = ($freeFrom !== null && $afterDiscount >= (int) $freeFrom)
                ? 0
                : (int) $method['price_cents'];
        }

        $freeShippingRemaining = null;
        if ($items !== [] && $freeFrom !== null && $afterDiscount < (int) $freeFrom) {
            $freeShippingRemaining = (int) $freeFrom - $afterDiscount;
        }

        // --- Envoltorio opcional ---------------------------------------
        $giftwrapCents = $giftWrap && $items !== [] ? $this->giftwrapCents() : 0;

        // --- Totales e impuestos ---------------------------------------
        $total = $afterDiscount + $shipping + $giftwrapCents;
        $split = Money::splitTax($total, $taxRate);
        $rate  = $this->currency->rateMicros($currency);

        return [
            'unit_count'              => $unitCount,
            'items_total_cents'       => $itemsTotal,
            'discount_cents'          => $discount,
            'coupon'                  => $coupon,
            'coupon_code'             => $coupon['code'] ?? null,
            'coupon_error'            => $couponError,
            'shipping_method'         => $shippingMethod,
            'shipping_label'          => (string) $method['label'],
            'shipping_description'    => (string) $method['description'],
            'shipping_cents'          => $shipping,
            'free_shipping_remaining' => $freeShippingRemaining,
            'gift_wrap'               => $giftWrap,
            'giftwrap_cents'          => $giftwrapCents,
            'taxable_base_cents'      => $split['base'],
            'tax_cents'               => $split['tax'],
            'tax_rate'                => $taxRate,
            'total_cents'             => $total,
            // Moneda de la compra, tipo de cambio aplicado y contravalor del
            // total en la moneda base: se guardan con el pedido.
            'currency'                => $currency,
            'base_currency'           => $this->currency->base(),
            'fx_rate_micros'          => $rate,
            'total_base_cents'        => $this->currency->toBase($total, $currency, $rate),
            'locale'                  => $this->translator->locale(),
        ];
    }

    /**
     * Métodos de envío tal como se ofrecen al cliente: nombres en el idioma
     * activo e importes en la moneda activa.
     *
     * @return array<string, array<string, mixed>>
     */
    public function shippingMethods(): array
    {
        $methods = [];

        foreach ((array) ($this->commerce['shipping'] ?? []) as $key => $method) {
            $freeFrom = $method['free_from_cents'] ?? null;

            $methods[(string) $key] = [
                'label'           => $this->translator->get((string) $method['label']),
                'description'     => $this->translator->get((string) $method['description']),
                'price_cents'     => $this->currency->fromBase((int) $method['price_cents']),
                'free_from_cents' => $freeFrom !== null ? $this->currency->fromBase((int) $freeFrom) : null,
            ];
        }

        return $methods;
    }

    /** Precio del envoltorio de regalo en la moneda activa. */
    public function giftwrapCents(): int
    {
        return $this->currency->fromBase((int) ($this->commerce['giftwrap_cents'] ?? 0));
    }

    /**
     * Cupones vigentes, listos para mostrarse: el importe del descuento y el
     * mínimo de compra van en la moneda activa. En español se conserva la
     * descripción escrita en la base de datos; en otro idioma se redacta a
     * partir de los datos del cupón, porque el texto guardado nombra euros.
     *
     * @return list<array<string, mixed>>
     */
    public function activeCoupons(): array
    {
        return array_map(function (array $coupon): array {
            $minimum = $this->currency->fromBase((int) $coupon['min_items_total_cents']);

            $coupon['display_min_cents']   = $minimum;
            $coupon['display_value_cents'] = $coupon['type'] === 'fixed'
                ? $this->currency->fromBase((int) $coupon['value'])
                : null;

            if (!$this->translator->isDefault()) {
                $coupon['description'] = $this->describeCoupon($coupon, $minimum);
            }

            return $coupon;
        }, $this->coupons->active());
    }

    /** @param array<string, mixed> $coupon */
    private function describeCoupon(array $coupon, int $minimum): string
    {
        // Solo se llama fuera del español: el porcentaje va sin espacio («10%»).
        $amount = $coupon['type'] === 'percent'
            ? (int) $coupon['value'] . '%'
            : $this->currency->format((int) $coupon['display_value_cents']);

        if ($minimum > 0) {
            return $this->translator->get(
                '{descuento} de descuento en pedidos de más de {minimo} en artículos.',
                ['descuento' => $amount, 'minimo' => $this->currency->format($minimum)]
            );
        }

        return $this->translator->get(
            '{descuento} de descuento sobre el importe de los artículos.',
            ['descuento' => $amount]
        );
    }
}
