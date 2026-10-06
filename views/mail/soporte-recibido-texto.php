<?php
/**
 * Versión en texto plano del acuse de recibo de soporte.
 *
 * @var \KitsuneNotes\Core\View $this
 * @var array<string, mixed>     $ticket
 * @var string                   $typeLabel
 * @var array<string, mixed>|null $order
 * @var string|null              $orderUrl
 */
$firstName = (string) strtok((string) $ticket['customer_name'], ' ');

$out = [
    '¡Recibido, ' . $firstName . '! Gom se pone con ello.',
    '',
    'Hemos registrado tu solicitud con la referencia ' . $ticket['reference'] . '.',
    'El equipo la revisará lo antes posible; guarda esta referencia para el seguimiento.',
    '',
    'Tipo: ' . $typeLabel,
    'Asunto: ' . $ticket['subject'],
];

if ($order !== null) {
    $out[] = 'Pedido: ' . $order['reference'];
}

$out[] = 'Mensaje:';
$out[] = $ticket['message'];

if ($order !== null && !empty($orderUrl)) {
    $out[] = '';
    $out[] = 'Consultar el estado del pedido:';
    $out[] = $orderUrl;
}

$out[] = '';
$out[] = '-- ';
$out[] = !empty($realDelivery)
    ? 'Prototipo académico sin actividad comercial real: pedido, pago y factura ficticios.'
    : 'Prototipo académico: correo de prueba, no entregado a ningún buzón real.';

echo implode("\n", $out), "\n";
