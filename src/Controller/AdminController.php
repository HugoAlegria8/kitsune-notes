<?php

declare(strict_types=1);

namespace KitsuneNotes\Controller;

use KitsuneNotes\Core\Request;
use KitsuneNotes\Core\Response;
use KitsuneNotes\Repository\OrderRepository;
use KitsuneNotes\Repository\SupportRepository;
use KitsuneNotes\Service\EventRecorder;
use KitsuneNotes\Service\Mailer;
use KitsuneNotes\Service\Notifier;
use KitsuneNotes\Service\OrderService;
use RuntimeException;
use Throwable;

/**
 * Back-office: consulta interna de pedidos, eventos e incidencias.
 *
 * Es deliberadamente sobrio: su objetivo es demostrar que el canal
 * digital deja evidencias consultables, no construir un ERP.
 */
final class AdminController extends Controller
{
    /** @param array<string, string> $args */
    public function loginForm(Request $request, array $args = []): Response
    {
        if ($this->app->auth()->check()) {
            return $this->redirect('/admin');
        }

        return $this->view('admin/login', [
            'title'      => 'Acceso al back-office',
            'demoEmail'  => 'admin@kitsunenotes.test',
        ], 'layout/admin');
    }

    /** @param array<string, string> $args */
    public function login(Request $request, array $args = []): Response
    {
        if (!$this->csrfValid($request)) {
            $this->flash('error', 'La sesión ha caducado. Inténtalo de nuevo.');

            return $this->redirect('/admin/login');
        }

        $email    = (string) $request->input('email', '');
        $password = (string) $request->input('password', '');

        if (!$this->app->auth()->attempt($email, $password)) {
            $this->flash('error', 'Credenciales incorrectas.');

            return $this->redirect('/admin/login');
        }

        // Si el acceso se pidió desde un enlace interno (p. ej. al abrir un
        // correo del buzón), se vuelve a esa página. Solo se aceptan rutas
        // del propio back-office: nunca una URL externa.
        $next = (string) $this->app->session()->get('admin_next', '');
        $this->app->session()->forget('admin_next');

        if (preg_match('#^/admin(?:/[A-Za-z0-9._~%/-]*)?(?:\?[A-Za-z0-9._~%&=+-]*)?$#', $next) === 1
            && !str_starts_with($next, '/admin/login')) {
            return $this->redirect($next);
        }

        return $this->redirect('/admin');
    }

    /** @param array<string, string> $args */
    public function logout(Request $request, array $args = []): Response
    {
        $this->app->auth()->logout();
        $this->flash('exito', 'Has cerrado la sesión del back-office.');

        return $this->redirect('/admin/login');
    }

    /** @param array<string, string> $args */
    public function dashboard(Request $request, array $args = []): Response
    {
        if (($guard = $this->guardStaff()) !== null) {
            return $guard;
        }

        $orders = $this->app->orders();

        return $this->view('admin/dashboard', [
            'title'          => 'Panel de control',
            'counts'         => $orders->countsByStatus(),
            'totalOrders'    => $orders->count(),
            'revenueCents'   => $orders->totalRevenueCents(),
            'eventCounts'    => $this->app->eventsRepository()->countsByName(),
            'totalEvents'    => $this->app->eventsRepository()->count(),
            'recentOrders'   => $orders->search([], 8),
            'recentEvents'   => $this->app->eventsRepository()->search([], 12),
            'openTickets'    => $this->app->support()->countOpen(),
            'lowStock'       => $this->app->products()->lowStock(20),
            'customers'      => $this->app->customers()->count(),
            'declinedPayments' => $this->app->payments()->countByStatus('rechazado'),
        ], 'layout/admin');
    }

    /** @param array<string, string> $args */
    public function orders(Request $request, array $args = []): Response
    {
        if (($guard = $this->guardStaff()) !== null) {
            return $guard;
        }

        $filters = [
            'estado' => $request->query('estado', '') ?? '',
            'q'      => $request->query('q', '') ?? '',
        ];

        return $this->view('admin/pedidos', [
            'title'    => 'Pedidos',
            'orders'   => $this->app->orders()->search($filters, 200),
            'filters'  => $filters,
            'statuses' => OrderRepository::STATUSES,
        ], 'layout/admin');
    }

    /** @param array<string, string> $args */
    public function orderDetail(Request $request, array $args = []): Response
    {
        if (($guard = $this->guardStaff()) !== null) {
            return $guard;
        }

        $order = $this->app->orders()->findByReference(strtoupper($args['reference'] ?? ''));

        if ($order === null) {
            return $this->notFound('Ese pedido no existe.');
        }

        return $this->view('admin/pedido-detalle', [
            'title'       => 'Pedido ' . $order['reference'],
            'order'       => $order,
            'lines'       => $this->app->orders()->lines((int) $order['id']),
            'payments'    => $this->app->payments()->forOrder((int) $order['id']),
            'history'     => $this->app->orders()->history((int) $order['id']),
            'events'      => $this->app->eventsRepository()->search([], 100),
            'transitions' => OrderService::allowedTransitions()[$order['status']] ?? [],
            'invoice'     => $this->app->invoices()->forOrder((int) $order['id']),
            'mails'       => $this->app->mailRepository()->forOrder((int) $order['id']),
            'templates'   => Notifier::TEMPLATES,
            'canInvoice'  => $this->app->payments()->lastAuthorizedForOrder((int) $order['id']) !== null,
        ], 'layout/admin');
    }

    /** Factura de un pedido, con el marco del back-office. @param array<string, string> $args */
    public function invoice(Request $request, array $args = []): Response
    {
        if (($guard = $this->guardStaff()) !== null) {
            return $guard;
        }

        $order = $this->app->orders()->findByReference(strtoupper($args['reference'] ?? ''));

        if ($order === null) {
            return $this->notFound('Ese pedido no existe.');
        }

        $invoice = $this->app->invoices()->forOrder((int) $order['id']);

        if ($invoice === null) {
            $this->flash('aviso', 'El pedido ' . $order['reference'] . ' todavía no tiene factura.');

            return $this->redirect('/admin/pedidos/' . $order['reference']);
        }

        return $this->view('invoice/ver', [
            'title'     => 'Factura ' . $invoice['number'],
            'invoice'   => $invoice,
            'doc'       => $invoice['doc'],
            'backUrl'   => '/admin/pedidos/' . $order['reference'],
            'backLabel' => 'Volver al pedido ' . $order['reference'],
        ], 'layout/admin');
    }

    /**
     * Expide la factura de un pedido pagado que aún no la tiene (por
     * ejemplo, uno anterior a esta función) y envía el correo de
     * confirmación; si ya la tiene, reenvía el correo. @param array<string, string> $args
     */
    public function issueInvoice(Request $request, array $args = []): Response
    {
        if (($guard = $this->guardStaff()) !== null) {
            return $guard;
        }

        if (!$this->csrfValid($request)) {
            $this->flash('error', 'La sesión ha caducado.');

            return $this->redirect('/admin/pedidos');
        }

        $order = $this->app->orders()->findByReference(strtoupper($args['reference'] ?? ''));

        if ($order === null) {
            return $this->notFound('Ese pedido no existe.');
        }

        $target = '/admin/pedidos/' . $order['reference'];

        try {
            $existing = $this->app->invoices()->forOrder((int) $order['id']);
            $invoice  = $this->app->invoices()->issue($order);
            $mail     = $this->app->notifier()->orderConfirmed($order, $invoice);
        } catch (RuntimeException $e) {
            $this->flash('error', $e->getMessage());

            return $this->redirect($target);
        }

        $outcome = $this->deliveryOutcome($mail);

        $this->flash($outcome['failed'] ? 'aviso' : 'exito', $existing === null
            ? 'Factura ' . $invoice['number'] . ' expedida y correo de confirmación ' . $outcome['text'] . '.'
            : 'Correo de confirmación con la factura ' . $invoice['number'] . ' reenviado: ' . $outcome['text'] . '.');

        return $this->redirect($target);
    }

    /**
     * Frase para los avisos del back-office según el resultado de la entrega
     * de un correo (solo buzón, enviado por SMTP o fallido).
     *
     * @param array<string, mixed> $mail el correo devuelto por el Notifier
     *
     * @return array{text:string, failed:bool}
     */
    private function deliveryOutcome(array $mail): array
    {
        $detail = (string) ($mail['delivery_detail'] ?? '');

        return match ($mail['delivery_status'] ?? Mailer::DELIVERY_LOCAL) {
            Mailer::DELIVERY_SENT => [
                'text'   => 'enviado por SMTP a ' . $mail['to_email'] . ' (y guardado en el buzón de pruebas)',
                'failed' => false,
            ],
            Mailer::DELIVERY_FAILED => [
                'text'   => 'guardado en el buzón de pruebas, pero NO se pudo enviar por SMTP: ' . $detail,
                'failed' => true,
            ],
            default => [
                'text'   => 'guardado en el buzón de pruebas' . ($detail !== '' ? ' (' . $detail . ')' : ''),
                'failed' => false,
            ],
        };
    }

    /** @param array<string, string> $args */
    public function updateStatus(Request $request, array $args = []): Response
    {
        if (($guard = $this->guardStaff()) !== null) {
            return $guard;
        }

        if (!$this->csrfValid($request)) {
            $this->flash('error', 'La sesión ha caducado.');

            return $this->redirect('/admin/pedidos');
        }

        $order = $this->app->orders()->findByReference(strtoupper($args['reference'] ?? ''));

        if ($order === null) {
            return $this->notFound('Ese pedido no existe.');
        }

        $newStatus = (string) $request->input('estado', '');
        $note      = (string) $request->input('nota', '');
        $user      = $this->app->auth()->user();
        $actor     = (string) ($user['email'] ?? 'back-office');

        if (!$this->app->orderService()->changeStatus($order, $newStatus, $actor, $note)) {
            $this->flash('error', 'La transición de estado solicitada no está permitida.');

            return $this->redirect('/admin/pedidos/' . $order['reference']);
        }

        $this->app->events()->record(
            EventRecorder::ORDER_STATUS_CHANGED,
            [
                'referencia'      => $order['reference'],
                'estado_anterior' => $order['status'],
                'estado_nuevo'    => $newStatus,
                'origen'          => 'back_office',
                'operador'        => $actor,
                'nota'            => $note,
            ],
            ['order_id' => (int) $order['id'], 'actor_type' => 'personal']
        );

        $message = 'El pedido ' . $order['reference'] . ' ha pasado a «' . $newStatus . '».';
        $type    = 'exito';

        // Al salir del almacén se avisa al cliente por correo.
        if ($newStatus === 'enviado' && ($mail = $this->notifyShipped((int) $order['id'])) !== null) {
            $outcome = $this->deliveryOutcome($mail);
            $message .= ' Aviso de envío ' . $outcome['text'] . '.';
            $type     = $outcome['failed'] ? 'aviso' : 'exito';
        }

        $this->flash($type, $message);

        return $this->redirect('/admin/pedidos/' . $order['reference']);
    }

    /**
     * El cambio de estado ya está guardado: un fallo al redactar el correo
     * no debe deshacerlo ni romper el back-office, solo queda trazado.
     *
     * @return array<string, mixed>|null el correo generado, o null si no se pudo
     */
    private function notifyShipped(int $orderId): ?array
    {
        try {
            $fresh = $this->app->orders()->findById($orderId);

            if ($fresh === null) {
                return null;
            }

            return $this->app->notifier()->orderShipped($fresh);
        } catch (Throwable $e) {
            error_log('[kitsune-notes] No se pudo enviar el aviso de envío: ' . $e->getMessage());

            return null;
        }
    }

    /** @param array<string, string> $args */
    public function events(Request $request, array $args = []): Response
    {
        if (($guard = $this->guardStaff()) !== null) {
            return $guard;
        }

        $filters = [
            'nombre' => $request->query('nombre', '') ?? '',
            'sesion' => $request->query('sesion', '') ?? '',
        ];

        return $this->view('admin/eventos', [
            'title'       => 'Eventos registrados',
            'events'      => $this->app->eventsRepository()->search($filters, 300),
            'filters'     => $filters,
            'names'       => $this->app->eventsRepository()->distinctNames(),
            'catalog'     => EventRecorder::CATALOG,
            'counts'      => $this->app->eventsRepository()->countsByName(),
            'apiToken'    => (string) $this->app->config('security.api_token'),
            'logPath'     => 'storage/events/events-' . date('Y-m-d') . '.jsonl',
        ], 'layout/admin');
    }

    /** @param array<string, string> $args */
    public function tickets(Request $request, array $args = []): Response
    {
        if (($guard = $this->guardStaff()) !== null) {
            return $guard;
        }

        return $this->view('admin/incidencias', [
            'title'   => 'Soporte e incidencias',
            'tickets' => $this->app->support()->all($request->query('estado', '') ?: null),
            'types'   => SupportRepository::TYPES,
            'estado'  => $request->query('estado', '') ?? '',
        ], 'layout/admin');
    }

    /** @param array<string, string> $args */
    public function updateTicket(Request $request, array $args = []): Response
    {
        if (($guard = $this->guardStaff()) !== null) {
            return $guard;
        }

        if (!$this->csrfValid($request)) {
            return $this->redirect('/admin/incidencias');
        }

        $reference = strtoupper($args['reference'] ?? '');
        $status    = (string) $request->input('estado', 'abierta');

        if (!in_array($status, ['abierta', 'en_curso', 'resuelta'], true)) {
            $this->flash('error', 'Estado de incidencia no válido.');

            return $this->redirect('/admin/incidencias');
        }

        $this->app->support()->updateStatus($reference, $status);
        $this->flash('exito', 'La incidencia ' . $reference . ' se ha marcado como «' . $status . '».');

        return $this->redirect('/admin/incidencias');
    }
}
