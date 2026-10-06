<?php
/**
 * Versión en texto plano del correo de confirmación con factura.
 *
 * Es text/plain: los textos se traducen con $tr() y NO con $this->t(), que
 * además escapa HTML.
 *
 * @var \KitsuneNotes\Core\View $this
 * @var array<string, mixed>     $order
 * @var array<string, mixed>     $invoice
 * @var array<string, mixed>     $doc
 * @var string                   $invoiceUrl
 * @var string                   $orderUrl
 */
$tr = fn (string $text, array $params = []): string => $this->app()->translator()->get($text, $params);

$firstName = (string) strtok((string) $doc['buyer']['name'], ' ');

$out = [
    $tr('¡Gracias por tu compra, {nombre}!', ['nombre' => $firstName]),
    '',
    $tr('Hemos recibido tu pedido {referencia} y el pago simulado se ha autorizado.', ['referencia' => $order['reference']]),
    $tr('Tu factura es la {factura} y la tienes copiada más abajo.', ['factura' => $invoice['number']]),
    '',
    $tr('Ver e imprimir la factura:'),
    $invoiceUrl,
    '',
    $tr('Consultar el estado del pedido (referencia + este correo):'),
    $orderUrl,
    '',
    $this->partial('mail/_factura-texto', ['doc' => $doc]),
    '',
    $tr('Con cariño,'),
    $tr('Kitsune, Neko, Tokki y Gom'),
    '',
    '-- ',
    !empty($realDelivery)
        ? $tr('Prototipo académico sin actividad comercial real: pedido, pago y factura ficticios.')
        : $tr('Prototipo académico: correo de prueba, no entregado a ningún buzón real.'),
];

echo implode("\n", $out), "\n";
