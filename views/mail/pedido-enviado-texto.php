<?php
/**
 * Versión en texto plano del aviso de envío.
 *
 * Es text/plain: los textos se traducen con $tr() y NO con $this->t(), que
 * además escapa HTML.
 *
 * @var \KitsuneNotes\Core\View $this
 * @var array<string, mixed>     $order
 * @var array<string, mixed>|null $invoice
 * @var list<array<string, mixed>> $lines
 * @var string                   $tracking
 * @var string                   $delivery
 * @var string                   $orderUrl
 * @var string|null              $invoiceUrl
 */
$tr = fn (string $text, array $params = []): string => $this->app()->translator()->get($text, $params);

$firstName = (string) strtok((string) $order['shipping_name'], ' ');

$out = [
    $tr('¡Tu pedido va en camino, {nombre}!', ['nombre' => $firstName]),
    '',
    $tr('El pedido {referencia} acaba de salir del almacén.', ['referencia' => $order['reference']]),
    $tr('Llegará {plazo} (envío {metodo}, simulado).', ['plazo' => $delivery, 'metodo' => $this->shippingLabel((string) $order['shipping_method'])]),
    '',
    $tr('Seguimiento (simulado): {transportista} · {codigo}', ['transportista' => 'Kitsune Express', 'codigo' => $tracking]),
    '',
];

if ($lines !== []) {
    $out[] = $tr('Qué lleva la caja:');
    foreach ($lines as $line) {
        $out[] = '- ' . $line['name'] . ' (' . $line['sku'] . ') x ' . (int) $line['quantity'];
    }
    $out[] = '';
}

$out[] = $tr('Dirección de entrega:');
$out[] = $order['shipping_name'];
$out[] = $order['shipping_address'];
$out[] = $order['shipping_postal_code'] . ' ' . $order['shipping_city'] . ' (' . $order['shipping_province'] . ')';
$out[] = '';
$out[] = $tr('Ver el estado del pedido:');
$out[] = $orderUrl;

if (!empty($invoiceUrl) && $invoice !== null) {
    $out[] = '';
    $out[] = $tr('Tu factura {factura} sigue disponible:', ['factura' => $invoice['number']]);
    $out[] = $invoiceUrl;
}

$out[] = '';
$out[] = '-- ';
$out[] = !empty($realDelivery)
    ? $tr('Prototipo académico sin actividad comercial real: pedido, pago y factura ficticios.')
    : $tr('Prototipo académico: correo de prueba, no entregado a ningún buzón real.');

echo implode("\n", $out), "\n";
