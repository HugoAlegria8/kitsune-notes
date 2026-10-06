<?php
/**
 * Versión en texto plano del aviso de envío.
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
$firstName = (string) strtok((string) $order['shipping_name'], ' ');

$out = [
    '¡Tu pedido va en camino, ' . $firstName . '!',
    '',
    'El pedido ' . $order['reference'] . ' acaba de salir del almacén.',
    'Llegará ' . $delivery . ' (envío ' . $this->shippingLabel((string) $order['shipping_method']) . ', simulado).',
    '',
    'Seguimiento (simulado): Kitsune Express · ' . $tracking,
    '',
];

if ($lines !== []) {
    $out[] = 'Qué lleva la caja:';
    foreach ($lines as $line) {
        $out[] = '- ' . $line['name'] . ' (' . $line['sku'] . ') x ' . (int) $line['quantity'];
    }
    $out[] = '';
}

$out[] = 'Dirección de entrega:';
$out[] = $order['shipping_name'];
$out[] = $order['shipping_address'];
$out[] = $order['shipping_postal_code'] . ' ' . $order['shipping_city'] . ' (' . $order['shipping_province'] . ')';
$out[] = '';
$out[] = 'Ver el estado del pedido:';
$out[] = $orderUrl;

if (!empty($invoiceUrl) && $invoice !== null) {
    $out[] = '';
    $out[] = 'Tu factura ' . $invoice['number'] . ' sigue disponible:';
    $out[] = $invoiceUrl;
}

$out[] = '';
$out[] = '-- ';
$out[] = !empty($realDelivery)
    ? 'Prototipo académico sin actividad comercial real: pedido, pago y factura ficticios.'
    : 'Prototipo académico: correo de prueba, no entregado a ningún buzón real.';

echo implode("\n", $out), "\n";
