<?php

declare(strict_types=1);

namespace KitsuneNotes\Service;

use DateTimeImmutable;
use KitsuneNotes\Core\Translator;
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
 *
 * Idioma: cada correo se redacta en el idioma del pedido (o de la solicitud
 * de soporte), no en el de quien provoca el envío. Si el personal marca como
 * enviado, desde el back-office en español, un pedido hecho en inglés, el
 * cliente recibe el aviso en inglés y con los importes en su moneda.
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
        private readonly Translator $translator,
    ) {
    }

    /**
     * Idioma en que hay que escribir a un cliente: el guardado en su pedido o
     * en su solicitud. Si falta o ya no está admitido, el idioma original.
     *
     * @param array<string, mixed>|null $row
     */
    private function localeOf(?array $row): string
    {
        $locale = (string) ($row['locale'] ?? '');

        return $this->translator->supports($locale) ? $locale : $this->translator->defaultLocale();
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
        return $this->translator->runIn($this->localeOf($order), fn (): array => $this->deliver(
            self::CONFIRMED,
            'pedido-confirmado',
            (string) $order['customer_email'],
            (string) $order['customer_name'],
            $this->translator->get(
                'Pedido {referencia} confirmado · Factura {factura}',
                ['referencia' => $order['reference'], 'factura' => $invoice['number']]
            ),
            [
                'preheader'  => $this->translator->get(
                    'Gracias por tu compra. Aquí tienes tu factura {factura}.',
                    ['factura' => $invoice['number']]
                ),
                'order'      => $order,
                'invoice'    => $invoice,
                'doc'        => $invoice['doc'],
                'invoiceUrl' => $this->invoiceUrl($order, $invoice),
                'orderUrl'   => $this->orderUrl($order),
            ],
            ['order' => $order, 'invoice' => $invoice],
            $at
        ));
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

        return $this->translator->runIn($this->localeOf($order), fn (): array => $this->deliver(
            self::SHIPPED,
            'pedido-enviado',
            (string) $order['customer_email'],
            (string) $order['customer_name'],
            $this->translator->get('Tu pedido {referencia} ya va en camino', ['referencia' => $order['reference']]),
            [
                'preheader' => $this->translator->get('Tu paquete de Kitsune Notes ha salido del almacén.'),
                'order'     => $order,
                'invoice'   => $invoice,
                'lines'     => $invoice['doc']['lines'] ?? [],
                'tracking'  => 'KNX' . strtoupper(substr(md5((string) $order['reference']), 0, 10)),
                'delivery'  => (string) $order['shipping_method'] === 'express'
                    ? $this->translator->get('en 24-48 horas')
                    : $this->translator->get('en 3-5 días laborables'),
                'orderUrl'  => $this->orderUrl($order),
                'invoiceUrl' => $invoice !== null ? $this->invoiceUrl($order, $invoice) : null,
            ],
            ['order' => $order, 'invoice' => $invoice],
            $at
        ));
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
        // El acuse sale en el idioma en que el cliente escribió la solicitud.
        return $this->translator->runIn($this->localeOf($ticket), fn (): array => $this->deliver(
            self::SUPPORT,
            'soporte-recibido',
            (string) $ticket['customer_email'],
            (string) $ticket['customer_name'],
            $this->translator->get('Hemos recibido tu solicitud {referencia}', ['referencia' => $ticket['reference']]),
            [
                'preheader' => $this->translator->get('Tu solicitud está registrada; el equipo la revisará lo antes posible.'),
                'ticket'    => $ticket,
                'typeLabel' => $this->translator->get(SupportRepository::TYPES[$ticket['type']] ?? (string) $ticket['type']),
                'order'     => $order,
                'orderUrl'  => $order !== null ? $this->orderUrl($order) : null,
            ],
            ['order' => $order, 'invoice' => null],
            $at
        ));
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

        // Es una herramienta del equipo: siempre en español.
        return $this->translator->runIn($this->translator->defaultLocale(), fn (): array => $this->deliver(
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
        ));
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
            'mailLocale'   => $this->translator->locale(),
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
                'locale'          => $this->translator->locale(),
            ],
            $at
        );
    }

    /**
     * Parámetro que hace que el enlace de un correo abra la tienda en el idioma
     * del propio correo. En español no se añade nada: los enlaces no cambian.
     */
    private function localeParam(string $separator): string
    {
        return $this->translator->isDefault() ? '' : $separator . 'idioma=' . rawurlencode($this->translator->locale());
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
            . $this->localeParam('&')
        );
    }

    /** @param array<string, mixed> $order */
    private function orderUrl(array $order): string
    {
        return $this->view->absoluteUrl(
            '/pedidos?referencia=' . rawurlencode((string) $order['reference']) . $this->localeParam('&')
        );
    }
}
