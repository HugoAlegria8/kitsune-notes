<?php

declare(strict_types=1);

namespace KitsuneNotes\Service;

use KitsuneNotes\Core\Session;
use KitsuneNotes\Repository\ProductRepository;

/**
 * Carrito de la compra.
 *
 * Decisión de diseño: en la sesión solo se guardan identificadores de
 * producto y cantidades. El precio, el stock y el nombre se releen
 * siempre de la base de datos al pintar el carrito, de modo que el
 * cliente no puede manipular importes desde el navegador.
 */
final class CartService
{
    private const SESSION_KEY = 'carrito';

    public function __construct(
        private readonly Session $session,
        private readonly ProductRepository $products,
        private readonly int $maxUnitsPerLine = 10,
    ) {
    }

    /** @return array{items: array<int, int>, coupon: ?string} */
    private function state(): array
    {
        $state = $this->session->get(self::SESSION_KEY, ['items' => [], 'coupon' => null]);

        return [
            'items'  => is_array($state['items'] ?? null) ? $state['items'] : [],
            'coupon' => isset($state['coupon']) && is_string($state['coupon']) ? $state['coupon'] : null,
        ];
    }

    /** @param array{items: array<int, int>, coupon: ?string} $state */
    private function save(array $state): void
    {
        $this->session->put(self::SESSION_KEY, $state);
    }

    /**
     * Añade unidades al carrito respetando el stock disponible.
     *
     * @return array{added:int, quantity:int, limited:bool}
     */
    public function add(int $productId, int $quantity = 1): array
    {
        $product = $this->products->findById($productId);

        if ($product === null || (int) $product['is_active'] !== 1) {
            return ['added' => 0, 'quantity' => 0, 'limited' => false];
        }

        $state   = $this->state();
        $current = (int) ($state['items'][$productId] ?? 0);
        $maximum = min($this->maxUnitsPerLine, (int) $product['stock']);
        $desired = $current + max(1, $quantity);
        $final   = max(0, min($desired, $maximum));

        $state['items'][$productId] = $final;
        $this->save($state);

        return [
            'added'    => $final - $current,
            'quantity' => $final,
            'limited'  => $desired > $maximum,
        ];
    }

    public function update(int $productId, int $quantity): void
    {
        $state = $this->state();

        if ($quantity <= 0) {
            unset($state['items'][$productId]);
            $this->save($state);

            return;
        }

        $product = $this->products->findById($productId);

        if ($product === null) {
            return;
        }

        $state['items'][$productId] = max(1, min($quantity, $this->maxUnitsPerLine, (int) $product['stock']));
        $this->save($state);
    }

    public function remove(int $productId): void
    {
        $state = $this->state();
        unset($state['items'][$productId]);
        $this->save($state);
    }

    public function clear(): void
    {
        $this->session->forget(self::SESSION_KEY);
    }

    /**
     * Líneas del carrito con los datos actuales del producto.
     *
     * @return list<array{product: array<string, mixed>, quantity:int, line_total_cents:int}>
     */
    public function items(): array
    {
        $state = $this->state();

        if ($state['items'] === []) {
            return [];
        }

        $products = $this->products->findManyByIds(array_keys($state['items']));
        $items    = [];

        foreach ($state['items'] as $productId => $quantity) {
            $product = $products[(int) $productId] ?? null;

            if ($product === null || (int) $product['is_active'] !== 1) {
                continue; // el producto ya no está disponible: se descarta
            }

            $quantity = max(1, min((int) $quantity, $this->maxUnitsPerLine));

            $items[] = [
                'product'          => $product,
                'quantity'         => $quantity,
                'line_total_cents' => (int) $product['price_cents'] * $quantity,
            ];
        }

        return $items;
    }

    public function isEmpty(): bool
    {
        return $this->items() === [];
    }

    /** Número total de unidades (para el contador de la cabecera). */
    public function unitCount(): int
    {
        $state = $this->state();

        return array_sum(array_map('intval', $state['items']));
    }

    public function couponCode(): ?string
    {
        return $this->state()['coupon'];
    }

    public function setCoupon(?string $code): void
    {
        $state           = $this->state();
        $state['coupon'] = $code === null || $code === '' ? null : strtoupper($code);
        $this->save($state);
    }

    public function maxUnitsPerLine(): int
    {
        return $this->maxUnitsPerLine;
    }
}
