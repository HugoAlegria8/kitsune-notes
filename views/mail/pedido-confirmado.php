<?php
/**
 * Correo: confirmación del pedido con la factura incluida.
 *
 * @var \KitsuneNotes\Core\View $this
 * @var array<string, mixed>     $order
 * @var array<string, mixed>     $invoice
 * @var array<string, mixed>     $doc
 * @var string                   $invoiceUrl enlace firmado a la factura en línea
 * @var string                   $orderUrl
 */
$firstName = (string) strtok((string) $doc['buyer']['name'], ' ');
// Estilo de los enlaces dentro de una frase: va como marcador {estilo} para
// que el CSS no forme parte del texto que se traduce.
$linkStyle = 'color:#BF2A5D; font-weight:bold;';
?>
<h1 style="margin:0 0 10px; font-size:26px; line-height:1.3; color:#5A2340;">
    <?= $this->t('¡Gracias por tu compra, {nombre}!', ['nombre' => $firstName]) ?> <span style="color:#D6336C;">&#9825;</span>
</h1>

<p style="margin:0 0 14px;">
    <?= $this->th('Hemos recibido tu pedido <strong>{referencia}</strong> y el pago simulado se ha autorizado. Aquí abajo tienes tu factura <strong>{factura}</strong>; también puedes abrirla en línea para imprimirla o guardarla como PDF.', ['referencia' => $order['reference'], 'factura' => $invoice['number']]) ?>
</p>

<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 10px;">
    <tr>
        <td style="background:#D6336C; border:3px solid #5A2340; border-radius:999px;">
            <a href="<?= $this->e($invoiceUrl) ?>"
               style="display:inline-block; padding:11px 24px; color:#FFFFFF; font-weight:bold; font-size:15px; text-decoration:none;">
                <?= $this->t('Ver e imprimir la factura') ?>
            </a>
        </td>
    </tr>
</table>

<p style="margin:0 0 22px; font-size:14px;">
    <?= $this->th('¿Quieres seguir el pedido? <a href="{url}" style="{estilo}">Consulta su estado</a> con la referencia y este correo electrónico.', ['url' => $orderUrl, 'estilo' => $linkStyle]) ?>
</p>

<?= $this->partial('mail/_factura', ['doc' => $doc]) ?>

<p style="margin:22px 0 0; font-size:14px;">
    <?= $this->t('Con cariño,') ?><br>
    <strong><?= $this->t('Kitsune, Neko, Tokki y Gom') ?></strong> <span style="color:#D6336C;">&#9825;</span>
</p>
