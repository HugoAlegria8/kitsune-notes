<?php
/**
 * Correo: comprobación del envío real (php bin/probar-correo.php).
 *
 * @var \KitsuneNotes\Core\View $this
 * @var string                   $host   servidor SMTP configurado
 * @var string                   $from   remitente configurado
 * @var string                   $sentAt fecha y hora del envío
 */
?>
<h1 style="margin:0 0 10px; font-size:26px; line-height:1.3; color:#5A2340;">
    ¡Funciona! Tokki ha llegado a tu bandeja <span style="color:#D6336C;">&#9825;</span>
</h1>

<p style="margin:0 0 16px;">
    Si estás leyendo este mensaje, Kitsune Notes ya puede enviar de verdad el correo de
    confirmación y la factura de los pedidos a las direcciones que has autorizado.
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#FFF5F8; border:2px solid #FFD6E7; border-radius:16px; margin-bottom:18px;">
    <tr>
        <td style="padding:14px 18px; font-size:14px; line-height:1.7;">
            <strong style="font-size:11px; letter-spacing:.07em; text-transform:uppercase; color:#8A4A6A;">Datos del envío</strong><br>
            <strong>Servidor SMTP:</strong> <?= $this->e($host) ?><br>
            <strong>Remitente:</strong> <?= $this->e($from) ?><br>
            <strong>Enviado:</strong> <?= $this->e($sentAt) ?>
        </td>
    </tr>
</table>

<p style="margin:0; font-size:14px;">
    Este mensaje solo comprueba la configuración; no corresponde a ningún pedido.
</p>
