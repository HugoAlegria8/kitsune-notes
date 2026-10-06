<?php

declare(strict_types=1);

namespace KitsuneNotes\Service;

use DateTimeImmutable;
use KitsuneNotes\Core\View;
use KitsuneNotes\Repository\SupportRepository;

/**
 * Redacta los correos transaccionales que la tienda envía al cliente y los
 * entrega al Mailer. Cada correo se genera en dos versiones —HTML y texto
 * plano— a partir de las plantillas de views/mail/.
 *
 * Plantillas:
 *  - pedido_confirmado: confirmación del pedido con la factura incluida.
 *  - pedido_enviado:    aviso de que el pedido sale del almacén.
 *  - soporte_recibido:  acuse de recibo de una solicitud de soporte.
 *  - prueba_smtp:       correo de comprobación del envío real (bin/probar-correo.php).
 */
final class Notifier
{
    public const CONFIRMED = 'pedido_confirmado';
    public const SHIPPED   = 'pedido_enviado';
    public const SUPPORT   = 'soporte_recibido';
    public const SMTP_TEST = 'prueba_smtp';

    public const TEMPLATES = [
        self::CONFIRMED => 'Confirmación y factura',
        self::SHIPPED   => 'Pedido enviado',
        self::SUPPORT   => 'Solicitud de soporte recibida',
        self::SMTP_TEST => 'Prueba de envío (SMTP)',
    ];

    /** @param array<string, mixed> $company */
    public function __construct(
        private readonly Mailer $mailer,
        private readonly View $view,
        private readonly InvoiceService $invoices,
        private readonly array $company,
    ) {
    }

    /**
     * Confirmación del pedido con la factura dentro del propio correo.
     *
     * @param array<string, mixed> $order   pedido (incluye customer_email)
     * @param array<string, mixed> $invoice factura con su documento («doc»)
     *
     * @return array<string, mixed> el correo almacenado
     */
    public function orderConfirmed(array $order, array $invoice, ?DateTimeImmutable $at = null): array
    {
        $subject = sprintf('Pedido %s confirmado · Factura %s', $order['reference'], $invoice['number']);

        return $this->deliver(
            self::CONFIRMED,
            'pedido-confirmado',
            (string) $order['customer_email'],
            (string) $order['customer_name'],
            $subject,
            [
                'preheader'  => 'Gracias por tu compra. Aquí tienes tu factura ' . $invoice['number'] . '.',
                'order'      => $order,
                'invoice'    => $invoice,
                'doc'        => $invoice['doc'],
                'invoiceUrl' => $this->invoiceUrl($order, $invoice),
                'orderUrl'   => $this->orderUrl($order),
            ],
            ['order' => $order, 'invoice' => $invoice],
            $at
        );
    }

    /**
     * Aviso de envío: se manda al pasar el pedido al estado «enviado».
     *
     * @param array<string, mixed> $order
     *
     * @return array<string, mixed>
     */
    public function orderShipped(array $order, ?DateTimeImmutable $at = null): array
    {
        $invoice = $this->invoices->forOrder((int) $order['id']);
        $subject = sprintf('Tu pedido %s ya va en camino', $order['reference']);

        return $this->deliver(
            self::SHIPPED,
            'pedido-enviado',
            (string) $order['customer_email'],
            (string) $order['customer_name'],
            $subject,
            [
                'preheader' => 'Tu paquete de Kitsune Notes ha salido del almacén.',
                'order'     => $order,
                'invoice'   => $invoice,
                'lines'     => $invoice['doc']['lines'] ?? [],
                'tracking'  => 'KNX' . strtoupper(substr(md5((string) $order['reference']), 0, 10)),
                'delivery'  => (string) $order['shipping_method'] === 'express'
                    ? 'en 24-48 horas'
                    : 'en 3-5 días laborables',
                'orderUrl'  => $this->orderUrl($order),
                'invoiceUrl' => $invoice !== null ? $this->invoiceUrl($order, $invoice) : null,
            ],
            ['order' => $order, 'invoice' => $invoice],
            $at
        );
    }

    /**
     * Acuse de recibo de una solicitud de soporte.
     *
     * @param array<string, mixed>      $ticket fila de support_tickets
     * @param array<string, mixed>|null $order  pedido relacionado, si lo hay
     *
     * @return array<string, mixed>
     */
    public function ticketReceived(array $ticket, ?array $order = null, ?DateTimeImmutable $at = null): array
    {
        $subject = sprintf('Hemos recibido tu solicitud %s', $ticket['reference']);

        return $this->deliver(
            self::SUPPORT,
            'soporte-recibido',
            (string) $ticket['customer_email'],
            (string) $ticket['customer_name'],
            $subject,
            [
                'preheader' => 'Tu solicitud está registrada; el equipo la revisará lo antes posible.',
                'ticket'    => $ticket,
                'typeLabel' => SupportRepository::TYPES[$ticket['type']] ?? (string) $ticket['type'],
                'order'     => $order,
                'orderUrl'  => $order !== null ? $this->orderUrl($order) : null,
            ],
            ['order' => $order, 'invoice' => null],
            $at
        );
    }

    /**
     * Correo de comprobación del envío real: no está ligado a ningún pedido.
     * Lo usa bin/probar-correo.php para verificar la configuración SMTP sin
     * necesidad de hacer una compra.
     *
     * @return array<string, mixed>
     */
    public function smtpTest(string $toEmail, string $toName = ''): array
    {
        $status = $this->mailer->status();

        return $this->deliver(
            self::SMTP_TEST,
            'prueba-smtp',
            $toEmail,
            $toName,
            'Prueba de envío de Kitsune Notes',
            [
                'preheader' => 'Si lees esto, el envío de correos de la tienda funciona.',
                'host'      => $status['host'],
                'from'      => $status['from'],
                'sentAt'    => (new DateTimeImmutable('now'))->format('d/m/Y H:i'),
            ],
            ['order' => null, 'invoice' => null],
            null
        );
    }

    // -----------------------------------------------------------------

    /**
     * @param array<string, mixed> $data
     * @param array{order:array<string, mixed>|null, invoice:array<string, mixed>|null} $links
     *
     * @return array<string, mixed>
     */
    private function deliver(
        string $template,
        string $view,
        string $toEmail,
        string $toName,
        string $subject,
        array $data,
        array $links,
        ?DateTimeImmutable $at,
    ): array {
        // El pie de cada correo dice la verdad según el modo: si el mensaje se va a
        // entregar de verdad no puede decir que «no se ha entregado a ningún buzón».
        $data += [
            'subject'      => $subject,
            'company'      => $this->company,
            'realDelivery' => $this->mailer->willDeliver($toEmail),
        ];

        $html = $this->view->render('mail/' . $view, $data, 'mail/layout');
        $text = $this->view->partial('mail/' . $view . '-texto', $data);

        $order   = $links['order'];
        $invoice = $links['invoice'];

        return $this->mailer->send(
            [
                'template'        => $template,
                'to_email'        => $toEmail,
                'to_name'         => $toName,
                'subject'         => $subject,
                'html'            => $html,
                'text'            => $text,
                'order_id'        => $order !== null ? (int) $order['id'] : null,
                'customer_id'     => $order !== null ? (int) $order['customer_id'] : null,
                'invoice_id'      => $invoice !== null ? (int) $invoice['id'] : null,
                'order_reference' => $order !== null ? (string) $order['reference'] : null,
                'invoice_number'  => $invoice !== null ? (string) $invoice['number'] : null,
            ],
            $at
        );
    }

    /**
     * Enlace absoluto y firmado a la factura en línea (se puede imprimir o
     * guardar como PDF desde ahí).
     *
     * @param array<string, mixed> $order
     * @param array<string, mixed> $invoice
     */
    private function invoiceUrl(array $order, array $invoice): string
    {
        return $this->view->absoluteUrl(
            '/pedido/' . rawurlencode((string) $order['reference']) . '/factura'
            . '?f=' . $this->invoices->signature($invoice)
        );
    }

    /** @param array<string, mixed> $order */
    private function orderUrl(array $order): string
    {
        return $this->view->absoluteUrl('/pedidos?referencia=' . rawurlencode((string) $order['reference']));
    }
}
