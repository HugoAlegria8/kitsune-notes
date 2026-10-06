<?php
/**
 * Versión en texto plano del correo de comprobación del envío real.
 *
 * @var \KitsuneNotes\Core\View $this
 * @var string                   $host
 * @var string                   $from
 * @var string                   $sentAt
 * @var bool                     $realDelivery
 */
$out = [
    '¡Funciona! Tokki ha llegado a tu bandeja.',
    '',
    'Si estás leyendo este mensaje, Kitsune Notes ya puede enviar de verdad el correo de',
    'confirmación y la factura de los pedidos a las direcciones que has autorizado.',
    '',
    'Servidor SMTP: ' . $host,
    'Remitente: ' . $from,
    'Enviado: ' . $sentAt,
    '',
    'Este mensaje solo comprueba la configuración; no corresponde a ningún pedido.',
    '',
    '-- ',
    !empty($realDelivery)
        ? 'Prototipo académico sin actividad comercial real: pedido, pago y factura ficticios.'
        : 'Prototipo académico: correo de prueba, no entregado a ningún buzón real.',
];

echo implode("\n", $out), "\n";
