<?php

declare(strict_types=1);

namespace KitsuneNotes\Controller;

use KitsuneNotes\Core\Request;
use KitsuneNotes\Core\Response;
use KitsuneNotes\Core\Validator;
use KitsuneNotes\Service\EventRecorder;
use KitsuneNotes\Service\PaymentSimulator;
use RuntimeException;
use Throwable;

/**
 * Proceso de formalización del pedido: datos de envío, pago simulado y
 * generación del pedido con su referencia única.
 */
final class CheckoutController extends Controller
{
    /** Paso 1: datos de envío y condiciones comerciales. */
    public function index(Request $request, array $args = []): Response
    {
        $cart  = $this->app->cart();
        $items = $cart->items();

        if ($items === []) {
            $this->flash('aviso', 'Tu carrito está vacío: añade algún producto antes de continuar.');

            return $this->redirect('/carrito');
        }

        $session        = $this->app->session();
        $shippingMethod = (string) $session->get('metodo_envio', 'estandar');
        $giftWrap       = (bool) $session->get('envoltorio', false);
        $summary        = $this->app->pricing()->summary($items, $cart->couponCode(), $shippingMethod, $giftWrap);

        $this->recordCheckoutStarted($items, $summary);

        return $this->view('checkout/datos', [
            'title'            => 'Datos de envío',
            'items'            => $items,
            'summary'          => $summary,
            'shippingMethods'  => $this->app->pricing()->shippingMethods(),
            'giftwrapCents'    => $this->app->pricing()->giftwrapCents(),
            'errors'           => $session->get('_checkout_errors', []),
            'old'              => $session->pullOldInput(),
        ]);
    }

    /** Paso 1 (envío del formulario). */
    public function submit(Request $request, array $args = []): Response
    {
        if (!$this->csrfValid($request)) {
            $this->flash('error', 'La sesión ha caducado. Revisa los datos y vuelve a enviarlos.');

            return $this->redirect('/checkout');
        }

        if ($this->app->cart()->isEmpty()) {
            return $this->redirect('/carrito');
        }

        $validator = new Validator(
            $request->all(),
            [
                'nombre'        => 'requerido|min:3|max:80',
                'email'         => 'requerido|email|max:120',
                'telefono'      => 'requerido|telefono',
                'direccion'     => 'requerido|min:5|max:150',
                'codigo_postal' => 'requerido|cp',
                'ciudad'        => 'requerido|max:80',
                'provincia'     => 'requerido|max:80',
                'metodo_envio'  => 'requerido|en:estandar,express',
                'notas'         => 'max:400',
                'condiciones'   => 'aceptado',
            ],
            [
                'nombre'        => 'Nombre y apellidos',
                'email'         => 'Correo electrónico',
                'telefono'      => 'Teléfono',
                'direccion'     => 'Dirección',
                'codigo_postal' => 'Código postal',
                'ciudad'        => 'Población',
                'provincia'     => 'Provincia',
                'metodo_envio'  => 'Método de envío',
                'notas'         => 'Notas para la entrega',
                'condiciones'   => 'las condiciones del prototipo',
            ]
        );

        $session = $this->app->session();

        if ($validator->fails()) {
            $session->put('_checkout_errors', $validator->errors());
            $session->flashInput($request->all());
            $this->flash('error', 'Revisa los campos marcados para poder continuar.');

            return $this->redirect('/checkout');
        }

        $data = $validator->validated();
        $session->forget('_checkout_errors');
        $session->put('metodo_envio', $data['metodo_envio']);
        $session->put('envoltorio', $request->input('envoltorio') !== null);
        $session->put('checkout', $data);

        return $this->redirect('/pago');
    }

    /** Paso 2: pasarela de pago simulada. */
    public function payment(Request $request, array $args = []): Response
    {
        $session  = $this->app->session();
        $checkout = $session->get('checkout');
        $items    = $this->app->cart()->items();

        if (!is_array($checkout) || $items === []) {
            $this->flash('aviso', 'Necesitamos tus datos de envío antes de pasar al pago.');

            return $this->redirect($items === [] ? '/carrito' : '/checkout');
        }

        $summary = $this->app->pricing()->summary(
            $items,
            $this->app->cart()->couponCode(),
            (string) $session->get('metodo_envio', 'estandar'),
            (bool) $session->get('envoltorio', false)
        );

        return $this->view('checkout/pago', [
            'title'     => 'Pago simulado',
            'items'     => $items,
            'summary'   => $summary,
            'checkout'  => $checkout,
            'testCards' => PaymentSimulator::testCards(),
            'errors'    => $session->get('_payment_errors', []),
        ]);
    }

    /** Paso 2 (envío del formulario de pago): genera el pedido y lo cobra. */
    public function processPayment(Request $request, array $args = []): Response
    {
        $session = $this->app->session();

        if (!$this->csrfValid($request)) {
            $this->flash('error', 'La sesión ha caducado. Vuelve a introducir los datos de pago.');

            return $this->redirect('/pago');
        }

        $checkout = $session->get('checkout');
        $items    = $this->app->cart()->items();

        if (!is_array($checkout) || $items === []) {
            return $this->redirect('/carrito');
        }

        $validator = new Validator(
            $request->all(),
            [
                'titular'        => 'requerido|min:3|max:80',
                'numero_tarjeta' => 'requerido|tarjeta',
                'caducidad'      => 'requerido|caducidad',
                'cvv'            => 'requerido|digitos|min:3|max:4',
            ],
            [
                'titular'        => 'Titular de la tarjeta',
                'numero_tarjeta' => 'Número de tarjeta',
                'caducidad'      => 'Caducidad',
                'cvv'            => 'CVV',
            ]
        );

        if ($validator->fails()) {
            $session->put('_payment_errors', $validator->errors());
            $this->flash('error', 'Los datos de la tarjeta de prueba no son válidos.');

            return $this->redirect('/pago');
        }

        $session->forget('_payment_errors');

        $summary = $this->app->pricing()->summary(
            $items,
            $this->app->cart()->couponCode(),
            (string) $session->get('metodo_envio', 'estandar'),
            (bool) $session->get('envoltorio', false)
        );

        // --- Pedido: se reutiliza el pendiente si el pago falló antes ---
        $order = $this->pendingOrder();

        if ($order === null) {
            try {
                $order = $this->app->orderService()->createFromCart(
                    $items,
                    $summary,
                    $checkout,
                    $session->id()
                );
            } catch (RuntimeException $e) {
                $this->flash('error', $e->getMessage());

                return $this->redirect('/pago');
            }

            $session->put('pedido_pendiente', $order['reference']);
            $this->recordOrderCreated($order, $items, $summary);
        }

        // --- Cobro simulado --------------------------------------------
        $result = $this->app->paymentSimulator()->charge($order, [
            'numero_tarjeta' => $request->input('numero_tarjeta', ''),
            'metodo'         => 'tarjeta',
        ]);

        $this->app->events()->record(
            EventRecorder::PAYMENT_SIMULATED,
            [
                'referencia_pedido' => $order['reference'],
                'referencia_pago'   => $result['reference'],
                'resultado'         => $result['status'],
                'motivo_rechazo'    => $result['decline_reason'],
                'importe_cents'     => $result['amount_cents'],
                'moneda'            => 'EUR',
                'metodo'            => 'tarjeta',
                'marca_tarjeta'     => $result['card_brand'],
                'ultimos_4'         => $result['card_last4'],
                'proveedor'         => 'simulador-interno',
            ],
            ['order_id' => (int) $order['id'], 'customer_id' => (int) $order['customer_id']]
        );

        if (!$result['approved']) {
            $this->flash(
                'error',
                'El pago simulado ha sido rechazado: ' . $result['decline_reason']
                . ' El pedido ' . $order['reference'] . ' queda pendiente de pago; puedes reintentarlo.'
            );

            return $this->redirect('/pago');
        }

        // --- Pago autorizado --------------------------------------------
        $this->app->orderService()->markAsPaid($order, (string) $result['authorization_code']);
        $this->issueInvoiceAndNotify((int) $order['id']);

        $own   = $session->get('pedidos_propios', []);
        $own[] = $order['reference'];
        $session->put('pedidos_propios', array_values(array_unique($own)));

        $this->app->cart()->clear();
        $session->forget('checkout');
        $session->forget('pedido_pendiente');
        $session->forget('envoltorio');
        $session->forget('metodo_envio');

        return $this->redirect('/pedido/' . $order['reference']);
    }

    // -----------------------------------------------------------------

    /**
     * Postventa automática: con el pago ya confirmado se expide la factura
     * y se envía la confirmación al cliente. El pago ya está registrado, así
     * que un fallo aquí no debe romper la compra: queda en el registro de
     * errores y el personal puede expedir la factura desde el back-office.
     */
    private function issueInvoiceAndNotify(int $orderId): void
    {
        try {
            $order = $this->app->orders()->findById($orderId);

            if ($order === null) {
                return;
            }

            $invoice = $this->app->invoices()->issue($order);
            $this->app->notifier()->orderConfirmed($order, $invoice);
        } catch (Throwable $e) {
            error_log(sprintf(
                '[kitsune-notes] Pedido %d pagado, pero no se pudo facturar o notificar: %s',
                $orderId,
                $e->getMessage()
            ));
        }
    }

    /** @return array<string, mixed>|null pedido creado y aún sin pagar */
    private function pendingOrder(): ?array
    {
        $reference = $this->app->session()->get('pedido_pendiente');

        if (!is_string($reference) || $reference === '') {
            return null;
        }

        $order = $this->app->orders()->findByReference($reference);

        return $order !== null && $order['status'] === 'creado' ? $order : null;
    }

    /**
     * Emite checkout.started una sola vez por composición de carrito,
     * para no inflar el almacén de eventos al recargar la página.
     *
     * @param list<array<string, mixed>> $items
     * @param array<string, mixed>       $summary
     */
    private function recordCheckoutStarted(array $items, array $summary): void
    {
        $signature = md5(json_encode(array_map(
            static fn (array $item): string => $item['product']['sku'] . 'x' . $item['quantity'],
            $items
        )) ?: '');

        $session = $this->app->session();

        if ($session->get('_checkout_signature') === $signature) {
            return;
        }

        $session->put('_checkout_signature', $signature);

        $this->app->events()->record(
            EventRecorder::CHECKOUT_STARTED,
            [
                'lineas'            => count($items),
                'unidades'          => (int) $summary['unit_count'],
                'importe_articulos' => (int) $summary['items_total_cents'],
                'total_estimado'    => (int) $summary['total_cents'],
                'cupon'             => $summary['coupon_code'],
                'metodo_envio'      => $summary['shipping_method'],
                'skus'              => array_map(
                    static fn (array $item): string => (string) $item['product']['sku'],
                    $items
                ),
            ]
        );
    }

    /**
     * @param array<string, mixed>       $order
     * @param list<array<string, mixed>> $items
     * @param array<string, mixed>       $summary
     */
    private function recordOrderCreated(array $order, array $items, array $summary): void
    {
        $this->app->events()->record(
            EventRecorder::ORDER_CREATED,
            [
                'referencia'         => $order['reference'],
                'estado'             => $order['status'],
                'lineas'             => array_map(
                    static fn (array $item): array => [
                        'sku'          => (string) $item['product']['sku'],
                        'nombre'       => (string) $item['product']['name'],
                        'cantidad'     => (int) $item['quantity'],
                        'precio_cents' => (int) $item['product']['price_cents'],
                    ],
                    $items
                ),
                'unidades'           => (int) $summary['unit_count'],
                'importe_articulos'  => (int) $summary['items_total_cents'],
                'descuento_cents'    => (int) $summary['discount_cents'],
                'envio_cents'        => (int) $summary['shipping_cents'],
                'envoltorio_cents'   => (int) $summary['giftwrap_cents'],
                'base_imponible'     => (int) $summary['taxable_base_cents'],
                'iva_cents'          => (int) $summary['tax_cents'],
                'total_cents'        => (int) $summary['total_cents'],
                'moneda'             => 'EUR',
                'cupon'              => $summary['coupon_code'],
                'metodo_envio'       => $summary['shipping_method'],
                'provincia_envio'    => $order['shipping_province'],
            ],
            ['order_id' => (int) $order['id'], 'customer_id' => (int) $order['customer_id']]
        );
    }
}
