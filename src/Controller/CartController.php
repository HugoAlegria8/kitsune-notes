<?php

declare(strict_types=1);

namespace KitsuneNotes\Controller;

use KitsuneNotes\Core\Request;
use KitsuneNotes\Core\Response;
use KitsuneNotes\Service\EventRecorder;

final class CartController extends Controller
{
    /** @param array<string, string> $args */
    public function index(Request $request, array $args = []): Response
    {
        $cart    = $this->app->cart();
        $items   = $cart->items();
        $summary = $this->app->pricing()->summary(
            $items,
            $cart->couponCode(),
            (string) ($this->app->session()->get('metodo_envio', 'estandar')),
            (bool) $this->app->session()->get('envoltorio', false)
        );

        return $this->view('cart/index', [
            'title'    => $this->t('Tu carrito'),
            'items'    => $items,
            'summary'  => $summary,
            'coupons'  => $this->app->pricing()->activeCoupons(),
            'maxUnits' => $cart->maxUnitsPerLine(),
        ]);
    }

    /** @param array<string, string> $args */
    public function add(Request $request, array $args = []): Response
    {
        if (!$this->csrfValid($request)) {
            $this->flash('error', $this->t('La sesión ha caducado. Vuelve a intentarlo.'));

            return $this->back($request, '/carrito');
        }

        $productId = $request->intInput('producto_id');
        $quantity  = max(1, $request->intInput('cantidad', 1));
        $product   = $this->app->products()->findById($productId);

        if ($product === null) {
            $this->flash('error', $this->t('El producto seleccionado no está disponible.'));

            return $this->back($request, '/catalogo');
        }

        $result = $this->app->cart()->add($productId, $quantity);

        if ($result['added'] <= 0) {
            $this->flash('error', $this->t('No queda stock disponible de «{producto}».', ['producto' => $product['name']]));

            return $this->back($request, '/catalogo');
        }

        // ---- Instrumentación: cart.item_added -------------------------
        $this->app->events()->record(
            EventRecorder::CART_ITEM_ADDED,
            [
                'sku'                  => $product['sku'],
                'nombre'               => $product['name'],
                'categoria'            => $product['category_slug'],
                'coleccion'            => $product['design_line_slug'],
                'cantidad_anadida'     => $result['added'],
                'cantidad_en_carrito'  => $result['quantity'],
                // Precio en la moneda en que compra el cliente y su original en euros.
                'precio_unitario_cents'=> (int) $product['price_cents'],
                'moneda'               => (string) $product['currency'],
                'precio_unitario_eur_cents' => (int) $product['price_base_cents'],
                'idioma'               => $this->app->translator()->locale(),
                'unidades_carrito'     => $this->app->cart()->unitCount(),
            ],
            ['product_id' => (int) $product['id']]
        );

        if ($result['limited']) {
            $this->flash('aviso', $this->t('Hemos ajustado la cantidad al máximo disponible de «{producto}».', ['producto' => $product['name']]));
        } else {
            $this->flash('exito', $this->t('«{producto}» se ha añadido a tu carrito.', ['producto' => $product['name']]));
        }

        return $this->redirect('/carrito');
    }

    /** @param array<string, string> $args */
    public function update(Request $request, array $args = []): Response
    {
        if (!$this->csrfValid($request)) {
            return $this->redirect('/carrito');
        }

        $productId = $request->intInput('producto_id');
        $quantity  = $request->intInput('cantidad', 1);

        $this->app->cart()->update($productId, $quantity);
        $this->flash('exito', $this->t('Carrito actualizado.'));

        return $this->redirect('/carrito');
    }

    /** @param array<string, string> $args */
    public function remove(Request $request, array $args = []): Response
    {
        if (!$this->csrfValid($request)) {
            return $this->redirect('/carrito');
        }

        $productId = $request->intInput('producto_id');
        $product   = $this->app->products()->findById($productId);

        $this->app->cart()->remove($productId);

        if ($product !== null) {
            $this->app->events()->record(
                EventRecorder::CART_ITEM_REMOVED,
                [
                    'sku'              => $product['sku'],
                    'nombre'           => $product['name'],
                    'unidades_carrito' => $this->app->cart()->unitCount(),
                ],
                ['product_id' => (int) $product['id']]
            );

            $this->flash('exito', $this->t('«{producto}» se ha eliminado del carrito.', ['producto' => $product['name']]));
        }

        return $this->redirect('/carrito');
    }

    /** Aplica o retira un código de descuento. */
    public function coupon(Request $request, array $args = []): Response
    {
        if (!$this->csrfValid($request)) {
            return $this->redirect('/carrito');
        }

        if ($request->input('accion') === 'quitar') {
            $this->app->cart()->setCoupon(null);
            $this->flash('aviso', $this->t('Se ha retirado el código de descuento.'));

            return $this->redirect('/carrito');
        }

        $code = strtoupper((string) $request->input('cupon', ''));

        if ($code === '') {
            $this->flash('error', $this->t('Introduce un código de descuento.'));

            return $this->redirect('/carrito');
        }

        $summary = $this->app->pricing()->summary($this->app->cart()->items(), $code);

        if ($summary['coupon_error'] !== null) {
            $this->flash('error', (string) $summary['coupon_error']);

            return $this->redirect('/carrito');
        }

        $this->app->cart()->setCoupon($code);
        $this->flash('exito', $this->t('Código {codigo} aplicado correctamente.', ['codigo' => $code]));

        return $this->redirect('/carrito');
    }
}
