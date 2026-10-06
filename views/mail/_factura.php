<?php
/**
 * Factura incrustada en el correo (HTML con estilos en línea).
 *
 * Imprime el documento congelado al expedir la factura, no el pedido
 * actual: por eso lee únicamente de $doc.
 *
 * El correo entero se redacta en el idioma del pedido, que es también el de
 * la factura: los rótulos se traducen con t() y los textos congelados en el
 * documento (método de envío, aviso, nombres de las líneas) se imprimen tal
 * cual. Los importes llevan siempre la moneda de la factura.
 *
 * @var \KitsuneNotes\Core\View $this
 * @var array<string, mixed>     $doc
 */
$seller   = $doc['seller'];
$buyer    = $doc['buyer'];
$tax      = $doc['tax'];
$pay      = $doc['payment'];
$currency = (string) ($doc['currency'] ?? 'EUR');
$th       = 'padding:8px 10px; background:#FFE9F1; border-bottom:2px solid #FFD6E7; font-size:11px; letter-spacing:.06em; text-transform:uppercase; color:#8A4A6A; text-align:left;';
$td       = 'padding:9px 10px; border-bottom:2px dashed #FFD6E7; font-size:14px; vertical-align:top;';
?>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:2px solid #FFD6E7; border-radius:16px; background:#FFFFFF;">
<tr>
<td style="padding:20px;">

    <p style="margin:0 0 4px; font-size:12px; font-weight:bold; letter-spacing:.06em; text-transform:uppercase; color:#4E2E99;"><?= $this->t('Factura de prueba · sin validez fiscal') ?></p>
    <h2 style="margin:0 0 12px; font-size:22px; line-height:1.3; color:#5A2340;"><?= $this->t('Factura {numero}', ['numero' => $doc['number']]) ?></h2>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="font-size:14px; margin-bottom:16px;">
        <tr>
            <td style="padding:1px 14px 1px 0; color:#8A4A6A; font-weight:bold;"><?= $this->t('Fecha de expedición') ?></td>
            <td style="padding:1px 0; font-weight:bold;"><?= $this->date($doc['issued_at'], false) ?></td>
        </tr>
        <tr>
            <td style="padding:1px 14px 1px 0; color:#8A4A6A; font-weight:bold;"><?= $this->t('Fecha de la operación') ?></td>
            <td style="padding:1px 0; font-weight:bold;"><?= $this->date($doc['operation_date'], false) ?></td>
        </tr>
        <tr>
            <td style="padding:1px 14px 1px 0; color:#8A4A6A; font-weight:bold;"><?= $this->t('Pedido') ?></td>
            <td style="padding:1px 0; font-weight:bold;"><?= $this->e($doc['order_reference']) ?></td>
        </tr>
    </table>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:16px;">
        <tr>
            <td width="50%" valign="top" style="padding:0 10px 0 0; font-size:14px; line-height:1.6;">
                <strong style="font-size:11px; letter-spacing:.07em; text-transform:uppercase; color:#8A4A6A;"><?= $this->t('Emisor') ?></strong><br>
                <strong><?= $this->e($seller['name']) ?></strong><br>
                <?= $this->t('NIF {nif}', ['nif' => $seller['tax_id']]) ?><br>
                <?= $this->e($seller['address']) ?><br>
                <?= $this->e($seller['postal_code']) ?> <?= $this->e($seller['city']) ?> (<?= $this->e($seller['province']) ?>)
            </td>
            <td width="50%" valign="top" style="padding:0 0 0 10px; font-size:14px; line-height:1.6;">
                <strong style="font-size:11px; letter-spacing:.07em; text-transform:uppercase; color:#8A4A6A;"><?= $this->t('Cliente') ?></strong><br>
                <strong><?= $this->e($buyer['name']) ?></strong><br>
                <?= $this->e($buyer['email']) ?><br>
                <?= $this->e($buyer['address']) ?><br>
                <?= $this->e($buyer['postal_code']) ?> <?= $this->e($buyer['city']) ?> (<?= $this->e($buyer['province']) ?>)
            </td>
        </tr>
    </table>

    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse; margin-bottom:14px;">
        <caption style="position:absolute; left:-9999px;"><?= $this->t('Líneas de la factura {numero}', ['numero' => $doc['number']]) ?></caption>
        <thead>
        <tr>
            <th scope="col" style="<?= $th ?>"><?= $this->t('Concepto') ?></th>
            <th scope="col" style="<?= $th ?> text-align:right;"><?= $this->t('Uds.') ?></th>
            <th scope="col" style="<?= $th ?> text-align:right;"><?= $this->t('Precio') ?></th>
            <th scope="col" style="<?= $th ?> text-align:right;"><?= $this->t('Importe') ?></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($doc['lines'] as $line): ?>
            <tr>
                <td style="<?= $td ?>">
                    <strong><?= $this->e($line['name']) ?></strong><br>
                    <span style="font-size:12px; color:#8A4A6A;"><?= $this->e($line['sku']) ?> · <?= $this->e($line['design_line']) ?></span>
                </td>
                <td style="<?= $td ?> text-align:right;"><?= (int) $line['quantity'] ?></td>
                <td style="<?= $td ?> text-align:right; white-space:nowrap;"><?= $this->money((int) $line['unit_price_cents'], $currency) ?></td>
                <td style="<?= $td ?> text-align:right; white-space:nowrap;"><?= $this->money((int) $line['total_cents'], $currency) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td width="40%" style="font-size:12px;">&nbsp;</td>
            <td width="60%">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:14px;">
                    <tr>
                        <td style="padding:3px 0;"><?= $this->t('Artículos') ?></td>
                        <td align="right" style="padding:3px 0; white-space:nowrap;"><?= $this->money((int) $doc['items_total_cents'], $currency) ?></td>
                    </tr>
                    <?php if ((int) $doc['discount']['cents'] > 0): ?>
                        <tr>
                            <td style="padding:3px 0; color:#1F6B4F; font-weight:bold;"><?= $this->t('Descuento {codigo}', ['codigo' => $doc['discount']['code']]) ?></td>
                            <td align="right" style="padding:3px 0; color:#1F6B4F; font-weight:bold; white-space:nowrap;">&minus;<?= $this->money((int) $doc['discount']['cents'], $currency) ?></td>
                        </tr>
                    <?php endif; ?>
                    <tr>
                        <td style="padding:3px 0;"><?= $this->e($doc['shipping']['label']) ?></td>
                        <td align="right" style="padding:3px 0; white-space:nowrap;"><?= (int) $doc['shipping']['cents'] === 0 ? $this->t('Gratis') : $this->money((int) $doc['shipping']['cents'], $currency) ?></td>
                    </tr>
                    <?php if ((int) $doc['giftwrap_cents'] > 0): ?>
                        <tr>
                            <td style="padding:3px 0;"><?= $this->t('Envoltorio furoshiki') ?></td>
                            <td align="right" style="padding:3px 0; white-space:nowrap;"><?= $this->money((int) $doc['giftwrap_cents'], $currency) ?></td>
                        </tr>
                    <?php endif; ?>
                    <tr>
                        <td style="padding:8px 0 3px; border-top:3px solid #5A2340; font-size:17px; font-weight:bold;"><?= $this->t('Total (IVA incluido)') ?></td>
                        <td align="right" style="padding:8px 0 3px; border-top:3px solid #5A2340; font-size:17px; font-weight:bold; white-space:nowrap;"><?= $this->money((int) $doc['total_cents'], $currency) ?></td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:14px; background:#FFF5F8; border:2px solid #FFD6E7; border-radius:12px; font-size:13px;">
        <tr>
            <td style="padding:10px 14px; color:#8A4A6A; font-weight:bold;"><?= $this->t('Desglose de IVA') ?></td>
            <td style="padding:10px 14px;" align="right"><?= $this->t('Base imponible') ?> <strong><?= $this->money((int) $tax['base_cents'], $currency) ?></strong></td>
            <td style="padding:10px 14px;" align="right"><?= $this->t('IVA {porcentaje} %', ['porcentaje' => number_format((float) $tax['rate'] * 100, 0, ',', '.')]) ?> <strong><?= $this->money((int) $tax['tax_cents'], $currency) ?></strong></td>
        </tr>
    </table>

    <?php if (!empty($doc['fx'])): ?>
        <?php /* Solo en las facturas que no están en euros. El Reglamento de facturación (art. 12 del
                 RD 1619/2012) permite facturar en cualquier moneda siempre que la cuota del impuesto
                 se exprese en euros: por eso se indican el tipo de cambio aplicado y el contravalor
                 en euros de la cuota de IVA y del total. En una factura en euros no se imprime nada. */ ?>
        <p style="margin:14px 0 0; font-size:13px; color:#8A4A6A;">
            <?= $this->t('Tipo de cambio aplicado: {tipo} (tipo fijo de demostración).', ['tipo' => $this->app()->currency()->rateLabel($currency, (int) $doc['fx']['rate_micros'])]) ?><br>
            <?= $this->t('Contravalor en euros: cuota de IVA {iva}, total {total}.', ['iva' => $this->money((int) $doc['fx']['tax_base_cents'], 'EUR'), 'total' => $this->money((int) $doc['fx']['total_base_cents'], 'EUR')]) ?>
        </p>
    <?php endif; ?>

    <p style="margin:14px 0 0; font-size:13px; color:#8A4A6A;">
        <?= $this->th('Pago simulado con {marca} &bull;&bull;&bull;&bull; {ultimos} (referencia {referencia}, autorización {autorizacion}).', [
            'marca'        => $pay['card_brand'],
            'ultimos'      => $pay['card_last4'],
            'referencia'   => $pay['reference'],
            'autorizacion' => $pay['authorization'],
        ]) ?>
    </p>
    <p style="margin:8px 0 0; font-size:12px; color:#8A4A6A;"><?= $this->e($doc['notice']) ?></p>

</td>
</tr>
</table>
