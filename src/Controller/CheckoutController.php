<?php

declare(strict_types=1);

namespace KitsuneNotes\Controller;

use KitsuneNotes\Core\Request;
use KitsuneNotes\Core\Response;
use KitsuneNotes\Service\EventRecorder;
use KitsuneNotes\Service\PaymentSimulator;
use KitsuneNotes\Support\Money;
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
            $this->flash('aviso', $this->t('Tu carrito está vacío: añade algún producto antes de continuar.'));

            return $this->redirect('/carrito');
        }

        $session        = $this->app->session();
        $pricing        = $this->app->pricing();
        $methods        = $pricing->shippingMethods();
        $shippingMethod = (string) $session->get('metodo_envio', 'estandar');
        $giftWrap       = (bool) $session->get('envoltorio', false);
        $summary        = $pricing->summary($items, $cart->couponCode(), $shippingMethod, $giftWrap);

        // El resumen de cada combinación de envío y envoltorio, ya calculado. La
        // página las trae todas y enseña la que corresponde a lo que el cliente
        // marca, de modo que el total cambia al momento sin botón, sin esperar al
        // servidor y sin que el navegador sume nada.
        $variants = [];

        foreach (array_keys($methods) as $method) {
            foreach ([false, true] as $wrap) {
                $variants[] = [
                    'method'    => (string) $method,
                    'gift_wrap' => $wrap,
                    'summary'   => $pricing->summary($items, $cart->couponCode(), (string) $method, $wrap),
                ];
            }
        }

        $this->recordCheckoutStarted($items, $summary);

        return $this->view('checkout/datos', [
            'title'            => $this->t('Datos de envío'),
            'items'            => $items,
            'summary'          => $summary,
            'summaryVariants'  => $variants,
            'shippingMethods'  => $methods,
            'giftwrapCents'    => $pricing->giftwrapCents(),
            'errors'           => $session->get('_checkout_errors', []),
            'old'              => $session->pullOldInput(),
        ]);
    }

    /** Paso 1 (envío del formulario). */
    public function submit(Request $request, array $args = []): Response
    {
        if (!$this->csrfValid($request)) {
            $this->flash('error', $this->t('La sesión ha caducado. Revisa los datos y vuelve a enviarlos.'));

            return $this->redirect('/checkout');
        }

        if ($this->app->cart()->isEmpty()) {
            return $this->redirect('/carrito');
        }

        // Las dos opciones que cambian el total se guardan siempre que sean
        // válidas, aunque el resto del formulario tenga errores: así el resumen
        // que se vuelve a pintar coincide con lo que el cliente ha marcado.
        $this->rememberOptions($request);

        $validator = $this->validate(
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
                'nombre'        => $this->t('Nombre y apellidos'),
                'email'         => $this->t('Correo electrónico'),
                'telefono'      => $this->t('Teléfono'),
                'direccion'     => $this->t('Dirección'),
                'codigo_postal' => $this->t('Código postal'),
                'ciudad'        => $this->t('Población'),
                'provincia'     => $this->t('Provincia'),
                'metodo_envio'  => $this->t('Método de envío'),
                'notas'         => $this->t('Notas para la entrega'),
                'condiciones'   => $this->t('las condiciones del prototipo'),
            ]
        );

        $session = $this->app->session();

        if ($validator->fails()) {
            $session->put('_checkout_errors', $validator->errors());
            $session->flashInput($request->all());
            $this->flash('error', $this->t('Revisa los campos marcados para poder continuar.'));

            return $this->redirect('/checkout');
        }

        $data = $validator->validated();
        $session->forget('_checkout_errors');
        $session->put('checkout', $data);

        return $this->redirect('/pago');
    }

    /**
     * Guarda en la sesión el método de envío (si es uno de los configurados)
     * y si se ha marcado el envoltorio de regalo.
     */
    private function rememberOptions(Request $request): void
    {
        $session = $this->app->session();
        $method  = (string) $request->input('metodo_envio', '');

        if (array_key_exists($method, $this->app->pricing()->shippingMethods())) {
            $session->put('metodo_envio', $method);
        }

        $session->put('envoltorio', $request->input('envoltorio') !== null);
    }

    /** Paso 2: pasarela de pago simulada. */
    public function payment(Request $request, array $args = []): Response
    {
        $session  = $this->app->session();
        $checkout = $session->get('checkout');
        $items    = $this->app->cart()->items();

        if (!is_array($checkout) || $items === []) {
            $this->flash('aviso', $this->t('Necesitamos tus datos de envío antes de pasar al pago.'));

            return $this->redirect($items === [] ? '/carrito' : '/checkout');
        }

        $summary = $this->app->pricing()->summary(
            $items,
            $this->app->cart()->couponCode(),
            (string) $session->get('metodo_envio', 'estandar'),
            (bool) $session->get('envoltorio', false)
        );

        return $this->view('checkout/pago', [
            'title'     => $this->t('Pago simulado'),
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
            $this->flash('error', $this->t('La sesión ha caducado. Vuelve a introducir los datos de pago.'));

            return $this->redirect('/pago');
        }

        $checkout = $session->get('checkout');
        $items    = $this->app->cart()->items();

        if (!is_array($checkout) || $items === []) {
            return $this->redirect('/carrito');
        }

        $validator = $this->validate(
            $request->all(),
            [
                'titular'        => 'requerido|min:3|max:80',
                'numero_tarjeta' => 'requerido|tarjeta',
                'caducidad'      => 'requerido|caducidad',
                'cvv'            => 'requerido|digitos|min:3|max:4',
            ],
            [
                'titular'        => $this->t('Titular de la tarjeta'),
                'numero_tarjeta' => $this->t('Número de tarjeta'),
                'caducidad'      => $this->t('Caducidad'),
                'cvv'            => $this->t('CVV'),
            ]
        );

        if ($validator->fails()) {
            $session->put('_payment_errors', $validator->errors());
            $this->flash('error', $this->t('Los datos de la tarjeta de prueba no son válidos.'));

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
        $order = $this->pendingOrder($summary);

        if ($order === null) {
            try {
                $order = $this->app->orderService()->createFromCart(
                    $items,
                    $summary,
                    $checkout,
                    $session->id()
                );
            } catch (RuntimeException $e) {
                error_log('[kitsune-notes] ' . $e->getMessage());
                $this->flash('error', $this->t('No se ha podido generar el pedido. Vuelve a intentarlo en unos minutos.'));

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
                'moneda'            => (string) $order['currency'],
                'importe_eur_cents' => (int) $order['total_base_cents'],
                'metodo'            => 'tarjeta',
                'marca_tarjeta'     => $result['card_brand'],
                'ultimos_4'         => $result['card_last4'],
                'proveedor'         => 'simulador-interno',
            ],
            ['order_id' => (int) $order['id'], 'customer_id' => (int) $order['customer_id']]
        );

        if (!$result['approved']) {
            // El motivo se guarda en español (es el dato del back-office) y se
            // traduce al mostrárselo al cliente.
            $this->flash('error', $this->t(
                'El pago simulado ha sido rechazado: {motivo} El pedido {referencia} queda pendiente de pago; puedes reintentarlo.',
                ['motivo' => $this->t((string) $result['decline_reason']), 'referencia' => $order['reference']]
            ));

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

    /**
     * Pedido creado y aún sin pagar que se puede reutilizar para reintentar
     * el cobro. Solo vale si sigue correspondiendo a lo que el cliente está
     * viendo: misma moneda y mismo total. Si entre un intento y otro ha
     * cambiado de idioma (y, con él, de moneda) o ha modificado el carrito,
     * el pedido pendiente se cancela y se genera uno nuevo, para no cobrar
     * nunca un importe distinto del que aparece en pantalla.
     *
     * @param array<string, mixed> $summary
     * @return array<string, mixed>|null
     */
    private function pendingOrder(array $summary): ?array
    {
        $session   = $this->app->session();
        $reference = $session->get('pedido_pendiente');

        if (!is_string($reference) || $reference === '') {
            return null;
        }

        $order = $this->app->orders()->findByReference($reference);

        if ($order === null || $order['status'] !== 'creado') {
            return null;
        }

        if ((string) $order['currency'] === (string) $summary['currency']
            && (int) $order['total_cents'] === (int) $summary['total_cents']) {
            return $order;
        }

        $changed = $this->app->orderService()->cancelUnpaid(
            $order,
            'El cliente cambió el carrito o la moneda antes de pagar: se sustituye por un pedido nuevo.'
        );

        if ($changed) {
            $this->app->events()->record(
                EventRecorder::ORDER_STATUS_CHANGED,
                [
                    'referencia'      => $order['reference'],
                    'estado_anterior' => 'creado',
                    'estado_nuevo'    => 'cancelado',
                    'origen'          => 'checkout',
                ],
                ['order_id' => (int) $order['id'], 'customer_id' => (int) $order['customer_id'], 'actor_type' => 'sistema']
            );
        }

        $session->forget('pedido_pendiente');

        return null;
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
                'moneda'            => (string) $summary['currency'],
                'total_estimado_eur_cents' => (int) $summary['total_base_cents'],
                'idioma'            => (string) $summary['locale'],
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
                        'precio_eur_cents' => (int) $item['product']['price_base_cents'],
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
                // Todos los importes anteriores están en «moneda». Para sumar
                // pedidos de monedas distintas se usa el contravalor en euros.
                'moneda'             => (string) $summary['currency'],
                'idioma'             => (string) $summary['locale'],
                'tipo_cambio'        => Money::rateToDecimal((int) $summary['fx_rate_micros']),
                'total_eur_cents'    => (int) $summary['total_base_cents'],
                'cupon'              => $summary['coupon_code'],
                'metodo_envio'       => $summary['shipping_method'],
                'provincia_envio'    => $order['shipping_province'],
            ],
            ['order_id' => (int) $order['id'], 'customer_id' => (int) $order['customer_id']]
        );
    }
}
