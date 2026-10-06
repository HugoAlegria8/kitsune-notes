<?php

declare(strict_types=1);

namespace KitsuneNotes\Service;

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
 */
final class PricingService
{
    /** @param array<string, mixed> $commerce */
    public function __construct(
        private readonly array $commerce,
        private readonly CouponRepository $coupons,
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
        $itemsTotal = array_sum(array_column($items, 'line_total_cents'));
        $unitCount  = array_sum(array_column($items, 'quantity'));

        // --- Descuento -------------------------------------------------
        $discount    = 0;
        $coupon      = null;
        $couponError = null;

        if ($couponCode !== null && $couponCode !== '') {
            $coupon = $this->coupons->findActive($couponCode);

            if ($coupon === null) {
                $couponError = 'El código de descuento no existe o ha caducado.';
            } elseif ($itemsTotal < (int) $coupon['min_items_total_cents']) {
                $couponError = sprintf(
                    'El código %s requiere un importe mínimo de %s en artículos.',
                    $coupon['code'],
                    Money::format((int) $coupon['min_items_total_cents'])
                );
                $coupon = null;
            } else {
                $discount = $coupon['type'] === 'percent'
                    ? (int) round($itemsTotal * ((int) $coupon['value'] / 100))
                    : (int) $coupon['value'];

                $discount = min($discount, $itemsTotal);
            }
        }

        $afterDiscount = $itemsTotal - $discount;

        // --- Envío -----------------------------------------------------
        $methods = (array) ($this->commerce['shipping'] ?? []);
        $method  = $methods[$shippingMethod] ?? $methods['estandar'];
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
        $giftwrapCents = $giftWrap && $items !== [] ? (int) ($this->commerce['giftwrap_cents'] ?? 0) : 0;

        // --- Totales e impuestos ---------------------------------------
        $total = $afterDiscount + $shipping + $giftwrapCents;
        $split = Money::splitTax($total, $taxRate);

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
            'currency'                => (string) ($this->commerce['currency'] ?? 'EUR'),
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public function shippingMethods(): array
    {
        return (array) ($this->commerce['shipping'] ?? []);
    }

    public function giftwrapCents(): int
    {
        return (int) ($this->commerce['giftwrap_cents'] ?? 0);
    }

    /** @return list<array<string, mixed>> */
    public function activeCoupons(): array
    {
        return $this->coupons->active();
    }
}
