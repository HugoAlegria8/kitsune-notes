<?php
/**
 * Correo: acuse de recibo de una solicitud de soporte.
 *
 * @var \KitsuneNotes\Core\View $this
 * @var array<string, mixed>     $ticket
 * @var string                   $typeLabel tipo de solicitud, ya traducido
 * @var array<string, mixed>|null $order
 * @var string|null              $orderUrl
 */
$firstName = (string) strtok((string) $ticket['customer_name'], ' ');
// Estilos que van dentro de una frase: se pasan como marcador {estilo} para
// que el CSS no forme parte del texto que se traduce.
$codeStyle = 'font-family:Consolas,Menlo,monospace;';
$linkStyle = 'color:#BF2A5D; font-weight:bold;';
?>
<h1 style="margin:0 0 10px; font-size:26px; line-height:1.3; color:#5A2340;">
    <?= $this->t('¡Recibido, {nombre}! Gom se pone con ello', ['nombre' => $firstName]) ?> <span style="color:#D6336C;">&#9825;</span>
</h1>

<p style="margin:0 0 16px;">
    <?= $this->th('Hemos registrado tu solicitud con la referencia <strong style="{estilo}">{referencia}</strong>.', ['referencia' => $ticket['reference'], 'estilo' => $codeStyle]) ?>
    <?= $this->t('El equipo la revisará lo antes posible; guarda esta referencia para el seguimiento.') ?>
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#FFF5F8; border:2px solid #FFD6E7; border-radius:16px; margin-bottom:18px;">
    <tr>
        <td style="padding:14px 18px; font-size:14px; line-height:1.7;">
            <strong style="font-size:11px; letter-spacing:.07em; text-transform:uppercase; color:#8A4A6A;"><?= $this->t('Tu solicitud') ?></strong><br>
            <strong><?= $this->t('Tipo:') ?></strong> <?= $this->e($typeLabel) ?><br>
            <strong><?= $this->t('Asunto:') ?></strong> <?= $this->e($ticket['subject']) ?><br>
            <?php if ($order !== null): ?>
                <strong><?= $this->t('Pedido:') ?></strong> <?= $this->e($order['reference']) ?><br>
            <?php endif; ?>
            <strong><?= $this->t('Mensaje:') ?></strong><br>
            <span style="color:#8A4A6A;"><?= nl2br($this->e($ticket['message'])) ?></span>
        </td>
    </tr>
</table>

<?php if ($order !== null && !empty($orderUrl)): ?>
    <p style="margin:0; font-size:14px;">
        <?= $this->th('Puedes consultar el estado del pedido <a href="{url}" style="{estilo}">{referencia}</a> cuando quieras.', ['url' => $orderUrl, 'referencia' => $order['reference'], 'estilo' => $linkStyle]) ?>
    </p>
<?php endif; ?>
