<?php

declare(strict_types=1);

namespace KitsuneNotes\Controller;

use KitsuneNotes\Core\Request;
use KitsuneNotes\Core\Response;
use KitsuneNotes\Service\EventRecorder;
use KitsuneNotes\Service\Mailer;
use KitsuneNotes\Service\Notifier;

/**
 * Consulta de pedidos por parte del cliente.
 *
 * Privacidad: el detalle de un pedido solo se muestra si la sesión
 * actual lo ha generado o si quien consulta demuestra conocer la pareja
 * referencia + correo electrónico usada en la compra. Conocer solo la
 * referencia no basta para ver los datos de envío.
 */
final class OrderController extends Controller
{
    /** @param array<string, string> $args */
    public function show(Request $request, array $args = []): Response
    {
        $reference = strtoupper(trim($args['reference'] ?? ''));
        $order     = $this->app->orders()->findByReference($reference);

        if ($order === null) {
            return $this->notFound($this->t('No encontramos ningún pedido con la referencia {referencia}.', ['referencia' => $reference]));
        }

        $own = (array) $this->app->session()->get('pedidos_propios', []);

        if (!in_array($reference, $own, true)) {
            $this->flash('aviso', $this->t('Para ver este pedido, confirma el correo electrónico con el que se realizó.'));

            return $this->view('order/consulta', [
                'title'     => $this->t('Consulta tu pedido'),
                'reference' => $reference,
                'errors'    => [],
            ]);
        }

        return $this->view('order/detalle', [
            'title'    => $this->t('Pedido {referencia}', ['referencia' => $order['reference']]),
            'order'    => $order,
            'lines'    => $this->app->orders()->lines((int) $order['id']),
            'payments' => $this->app->payments()->forOrder((int) $order['id']),
            'history'  => $this->app->orders()->history((int) $order['id']),
            'justPlaced' => $order['status'] === 'pagado_simulado',
            'invoice'  => $this->app->invoices()->forOrder((int) $order['id']),
            'mailInfo' => $this->confirmationMail((int) $order['id']),
        ]);
    }

    /**
     * Cómo salió el correo de confirmación del pedido, para que la página de
     * confirmación diga la verdad: enviado de verdad, fallido o solo buzón.
     *
     * @return array{status:string, smtp:bool}
     */
    private function confirmationMail(int $orderId): array
    {
        $status = Mailer::DELIVERY_LOCAL;

        // forOrder() devuelve primero el más reciente.
        foreach ($this->app->mailRepository()->forOrder($orderId) as $mail) {
            if ($mail['template'] === Notifier::CONFIRMED) {
                $status = (string) $mail['delivery_status'];
                break;
            }
        }

        return ['status' => $status, 'smtp' => $this->app->mailer()->smtpEnabled()];
    }

    /**
     * Factura del pedido. Se puede abrir de dos maneras: desde el área del
     * cliente (la sesión ya ha demostrado conocer referencia + correo) o
     * desde el enlace firmado que lleva el correo de confirmación.
     *
     * @param array<string, string> $args
     */
    public function invoice(Request $request, array $args = []): Response
    {
        $reference = strtoupper(trim($args['reference'] ?? ''));
        $order     = $this->app->orders()->findByReference($reference);

        if ($order === null) {
            return $this->notFound($this->t('No encontramos ningún pedido con la referencia {referencia}.', ['referencia' => $reference]));
        }

        $invoice   = $this->app->invoices()->forOrder((int) $order['id']);
        $own       = (array) $this->app->session()->get('pedidos_propios', []);
        $viaLink   = $invoice !== null
            && $this->app->invoices()->verify($invoice, (string) $request->query('f', ''));

        if (!in_array($reference, $own, true) && !$viaLink) {
            $this->flash('aviso', $this->t('Para ver la factura, confirma el correo electrónico con el que se hizo el pedido.'));

            return $this->view('order/consulta', [
                'title'     => $this->t('Consulta tu pedido'),
                'reference' => $reference,
                'errors'    => [],
            ]);
        }

        if ($invoice === null) {
            return $this->notFound($this->t(
                'El pedido {referencia} todavía no tiene factura: se expide cuando el pago queda confirmado.',
                ['referencia' => $reference]
            ));
        }

        $this->recordViewed($order, $invoice, $viaLink && !in_array($reference, $own, true));

        return $this->view('invoice/ver', [
            'title'     => $this->t('Factura {numero}', ['numero' => $invoice['number']]),
            'invoice'   => $invoice,
            'doc'       => $invoice['doc'],
            'backUrl'   => '/pedido/' . $order['reference'],
            'backLabel' => $this->t('Volver al pedido'),
        ]);
    }

    /**
     * Emite invoice.viewed una vez por sesión y factura, para que recargar la
     * página no infle el almacén de eventos. El canal permite medir cuántos
     * clientes llegan a la factura desde el enlace del correo.
     *
     * @param array<string, mixed> $order
     * @param array<string, mixed> $invoice
     */
    private function recordViewed(array $order, array $invoice, bool $fromEmail): void
    {
        $session = $this->app->session();
        $key     = '_factura_vista_' . $invoice['number'];

        if ($session->get($key) === true) {
            return;
        }

        $session->put($key, true);

        $this->app->events()->record(
            EventRecorder::INVOICE_VIEWED,
            [
                'numero'            => (string) $invoice['number'],
                'referencia_pedido' => (string) $order['reference'],
                'canal'             => $fromEmail ? 'enlace_correo' : 'area_cliente',
            ],
            ['order_id' => (int) $order['id'], 'customer_id' => (int) $order['customer_id']]
        );
    }

    /** @param array<string, string> $args */
    public function lookupForm(Request $request, array $args = []): Response
    {
        return $this->view('order/consulta', [
            'title'     => $this->t('Consulta tu pedido'),
            'reference' => strtoupper((string) $request->query('referencia', '')),
            'errors'    => [],
        ]);
    }

    /** @param array<string, string> $args */
    public function lookup(Request $request, array $args = []): Response
    {
        if (!$this->csrfValid($request)) {
            return $this->redirect('/pedidos');
        }

        $validator = $this->validate(
            $request->all(),
            ['referencia' => 'requerido|max:32', 'email' => 'requerido|email'],
            ['referencia' => $this->t('Referencia del pedido'), 'email' => $this->t('Correo electrónico')]
        );

        if ($validator->fails()) {
            return $this->view('order/consulta', [
                'title'     => $this->t('Consulta tu pedido'),
                'reference' => (string) $request->input('referencia', ''),
                'errors'    => $validator->errors(),
            ]);
        }

        $reference = strtoupper((string) $request->input('referencia'));
        $email     = mb_strtolower((string) $request->input('email'));
        $order     = $this->app->orders()->findByReference($reference);

        if ($order === null || mb_strtolower((string) $order['customer_email']) !== $email) {
            $this->flash('error', $this->t('No hay ningún pedido que coincida con esos datos.'));

            return $this->view('order/consulta', [
                'title'     => $this->t('Consulta tu pedido'),
                'reference' => $reference,
                'errors'    => [],
            ]);
        }

        $own   = (array) $this->app->session()->get('pedidos_propios', []);
        $own[] = $reference;
        $this->app->session()->put('pedidos_propios', array_values(array_unique($own)));

        return $this->redirect('/pedido/' . $reference);
    }
}
