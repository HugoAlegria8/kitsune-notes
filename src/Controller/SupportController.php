<?php

declare(strict_types=1);

namespace KitsuneNotes\Controller;

use KitsuneNotes\Core\Request;
use KitsuneNotes\Core\Response;
use KitsuneNotes\Repository\SupportRepository;
use KitsuneNotes\Service\EventRecorder;
use KitsuneNotes\Service\Mailer;
use Throwable;

/**
 * Canal de postventa: consultas e incidencias sobre pedidos.
 */
final class SupportController extends Controller
{
    /** @param array<string, string> $args */
    public function form(Request $request, array $args = []): Response
    {
        return $this->view('support/form', [
            'title'     => $this->t('Soporte y postventa'),
            'types'     => SupportRepository::TYPES,
            'reference' => strtoupper((string) $request->query('pedido', '')),
            'errors'    => [],
            'old'       => $this->app->session()->pullOldInput(),
        ]);
    }

    /** @param array<string, string> $args */
    public function submit(Request $request, array $args = []): Response
    {
        if (!$this->csrfValid($request)) {
            $this->flash('error', $this->t('La sesión ha caducado. Vuelve a enviar el formulario.'));

            return $this->redirect('/soporte');
        }

        $validator = $this->validate(
            $request->all(),
            [
                'nombre'  => 'requerido|min:3|max:80',
                'email'   => 'requerido|email|max:120',
                'tipo'    => 'requerido|en:' . implode(',', array_keys(SupportRepository::TYPES)),
                'pedido'  => 'max:32',
                'asunto'  => 'requerido|min:5|max:120',
                'mensaje' => 'requerido|min:15|max:1500',
            ],
            [
                'nombre'  => $this->t('Nombre'),
                'email'   => $this->t('Correo electrónico'),
                'tipo'    => $this->t('Tipo de solicitud'),
                'pedido'  => $this->t('Referencia del pedido'),
                'asunto'  => $this->t('Asunto'),
                'mensaje' => $this->t('Mensaje'),
            ]
        );

        if ($validator->fails()) {
            $this->app->session()->flashInput($request->all());

            return $this->view('support/form', [
                'title'     => $this->t('Soporte y postventa'),
                'types'     => SupportRepository::TYPES,
                'reference' => (string) $request->input('pedido', ''),
                'errors'    => $validator->errors(),
                'old'       => $request->all(),
            ]);
        }

        $data            = $validator->validated();
        $orderReference  = strtoupper((string) ($data['pedido'] ?? ''));
        $order           = $orderReference !== '' ? $this->app->orders()->findByReference($orderReference) : null;

        $ticketReference = $this->app->support()->insert([
            'order_reference' => $order !== null ? $orderReference : '',
            'customer_name'   => $data['nombre'],
            'customer_email'  => $data['email'],
            'type'            => $data['tipo'],
            'subject'         => $data['asunto'],
            'message'         => $data['mensaje'],
            // Idioma en que escribe el cliente: el acuse de recibo sale en él.
            'locale'          => $this->app->translator()->locale(),
        ]);

        // Una solicitud asociada a un pedido concreto es una incidencia;
        // el resto son solicitudes genéricas de soporte.
        $isIncident = $order !== null
            && in_array($data['tipo'], ['incidencia_envio', 'producto_danado', 'devolucion'], true);

        $this->app->events()->record(
            $isIncident ? EventRecorder::INCIDENT_CREATED : EventRecorder::SUPPORT_REQUESTED,
            [
                'referencia_ticket' => $ticketReference,
                'tipo'              => $data['tipo'],
                'asunto'            => $data['asunto'],
                'referencia_pedido' => $order !== null ? $orderReference : null,
                'canal'             => 'formulario_web',
                'idioma'            => $this->app->translator()->locale(),
                'longitud_mensaje'  => mb_strlen((string) $data['mensaje']),
            ],
            $order !== null
                ? ['order_id' => (int) $order['id'], 'customer_id' => (int) $order['customer_id']]
                : []
        );

        // Si la incidencia afecta a un pedido en curso, se refleja en su estado.
        if ($isIncident && $order !== null) {
            $changed = $this->app->orderService()->changeStatus(
                $order,
                'incidencia',
                'sistema',
                'Incidencia ' . $ticketReference . ' comunicada por el cliente.'
            );

            if ($changed) {
                $this->app->events()->record(
                    EventRecorder::ORDER_STATUS_CHANGED,
                    [
                        'referencia'     => $orderReference,
                        'estado_anterior'=> $order['status'],
                        'estado_nuevo'   => 'incidencia',
                        'origen'         => 'formulario_soporte',
                        'ticket'         => $ticketReference,
                    ],
                    ['order_id' => (int) $order['id']]
                );
            }
        }

        // Acuse de recibo por correo. La solicitud ya está guardada: si el
        // correo fallara, no debe perderse ni mostrarse como error.
        $mailSent   = false;
        $mailStatus = Mailer::DELIVERY_LOCAL;

        try {
            $ticket = $this->app->support()->findByReference($ticketReference);

            if ($ticket !== null) {
                $mail       = $this->app->notifier()->ticketReceived($ticket, $order);
                $mailSent   = true;
                $mailStatus = (string) $mail['delivery_status'];
            }
        } catch (Throwable $e) {
            error_log('[kitsune-notes] No se pudo enviar el acuse de soporte: ' . $e->getMessage());
        }

        return $this->view('support/enviado', [
            'title'      => $this->t('Solicitud registrada'),
            'reference'  => $ticketReference,
            'isIncident' => $isIncident,
            'order'      => $order,
            'mailSent'   => $mailSent,
            'mailStatus' => $mailStatus,
            'smtpOn'     => $this->app->mailer()->smtpEnabled(),
            'email'      => (string) $data['email'],
        ]);
    }
}
