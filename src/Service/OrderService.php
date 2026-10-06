<?php

declare(strict_types=1);

namespace KitsuneNotes\Service;

use DateTimeImmutable;
use DateTimeZone;
use KitsuneNotes\Repository\CustomerRepository;
use KitsuneNotes\Repository\OrderRepository;
use KitsuneNotes\Repository\ProductRepository;
use PDO;
use RuntimeException;

/**
 * Creación y ciclo de vida del pedido.
 *
 * La conversión de carrito a pedido es una transacción: o se escriben
 * el pedido, sus líneas y el descuento de stock, o no se escribe nada.
 */
final class OrderService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly OrderRepository $orders,
        private readonly CustomerRepository $customers,
        private readonly ProductRepository $products,
    ) {
    }

    /**
     * Convierte el carrito en un pedido persistido con estado «creado».
     *
     * @param list<array{product: array<string, mixed>, quantity:int, line_total_cents:int}> $items
     * @param array<string, mixed> $summary  desglose devuelto por PricingService
     * @param array<string, mixed> $checkout datos validados del formulario
     *
     * @return array<string, mixed> el pedido recién creado
     */
    public function createFromCart(array $items, array $summary, array $checkout, string $sessionId): array
    {
        if ($items === []) {
            throw new RuntimeException('No se puede generar un pedido con el carrito vacío.');
        }

        $now = (new DateTimeImmutable('now', new DateTimeZone('Europe/Madrid')))->format(DATE_ATOM);

        $this->pdo->beginTransaction();

        try {
            $customerId = $this->customers->upsert([
                'email'        => $checkout['email'],
                'full_name'    => $checkout['nombre'],
                'phone'        => $checkout['telefono'] ?? '',
                'address_line' => $checkout['direccion'],
                'postal_code'  => $checkout['codigo_postal'],
                'city'         => $checkout['ciudad'],
                'province'     => $checkout['provincia'],
                'country'      => 'ES',
            ]);

            // Referencia provisional única; se sustituye por la definitiva
            // en cuanto conocemos el identificador autoincremental.
            $provisional = 'TMP-' . bin2hex(random_bytes(8));

            $orderId = $this->orders->insert([
                'reference'            => $provisional,
                'customer_id'          => $customerId,
                'status'               => 'creado',
                'currency'             => (string) $summary['currency'],
                'items_total_cents'    => (int) $summary['items_total_cents'],
                'discount_cents'       => (int) $summary['discount_cents'],
                'shipping_cents'       => (int) $summary['shipping_cents'],
                'giftwrap_cents'       => (int) $summary['giftwrap_cents'],
                'taxable_base_cents'   => (int) $summary['taxable_base_cents'],
                'tax_cents'            => (int) $summary['tax_cents'],
                'total_cents'          => (int) $summary['total_cents'],
                'coupon_code'          => $summary['coupon_code'],
                'shipping_method'      => (string) $summary['shipping_method'],
                'shipping_name'        => (string) $checkout['nombre'],
                'shipping_address'     => (string) $checkout['direccion'],
                'shipping_postal_code' => (string) $checkout['codigo_postal'],
                'shipping_city'        => (string) $checkout['ciudad'],
                'shipping_province'    => (string) $checkout['provincia'],
                'shipping_country'     => 'ES',
                'gift_wrap'            => $summary['gift_wrap'] ? 1 : 0,
                'customer_notes'       => (string) ($checkout['notas'] ?? ''),
                'session_id'           => $sessionId,
                'created_at'           => $now,
                'updated_at'           => $now,
            ]);

            $reference = sprintf('KN-%s-%06d', date('Y'), $orderId);
            $this->orders->updateReference($orderId, $reference);

            foreach ($items as $item) {
                $product = $item['product'];

                // Datos maestros congelados en la línea (snapshot): el
                // pedido histórico no cambia si el producto se edita.
                $this->orders->insertLine($orderId, [
                    'product_id'       => (int) $product['id'],
                    'sku'              => (string) $product['sku'],
                    'name'             => (string) $product['name'],
                    'design_line'      => (string) $product['design_line_name'],
                    'image_path'       => (string) $product['image_path'],
                    'unit_price_cents' => (int) $product['price_cents'],
                    'quantity'         => (int) $item['quantity'],
                    'tax_rate'         => (float) $product['tax_rate'],
                    'line_total_cents' => (int) $item['line_total_cents'],
                ]);

                $this->products->decreaseStock((int) $product['id'], (int) $item['quantity']);
            }

            $this->orders->addHistory($orderId, null, 'creado', 'sistema', 'Pedido generado desde el checkout.');

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();

            throw new RuntimeException('No se ha podido generar el pedido: ' . $e->getMessage(), previous: $e);
        }

        $order = $this->orders->findById($orderId);

        if ($order === null) {
            throw new RuntimeException('El pedido se ha creado pero no se ha podido recuperar.');
        }

        return $order;
    }

    /**
     * Transiciones admitidas del ciclo de vida del pedido.
     *
     * @return array<string, list<string>>
     */
    public static function allowedTransitions(): array
    {
        return [
            'creado'                => ['pagado_simulado', 'cancelado', 'incidencia'],
            'pagado_simulado'       => ['pendiente_preparacion', 'cancelado', 'incidencia'],
            'pendiente_preparacion' => ['enviado', 'cancelado', 'incidencia'],
            'enviado'               => ['entregado', 'incidencia'],
            'entregado'             => ['incidencia'],
            'incidencia'            => ['pendiente_preparacion', 'enviado', 'entregado', 'cancelado'],
            'cancelado'             => [],
        ];
    }

    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::allowedTransitions()[$from] ?? [], true);
    }

    /**
     * Cambia el estado de un pedido dejando traza en el histórico.
     *
     * @param array<string, mixed> $order
     */
    public function changeStatus(array $order, string $newStatus, string $changedBy, string $note = ''): bool
    {
        $current = (string) $order['status'];

        if ($current === $newStatus || !$this->canTransition($current, $newStatus)) {
            return false;
        }

        $this->orders->updateStatus((int) $order['id'], $newStatus);
        $this->orders->addHistory((int) $order['id'], $current, $newStatus, $changedBy, $note);

        return true;
    }

    /** Marca el pedido como pagado tras una autorización simulada. */
    public function markAsPaid(array $order, string $authorizationCode): void
    {
        $this->orders->updateStatus((int) $order['id'], 'pagado_simulado');
        $this->orders->addHistory(
            (int) $order['id'],
            (string) $order['status'],
            'pagado_simulado',
            'sistema',
            'Pago simulado autorizado con código ' . $authorizationCode . '.'
        );
    }
}
