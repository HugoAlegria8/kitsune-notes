<?php
/**
 * Versión en texto plano del correo de confirmación con factura.
 *
 * @var \KitsuneNotes\Core\View $this
 * @var array<string, mixed>     $order
 * @var array<string, mixed>     $invoice
 * @var array<string, mixed>     $doc
 * @var string                   $invoiceUrl
 * @var string                   $orderUrl
 */
$firstName = (string) strtok((string) $doc['buyer']['name'], ' ');

$out = [
    '¡Gracias por tu compra, ' . $firstName . '!',
    '',
    'Hemos recibido tu pedido ' . $order['reference'] . ' y el pago simulado se ha autorizado.',
    'Tu factura es la ' . $invoice['number'] . ' y la tienes copiada más abajo.',
    '',
    'Ver e imprimir la factura:',
    $invoiceUrl,
    '',
    'Consultar el estado del pedido (referencia + este correo):',
    $orderUrl,
    '',
    $this->partial('mail/_factura-texto', ['doc' => $doc]),
    '',
    'Con cariño,',
    'Kitsune, Neko, Tokki y Gom',
    '',
    '-- ',
    !empty($realDelivery)
        ? 'Prototipo académico sin actividad comercial real: pedido, pago y factura ficticios.'
        : 'Prototipo académico: correo de prueba, no entregado a ningún buzón real.',
];

echo implode("\n", $out), "\n";
