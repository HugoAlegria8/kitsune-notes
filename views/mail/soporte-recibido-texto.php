<?php
/**
 * Versión en texto plano del acuse de recibo de soporte.
 *
 * Es text/plain: los textos se traducen con $tr() y NO con $this->t(), que
 * además escapa HTML.
 *
 * @var \KitsuneNotes\Core\View $this
 * @var array<string, mixed>     $ticket
 * @var string                   $typeLabel
 * @var array<string, mixed>|null $order
 * @var string|null              $orderUrl
 */
$tr = fn (string $text, array $params = []): string => $this->app()->translator()->get($text, $params);

$firstName = (string) strtok((string) $ticket['customer_name'], ' ');

$out = [
    $tr('¡Recibido, {nombre}! Gom se pone con ello.', ['nombre' => $firstName]),
    '',
    $tr('Hemos registrado tu solicitud con la referencia {referencia}.', ['referencia' => $ticket['reference']]),
    $tr('El equipo la revisará lo antes posible; guarda esta referencia para el seguimiento.'),
    '',
    $tr('Tipo: {tipo}', ['tipo' => $typeLabel]),
    $tr('Asunto: {asunto}', ['asunto' => $ticket['subject']]),
];

if ($order !== null) {
    $out[] = $tr('Pedido: {referencia}', ['referencia' => $order['reference']]);
}

$out[] = $tr('Mensaje:');
$out[] = $ticket['message'];

if ($order !== null && !empty($orderUrl)) {
    $out[] = '';
    $out[] = $tr('Consultar el estado del pedido:');
    $out[] = $orderUrl;
}

$out[] = '';
$out[] = '-- ';
$out[] = !empty($realDelivery)
    ? $tr('Prototipo académico sin actividad comercial real: pedido, pago y factura ficticios.')
    : $tr('Prototipo académico: correo de prueba, no entregado a ningún buzón real.');

echo implode("\n", $out), "\n";
