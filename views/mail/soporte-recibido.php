<?php
/**
 * Correo: acuse de recibo de una solicitud de soporte.
 *
 * @var \KitsuneNotes\Core\View $this
 * @var array<string, mixed>     $ticket
 * @var string                   $typeLabel
 * @var array<string, mixed>|null $order
 * @var string|null              $orderUrl
 */
$firstName = (string) strtok((string) $ticket['customer_name'], ' ');
?>
<h1 style="margin:0 0 10px; font-size:26px; line-height:1.3; color:#5A2340;">
    ¡Recibido, <?= $this->e($firstName) ?>! Gom se pone con ello <span style="color:#D6336C;">&#9825;</span>
</h1>

<p style="margin:0 0 16px;">
    Hemos registrado tu solicitud con la referencia
    <strong style="font-family:Consolas,Menlo,monospace;"><?= $this->e($ticket['reference']) ?></strong>.
    El equipo la revisará lo antes posible; guarda esta referencia para el seguimiento.
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#FFF5F8; border:2px solid #FFD6E7; border-radius:16px; margin-bottom:18px;">
    <tr>
        <td style="padding:14px 18px; font-size:14px; line-height:1.7;">
            <strong style="font-size:11px; letter-spacing:.07em; text-transform:uppercase; color:#8A4A6A;">Tu solicitud</strong><br>
            <strong>Tipo:</strong> <?= $this->e($typeLabel) ?><br>
            <strong>Asunto:</strong> <?= $this->e($ticket['subject']) ?><br>
            <?php if ($order !== null): ?>
                <strong>Pedido:</strong> <?= $this->e($order['reference']) ?><br>
            <?php endif; ?>
            <strong>Mensaje:</strong><br>
            <span style="color:#8A4A6A;"><?= nl2br($this->e($ticket['message'])) ?></span>
        </td>
    </tr>
</table>

<?php if ($order !== null && !empty($orderUrl)): ?>
    <p style="margin:0; font-size:14px;">
        Puedes consultar el estado del pedido
        <a href="<?= $this->e($orderUrl) ?>" style="color:#BF2A5D; font-weight:bold;"><?= $this->e($order['reference']) ?></a>
        cuando quieras.
    </p>
<?php endif; ?>
