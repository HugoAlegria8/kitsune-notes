<?php
/**
 * Factura en texto plano (se incrusta en el correo de confirmación).
 *
 * @var \KitsuneNotes\Core\View $this
 * @var array<string, mixed>     $doc
 */
$seller = $doc['seller'];
$buyer  = $doc['buyer'];
$tax    = $doc['tax'];
$pay    = $doc['payment'];
$rule   = str_repeat('-', 56);

$out = [
    $rule,
    'FACTURA ' . $doc['number'] . '  (de prueba, sin validez fiscal)',
    $rule,
    'Fecha de expedición: ' . $this->date($doc['issued_at'], false),
    'Fecha de la operación: ' . $this->date($doc['operation_date'], false),
    'Pedido: ' . $doc['order_reference'],
    '',
    'EMISOR',
    $seller['name'] . ' · NIF ' . $seller['tax_id'],
    $seller['address'] . ', ' . $seller['postal_code'] . ' ' . $seller['city'] . ' (' . $seller['province'] . ')',
    '',
    'CLIENTE',
    $buyer['name'] . ' · ' . $buyer['email'],
    $buyer['address'] . ', ' . $buyer['postal_code'] . ' ' . $buyer['city'] . ' (' . $buyer['province'] . ')',
    '',
    'CONCEPTOS',
];

foreach ($doc['lines'] as $line) {
    $out[] = sprintf(
        '- %s (%s) · %d x %s = %s',
        $line['name'],
        $line['sku'],
        (int) $line['quantity'],
        $this->money((int) $line['unit_price_cents']),
        $this->money((int) $line['total_cents'])
    );
}

$out[] = '';
$out[] = 'Artículos: ' . $this->money((int) $doc['items_total_cents']);

if ((int) $doc['discount']['cents'] > 0) {
    $out[] = 'Descuento ' . $doc['discount']['code'] . ': -' . $this->money((int) $doc['discount']['cents']);
}

$out[] = $doc['shipping']['label'] . ': '
    . ((int) $doc['shipping']['cents'] === 0 ? 'Gratis' : $this->money((int) $doc['shipping']['cents']));

if ((int) $doc['giftwrap_cents'] > 0) {
    $out[] = 'Envoltorio furoshiki: ' . $this->money((int) $doc['giftwrap_cents']);
}

$out[] = 'TOTAL (IVA incluido): ' . $this->money((int) $doc['total_cents']);
$out[] = '';
$out[] = sprintf(
    'Desglose de IVA: base imponible %s + IVA %s %% %s',
    $this->money((int) $tax['base_cents']),
    number_format((float) $tax['rate'] * 100, 0, ',', '.'),
    $this->money((int) $tax['tax_cents'])
);
$out[] = sprintf(
    'Pago simulado con %s ****%s (referencia %s, autorización %s).',
    $pay['card_brand'],
    $pay['card_last4'],
    $pay['reference'],
    $pay['authorization']
);
$out[] = $doc['notice'];
$out[] = $rule;

echo implode("\n", $out);
