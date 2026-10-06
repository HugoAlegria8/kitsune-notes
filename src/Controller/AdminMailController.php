<?php

declare(strict_types=1);

namespace KitsuneNotes\Controller;

use KitsuneNotes\Core\Request;
use KitsuneNotes\Core\Response;
use KitsuneNotes\Service\Mailer;
use KitsuneNotes\Service\Notifier;
use KitsuneNotes\Support\MimeMessage;

/**
 * Back-office: buzón de correos de prueba.
 *
 * Todo lo que la tienda «envía» —confirmación con factura, aviso de envío,
 * acuse de soporte— queda guardado en el buzón de pruebas, donde el equipo
 * puede ver exactamente lo que recibiría cada cliente y descargarlo como
 * fichero .eml. Por defecto es el único destino; si el equipo activa el
 * envío real por SMTP, cada correo muestra además cómo salió la entrega.
 */
final class AdminMailController extends Controller
{
    /** @param array<string, string> $args */
    public function index(Request $request, array $args = []): Response
    {
        if (($guard = $this->guardStaff()) !== null) {
            return $guard;
        }

        $filters = [
            'q'         => $request->query('q', '') ?? '',
            'plantilla' => $request->query('plantilla', '') ?? '',
        ];

        $mails = $this->app->mailRepository();

        return $this->view('admin/correos', [
            'title'     => 'Buzón de pruebas',
            'mails'     => $mails->search($filters, 200),
            'filters'   => $filters,
            'templates' => Notifier::TEMPLATES,
            'total'     => $mails->count(),
            'unread'    => $mails->countUnread(),
            'mailer'    => $this->app->mailer()->status(),
            'delivery'  => $mails->countsByDelivery(),
        ], 'layout/admin');
    }

    /** @param array<string, string> $args */
    public function show(Request $request, array $args = []): Response
    {
        if (($guard = $this->guardStaff()) !== null) {
            return $guard;
        }

        $mail = $this->find($args['id'] ?? '');

        if ($mail === null) {
            return $this->notFound('Ese correo no existe.');
        }

        $this->app->mailRepository()->markRead((int) $mail['id']);

        return $this->view('admin/correo', [
            'title'     => $mail['subject'],
            'mail'      => $mail,
            'templates' => Notifier::TEMPLATES,
            'invoice'   => $mail['invoice_id'] !== null
                ? $this->app->invoices()->find((int) $mail['invoice_id'])
                : null,
        ], 'layout/admin');
    }

    /** @param array<string, string> $args */
    public function download(Request $request, array $args = []): Response
    {
        if (($guard = $this->guardStaff()) !== null) {
            return $guard;
        }

        $mail = $this->find($args['id'] ?? '');

        if ($mail === null) {
            return $this->notFound('Ese correo no existe.');
        }

        return Response::eml(
            MimeMessage::build($mail, simulated: $mail['delivery_status'] !== Mailer::DELIVERY_SENT),
            sprintf('correo-%d-%s.eml', (int) $mail['id'], $mail['template'])
        );
    }

    /** @return array<string, mixed>|null */
    private function find(string $id): ?array
    {
        return ctype_digit($id) ? $this->app->mailRepository()->find((int) $id) : null;
    }
}
