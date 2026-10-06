<?php
/**
 * Factura en texto plano (se incrusta en el correo de confirmación).
 *
 * Es text/plain: los textos se traducen con $tr() y NO con $this->t(), que
 * además escapa HTML. El correo se redacta en el idioma del pedido, que es
 * también el de la factura; los importes llevan la moneda de la factura.
 *
 * @var \KitsuneNotes\Core\View $this
 * @var array<string, mixed>     $doc
 */
$tr = fn (string $text, array $params = []): string => $this->app()->translator()->get($text, $params);

$seller   = $doc['seller'];
$buyer    = $doc['buyer'];
$tax      = $doc['tax'];
$pay      = $doc['payment'];
$currency = (string) ($doc['currency'] ?? 'EUR');
$rule     = str_repeat('-', 56);

$out = [
    $rule,
    $tr('FACTURA {numero}  (de prueba, sin validez fiscal)', ['numero' => $doc['number']]),
    $rule,
    $tr('Fecha de expedición: {fecha}', ['fecha' => $this->date($doc['issued_at'], false)]),
    $tr('Fecha de la operación: {fecha}', ['fecha' => $this->date($doc['operation_date'], false)]),
    $tr('Pedido: {referencia}', ['referencia' => $doc['order_reference']]),
    '',
    $tr('EMISOR'),
    $seller['name'] . ' · ' . $tr('NIF {nif}', ['nif' => $seller['tax_id']]),
    $seller['address'] . ', ' . $seller['postal_code'] . ' ' . $seller['city'] . ' (' . $seller['province'] . ')',
    '',
    $tr('CLIENTE'),
    $buyer['name'] . ' · ' . $buyer['email'],
    $buyer['address'] . ', ' . $buyer['postal_code'] . ' ' . $buyer['city'] . ' (' . $buyer['province'] . ')',
    '',
    $tr('CONCEPTOS'),
];

foreach ($doc['lines'] as $line) {
    $out[] = sprintf(
        '- %s (%s) · %d x %s = %s',
        $line['name'],
        $line['sku'],
        (int) $line['quantity'],
        $this->money((int) $line['unit_price_cents'], $currency),
        $this->money((int) $line['total_cents'], $currency)
    );
}

$out[] = '';
$out[] = $tr('Artículos: {importe}', ['importe' => $this->money((int) $doc['items_total_cents'], $currency)]);

if ((int) $doc['discount']['cents'] > 0) {
    $out[] = $tr('Descuento {codigo}: -{importe}', [
        'codigo'  => $doc['discount']['code'],
        'importe' => $this->money((int) $doc['discount']['cents'], $currency),
    ]);
}

// La etiqueta del envío está congelada en el documento, ya en su idioma.
$out[] = $doc['shipping']['label'] . ': '
    . ((int) $doc['shipping']['cents'] === 0 ? $tr('Gratis') : $this->money((int) $doc['shipping']['cents'], $currency));

if ((int) $doc['giftwrap_cents'] > 0) {
    $out[] = $tr('Envoltorio furoshiki: {importe}', ['importe' => $this->money((int) $doc['giftwrap_cents'], $currency)]);
}

$out[] = $tr('TOTAL (IVA incluido): {importe}', ['importe' => $this->money((int) $doc['total_cents'], $currency)]);
$out[] = '';
$out[] = $tr('Desglose de IVA: base imponible {base} + IVA {porcentaje} % {cuota}', [
    'base'       => $this->money((int) $tax['base_cents'], $currency),
    'porcentaje' => number_format((float) $tax['rate'] * 100, 0, ',', '.'),
    'cuota'      => $this->money((int) $tax['tax_cents'], $currency),
]);

// Solo en las facturas que no están en euros. El Reglamento de facturación
// (art. 12 del RD 1619/2012) permite facturar en cualquier moneda siempre que
// la cuota del impuesto se exprese en euros: por eso se indican el tipo de
// cambio aplicado y el contravalor en euros de la cuota de IVA y del total.
// En una factura en euros no se añade nada.
if (!empty($doc['fx'])) {
    $out[] = $tr('Tipo de cambio aplicado: {tipo} (tipo fijo de demostración).', [
        'tipo' => $this->app()->currency()->rateLabel($currency, (int) $doc['fx']['rate_micros']),
    ]);
    $out[] = $tr('Contravalor en euros: cuota de IVA {iva}, total {total}.', [
        'iva'   => $this->money((int) $doc['fx']['tax_base_cents'], 'EUR'),
        'total' => $this->money((int) $doc['fx']['total_base_cents'], 'EUR'),
    ]);
}

$out[] = $tr('Pago simulado con {marca} ****{ultimos} (referencia {referencia}, autorización {autorizacion}).', [
    'marca'        => $pay['card_brand'],
    'ultimos'      => $pay['card_last4'],
    'referencia'   => $pay['reference'],
    'autorizacion' => $pay['authorization'],
]);
$out[] = $doc['notice'];
$out[] = $rule;

echo implode("\n", $out);
