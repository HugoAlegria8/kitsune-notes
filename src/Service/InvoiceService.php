<?php

declare(strict_types=1);

namespace KitsuneNotes\Service;

use DateTimeImmutable;
use DateTimeZone;
use KitsuneNotes\Core\Translator;
use KitsuneNotes\Repository\InvoiceRepository;
use KitsuneNotes\Repository\OrderRepository;
use KitsuneNotes\Repository\PaymentRepository;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Expedición de facturas.
 *
 * Reglas de negocio:
 *  - Solo se factura un pedido con un pago AUTORIZADO. Un intento rechazado
 *    nunca genera factura ni consume número.
 *  - La expedición es idempotente: un pedido tiene como mucho una factura.
 *  - La numeración es correlativa y sin huecos dentro de cada año
 *    (F-2026-000001). El número se asigna dentro de una transacción y, si
 *    dos peticiones simultáneas chocan, la restricción UNIQUE de la base de
 *    datos obliga a reintentar con el siguiente número libre.
 *  - La factura es un documento inmutable: al expedirla se congela una copia
 *    completa en data_json (emisor, cliente, líneas, desglose de IVA y pago).
 *  - Los importes proceden del pedido, que ya guarda base imponible y cuota
 *    por desglose del total (el precio de catálogo lleva el IVA incluido).
 *  - La factura se expide en la moneda y en el idioma del pedido. Si la
 *    moneda no es el euro, el documento recoge además el tipo de cambio
 *    aplicado y el contravalor en euros de la cuota de IVA y del total: el
 *    Reglamento de facturación (art. 12 del RD 1619/2012) permite facturar
 *    en cualquier moneda siempre que el impuesto se exprese en euros.
 *  - La numeración es única: no hay una serie por moneda ni por idioma.
 */
final class InvoiceService
{
    private const MAX_ATTEMPTS = 5;

    /**
     * @param array<string, mixed> $company   datos (ficticios) del emisor
     * @param array<string, mixed> $invoicing prefijo de serie y clave de firma de enlaces
     * @param array<string, mixed> $commerce  tipo de IVA y métodos de envío
     */
    public function __construct(
        private readonly PDO $pdo,
        private readonly InvoiceRepository $invoices,
        private readonly OrderRepository $orders,
        private readonly PaymentRepository $payments,
        private readonly EventRecorder $events,
        private readonly array $company,
        private readonly array $invoicing,
        private readonly array $commerce,
        private readonly Translator $translator,
        private readonly CurrencyService $currency,
    ) {
    }

    /** @return array<string, mixed>|null factura con el documento descodificado en «doc» */
    public function forOrder(int $orderId): ?array
    {
        $row = $this->invoices->findByOrderId($orderId);

        return $row === null ? null : $this->hydrate($row);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $row = $this->invoices->findById($id);

        return $row === null ? null : $this->hydrate($row);
    }

    /**
     * Expide la factura de un pedido pagado (o devuelve la que ya tiene).
     *
     * @param array<string, mixed> $order pedido leído con OrderRepository (incluye customer_email)
     *
     * @return array<string, mixed>
     */
    public function issue(array $order, ?DateTimeImmutable $at = null): array
    {
        $orderId  = (int) $order['id'];
        $existing = $this->invoices->findByOrderId($orderId);

        if ($existing !== null) {
            return $this->hydrate($existing);
        }

        $payment = $this->payments->lastAuthorizedForOrder($orderId);

        if ($payment === null) {
            throw new RuntimeException('Solo se puede facturar un pedido con un pago autorizado.');
        }

        $at ??= new DateTimeImmutable('now', new DateTimeZone('Europe/Madrid'));
        $year   = (int) $at->format('Y');
        $prefix = (string) ($this->invoicing['prefix'] ?? 'F');

        for ($attempt = 1; ; $attempt++) {
            $this->pdo->beginTransaction();

            try {
                $serial   = $this->invoices->nextSerial($year);
                $number   = sprintf('%s-%d-%06d', $prefix, $year, $serial);
                $document = $this->buildDocument($order, $payment, $number, $at);

                $id = $this->invoices->insert([
                    'number'      => $number,
                    'series_year' => $year,
                    'serial_no'   => $serial,
                    'order_id'    => $orderId,
                    'issued_at'   => $at->format(DATE_ATOM),
                    'base_cents'  => (int) $document['tax']['base_cents'],
                    'tax_cents'   => (int) $document['tax']['tax_cents'],
                    'total_cents' => (int) $document['total_cents'],
                    'data_json'   => (string) json_encode(
                        $document,
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    ),
                ]);

                $this->pdo->commit();

                break;
            } catch (PDOException $e) {
                $this->rollBack();

                // Otra petición pudo facturar este pedido, o coger el mismo
                // número, entre la lectura y la inserción.
                $concurrent = $this->invoices->findByOrderId($orderId);

                if ($concurrent !== null) {
                    return $this->hydrate($concurrent);
                }

                if ($attempt >= self::MAX_ATTEMPTS) {
                    throw new RuntimeException('No se ha podido numerar la factura: ' . $e->getMessage(), previous: $e);
                }
            } catch (Throwable $e) {
                $this->rollBack();

                throw $e;
            }
        }

        $this->events->record(
            EventRecorder::INVOICE_ISSUED,
            [
                'numero'               => $number,
                'referencia_pedido'    => (string) $order['reference'],
                'base_imponible_cents' => (int) $document['tax']['base_cents'],
                'iva_cents'            => (int) $document['tax']['tax_cents'],
                'total_cents'          => (int) $document['total_cents'],
                'moneda'               => (string) $document['currency'],
                'total_eur_cents'      => (int) ($document['fx']['total_base_cents'] ?? $document['total_cents']),
                'idioma'               => (string) $document['locale'],
            ],
            ['order_id' => $orderId, 'customer_id' => (int) $order['customer_id'], 'actor_type' => 'sistema']
        );

        $invoice = $this->find($id);

        if ($invoice === null) {
            throw new RuntimeException('La factura se ha creado pero no se ha podido recuperar.');
        }

        return $invoice;
    }

    /**
     * Firma del enlace a la factura que se incluye en los correos. Permite
     * abrir el documento sin pedir antes el correo del cliente, pero solo a
     * quien tenga el enlace exacto: es la misma idea que un enlace de
     * «restablecer contraseña».
     *
     * @param array<string, mixed> $invoice
     */
    public function signature(array $invoice): string
    {
        $secret = (string) ($this->invoicing['link_secret'] ?? '');

        return substr(hash_hmac('sha256', 'factura|' . $invoice['number'], $secret), 0, 32);
    }

    /** @param array<string, mixed> $invoice */
    public function verify(array $invoice, string $signature): bool
    {
        return $signature !== '' && hash_equals($this->signature($invoice), $signature);
    }

    // -----------------------------------------------------------------

    private function rollBack(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        $document = json_decode((string) $row['data_json'], true);
        $row['doc'] = is_array($document) ? $document : [];

        return $row;
    }

    /**
     * Copia congelada de todo lo que aparece impreso en la factura.
     *
     * @param array<string, mixed> $order
     * @param array<string, mixed> $payment
     *
     * @return array<string, mixed>
     */
    private function buildDocument(array $order, array $payment, string $number, DateTimeImmutable $at): array
    {
        $lines = array_map(
            static fn (array $line): array => [
                'sku'              => (string) $line['sku'],
                'name'             => (string) $line['name'],
                'design_line'      => (string) $line['design_line'],
                'quantity'         => (int) $line['quantity'],
                'unit_price_cents' => (int) $line['unit_price_cents'],
                'total_cents'      => (int) $line['line_total_cents'],
            ],
            $this->orders->lines((int) $order['id'])
        );

        // Idioma y moneda del documento: los del pedido. Los textos que se
        // congelan aquí (método de envío, país, avisos) se guardan ya en ese
        // idioma, para que la factura no dependa de quién la abra después.
        $locale   = $this->translator->supports((string) ($order['locale'] ?? ''))
            ? (string) $order['locale']
            : $this->translator->defaultLocale();
        $tr       = fn (string $text): string => $this->translator->get($text, [], $locale);
        $currency = (string) $order['currency'];
        $rate     = (int) ($order['fx_rate_micros'] ?? 0) ?: $this->currency->rateMicros($currency);

        $method = (string) $order['shipping_method'];
        $label  = isset($this->commerce['shipping'][$method]['label'])
            ? $tr((string) $this->commerce['shipping'][$method]['label'])
            : $tr('Envío');

        // Solo en facturas que no están en euros: tipo de cambio y
        // contravalor en euros de la cuota de IVA y del total.
        $fx = $currency === $this->currency->base() ? [] : ['fx' => [
            'base_currency'    => $this->currency->base(),
            'rate_micros'      => $rate,
            'tax_base_cents'   => $this->currency->toBase((int) $order['tax_cents'], $currency, $rate),
            'total_base_cents' => (int) ($order['total_base_cents'] ?? 0)
                ?: $this->currency->toBase((int) $order['total_cents'], $currency, $rate),
        ]];

        return [
            'number'          => $number,
            'issued_at'       => $at->format(DATE_ATOM),
            'operation_date'  => (string) $payment['processed_at'],
            'order_reference' => (string) $order['reference'],
            'currency'        => $currency,
            'locale'          => $locale,
            'seller'          => [
                'name'        => (string) ($this->company['name'] ?? ''),
                'tax_id'      => (string) ($this->company['tax_id'] ?? ''),
                'address'     => (string) ($this->company['address'] ?? ''),
                'postal_code' => (string) ($this->company['postal_code'] ?? ''),
                'city'        => (string) ($this->company['city'] ?? ''),
                'province'    => (string) ($this->company['province'] ?? ''),
                'country'     => $tr((string) ($this->company['country'] ?? '')),
                'email'       => (string) ($this->company['email'] ?? ''),
                'fictional'   => $tr((string) ($this->company['fictional'] ?? '')),
            ],
            'buyer'           => [
                'name'        => (string) $order['shipping_name'],
                'email'       => (string) $order['customer_email'],
                'address'     => (string) $order['shipping_address'],
                'postal_code' => (string) $order['shipping_postal_code'],
                'city'        => (string) $order['shipping_city'],
                'province'    => (string) $order['shipping_province'],
                'country'     => $tr('España'),
            ],
            'lines'           => $lines,
            'items_total_cents' => (int) $order['items_total_cents'],
            'discount'        => [
                'code'  => (string) ($order['coupon_code'] ?? ''),
                'cents' => (int) $order['discount_cents'],
            ],
            'shipping'        => ['method' => $method, 'label' => $label, 'cents' => (int) $order['shipping_cents']],
            'giftwrap_cents'  => (int) $order['giftwrap_cents'],
            'tax'             => [
                'rate'       => (float) ($this->commerce['tax_rate'] ?? 0.21),
                'base_cents' => (int) $order['taxable_base_cents'],
                'tax_cents'  => (int) $order['tax_cents'],
            ],
            'total_cents'     => (int) $order['total_cents'],
            'payment'         => [
                'method'        => (string) $payment['method'],
                'card_brand'    => (string) $payment['card_brand'],
                'card_last4'    => (string) $payment['card_last4'],
                'authorization' => (string) $payment['authorization_code'],
                'reference'     => (string) $payment['reference'],
                'processed_at'  => (string) $payment['processed_at'],
            ],
            // (En una sola línea: el texto es la clave de traducción.)
            'notice'          => $tr('Factura de prueba emitida por un prototipo académico: no tiene validez fiscal y no se ha cobrado ningún importe.'),
        ] + $fx;
    }
}
