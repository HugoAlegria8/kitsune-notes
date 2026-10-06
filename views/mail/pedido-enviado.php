<?php
/**
 * Correo: el pedido ha salido del almacén.
 *
 * @var \KitsuneNotes\Core\View $this
 * @var array<string, mixed>     $order
 * @var array<string, mixed>|null $invoice
 * @var list<array<string, mixed>> $lines
 * @var string                   $tracking número de seguimiento (simulado)
 * @var string                   $delivery plazo estimado
 * @var string                   $orderUrl
 * @var string|null              $invoiceUrl
 */
$firstName = (string) strtok((string) $order['shipping_name'], ' ');
?>
<h1 style="margin:0 0 10px; font-size:26px; line-height:1.3; color:#5A2340;">
    ¡Tu pedido va en camino, <?= $this->e($firstName) ?>! <span style="color:#D6336C;">&#10047;</span>
</h1>

<p style="margin:0 0 16px;">
    El pedido <strong><?= $this->e($order['reference']) ?></strong> acaba de salir del almacén.
    Llegará <strong><?= $this->e($delivery) ?></strong> (envío <?= $this->e($this->shippingLabel((string) $order['shipping_method'])) ?>, simulado).
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#F1E8FF; border:2px solid #C9A7FF; border-radius:16px; margin-bottom:18px;">
    <tr>
        <td style="padding:14px 18px; font-size:14px; line-height:1.7;">
            <strong style="font-size:11px; letter-spacing:.07em; text-transform:uppercase; color:#4E2E99;">Seguimiento (simulado)</strong><br>
            Transportista: Kitsune Express<br>
            Código: <strong style="font-family:Consolas,Menlo,monospace; letter-spacing:.04em;"><?= $this->e($tracking) ?></strong>
        </td>
    </tr>
</table>

<?php if ($lines !== []): ?>
    <p style="margin:0 0 6px; font-size:12px; font-weight:bold; letter-spacing:.07em; text-transform:uppercase; color:#8A4A6A;">Qué lleva la caja</p>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:18px; font-size:14px;">
        <?php foreach ($lines as $line): ?>
            <tr>
                <td style="padding:7px 0; border-bottom:2px dashed #FFD6E7;">
                    <strong><?= $this->e($line['name']) ?></strong><br>
                    <span style="font-size:12px; color:#8A4A6A;"><?= $this->e($line['sku']) ?></span>
                </td>
                <td align="right" style="padding:7px 0; border-bottom:2px dashed #FFD6E7; white-space:nowrap;">&times; <?= (int) $line['quantity'] ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

<p style="margin:0 0 16px; font-size:14px; line-height:1.7;">
    <strong style="font-size:11px; letter-spacing:.07em; text-transform:uppercase; color:#8A4A6A;">Dirección de entrega</strong><br>
    <?= $this->e($order['shipping_name']) ?><br>
    <?= $this->e($order['shipping_address']) ?><br>
    <?= $this->e($order['shipping_postal_code']) ?> <?= $this->e($order['shipping_city']) ?> (<?= $this->e($order['shipping_province']) ?>)
</p>

<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 8px;">
    <tr>
        <td style="background:#D6336C; border:3px solid #5A2340; border-radius:999px;">
            <a href="<?= $this->e($orderUrl) ?>"
               style="display:inline-block; padding:11px 24px; color:#FFFFFF; font-weight:bold; font-size:15px; text-decoration:none;">
                Ver el estado del pedido
            </a>
        </td>
    </tr>
</table>

<?php if (!empty($invoiceUrl) && $invoice !== null): ?>
    <p style="margin:12px 0 0; font-size:14px;">
        Tu factura <?= $this->e($invoice['number']) ?> sigue disponible:
        <a href="<?= $this->e($invoiceUrl) ?>" style="color:#BF2A5D; font-weight:bold;">verla o imprimirla</a>.
    </p>
<?php endif; ?>
