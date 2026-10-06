<?php
/**
 * Documento de factura para pantalla e impresión.
 *
 * Imprime la copia congelada al expedir la factura ($doc), no el estado
 * actual del pedido ni de los datos maestros: una factura emitida no cambia.
 *
 * Idioma y moneda: quien incluye esta plantilla la pinta dentro de
 * $this->inLocale($doc['locale'], …), así que los rótulos, las fechas y los
 * separadores de los importes salen en el idioma de la factura, no en el de
 * quien la mira. Los textos congelados en el documento (método de envío,
 * país, avisos y nombres de las líneas) ya están en ese idioma y se imprimen
 * tal cual. Todos los importes se pintan en la moneda de la factura.
 *
 * @var \KitsuneNotes\Core\View $this
 * @var array<string, mixed>     $doc
 * @var string|null              $docLang valor de «lang» cuando el documento no está en el idioma de la página
 */
$seller   = $doc['seller'];
$buyer    = $doc['buyer'];
$tax      = $doc['tax'];
$pay      = $doc['payment'];
$rate     = number_format((float) $tax['rate'] * 100, 0, ',', '.');
$currency = (string) ($doc['currency'] ?? 'EUR');
?>
<article class="factura" aria-labelledby="factura-numero"<?= !empty($docLang) ? ' lang="' . $this->e($docLang) . '"' : '' ?>>
    <p class="factura__cinta"><?= $this->t('Factura de prueba · sin validez fiscal') ?></p>

    <header class="factura__cabecera">
        <div class="factura__marca">
            <img src="<?= $this->asset('assets/img/brand/kitsune.svg') ?>" alt="" width="64" height="60">
            <div>
                <p class="factura__empresa"><?= $this->e($seller['name']) ?></p>
                <p class="factura__sub">
                    <?= $this->t('NIF {nif}', ['nif' => $seller['tax_id']]) ?><br>
                    <?= $this->e($seller['address']) ?><br>
                    <?= $this->e($seller['postal_code']) ?> <?= $this->e($seller['city']) ?> (<?= $this->e($seller['province']) ?>)
                </p>
            </div>
        </div>

        <div class="factura__datos">
            <p class="factura__titulo"><?= $this->t('Factura') ?></p>
            <p class="factura__numero" id="factura-numero"><?= $this->e($doc['number']) ?></p>
            <dl>
                <dt><?= $this->t('Fecha de expedición') ?></dt>
                <dd><?= $this->date($doc['issued_at'], false) ?></dd>
                <dt><?= $this->t('Fecha de la operación') ?></dt>
                <dd><?= $this->date($doc['operation_date'], false) ?></dd>
                <dt><?= $this->t('Pedido') ?></dt>
                <dd><?= $this->e($doc['order_reference']) ?></dd>
            </dl>
        </div>
    </header>

    <div class="factura__partes">
        <section class="factura__parte" aria-labelledby="factura-emisor">
            <h2 id="factura-emisor"><?= $this->t('Emisor') ?></h2>
            <p>
                <strong><?= $this->e($seller['name']) ?></strong><br>
                <?= $this->t('NIF {nif}', ['nif' => $seller['tax_id']]) ?><br>
                <?= $this->e($seller['address']) ?><br>
                <?= $this->e($seller['postal_code']) ?> <?= $this->e($seller['city']) ?>, <?= $this->e($seller['country']) ?><br>
                <?= $this->e($seller['email']) ?>
            </p>
        </section>

        <section class="factura__parte" aria-labelledby="factura-cliente">
            <h2 id="factura-cliente"><?= $this->t('Cliente') ?></h2>
            <p>
                <strong><?= $this->e($buyer['name']) ?></strong><br>
                <?= $this->e($buyer['address']) ?><br>
                <?= $this->e($buyer['postal_code']) ?> <?= $this->e($buyer['city']) ?> (<?= $this->e($buyer['province']) ?>), <?= $this->e($buyer['country']) ?><br>
                <?= $this->e($buyer['email']) ?>
            </p>
        </section>
    </div>

    <div class="tabla-envoltorio">
        <table class="factura__tabla">
            <caption class="solo-lectores"><?= $this->t('Conceptos facturados en la factura {numero}', ['numero' => $doc['number']]) ?></caption>
            <thead>
            <tr>
                <th scope="col"><?= $this->t('Concepto') ?></th>
                <th scope="col" class="num"><?= $this->t('Uds.') ?></th>
                <th scope="col" class="num"><?= $this->t('Precio (IVA incl.)') ?></th>
                <th scope="col" class="num"><?= $this->t('Importe') ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($doc['lines'] as $line): ?>
                <tr>
                    <td>
                        <strong><?= $this->e($line['name']) ?></strong><br>
                        <span class="factura__detalle"><?= $this->e($line['sku']) ?> · <?= $this->e($line['design_line']) ?></span>
                    </td>
                    <td class="num"><?= (int) $line['quantity'] ?></td>
                    <td class="num"><?= $this->money((int) $line['unit_price_cents'], $currency) ?></td>
                    <td class="num"><?= $this->money((int) $line['total_cents'], $currency) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="factura__totales">
        <section class="factura__bloque" aria-labelledby="factura-fiscal">
            <h2 id="factura-fiscal"><?= $this->t('Desglose de IVA') ?></h2>
            <div class="factura__fila">
                <span><?= $this->t('Base imponible') ?></span>
                <span><?= $this->money((int) $tax['base_cents'], $currency) ?></span>
            </div>
            <div class="factura__fila">
                <span><?= $this->t('IVA {porcentaje} %', ['porcentaje' => $rate]) ?></span>
                <span><?= $this->money((int) $tax['tax_cents'], $currency) ?></span>
            </div>
            <div class="factura__fila factura__fila--suma">
                <span><?= $this->t('Total factura') ?></span>
                <span><?= $this->money((int) $doc['total_cents'], $currency) ?></span>
            </div>
        </section>

        <section class="factura__bloque" aria-labelledby="factura-importes">
            <h2 id="factura-importes"><?= $this->t('Importes') ?></h2>
            <div class="factura__fila">
                <span><?= $this->t('Artículos') ?></span>
                <span><?= $this->money((int) $doc['items_total_cents'], $currency) ?></span>
            </div>
            <?php if ((int) $doc['discount']['cents'] > 0): ?>
                <div class="factura__fila factura__fila--descuento">
                    <span><?= $this->t('Descuento {codigo}', ['codigo' => $doc['discount']['code']]) ?></span>
                    <span>−<?= $this->money((int) $doc['discount']['cents'], $currency) ?></span>
                </div>
            <?php endif; ?>
            <div class="factura__fila">
                <span><?= $this->e($doc['shipping']['label']) ?></span>
                <span><?= (int) $doc['shipping']['cents'] === 0 ? $this->t('Gratis') : $this->money((int) $doc['shipping']['cents'], $currency) ?></span>
            </div>
            <?php if ((int) $doc['giftwrap_cents'] > 0): ?>
                <div class="factura__fila">
                    <span><?= $this->t('Envoltorio furoshiki') ?></span>
                    <span><?= $this->money((int) $doc['giftwrap_cents'], $currency) ?></span>
                </div>
            <?php endif; ?>
            <div class="factura__fila factura__fila--total">
                <span><?= $this->t('Total (IVA incluido)') ?></span>
                <span><?= $this->money((int) $doc['total_cents'], $currency) ?></span>
            </div>
        </section>
    </div>

    <?php if (!empty($doc['fx'])): ?>
        <?php /* Solo en las facturas que no están en euros. El Reglamento de facturación (art. 12 del
                 RD 1619/2012) permite facturar en cualquier moneda siempre que la cuota del impuesto
                 se exprese en euros: por eso se indican el tipo de cambio aplicado y el contravalor
                 en euros de la cuota de IVA y del total. En una factura en euros no se imprime nada. */ ?>
        <p class="factura__pago">
            <?= $this->t('Tipo de cambio aplicado: {tipo} (tipo fijo de demostración).', ['tipo' => $this->app()->currency()->rateLabel($currency, (int) $doc['fx']['rate_micros'])]) ?><br>
            <?= $this->t('Contravalor en euros: cuota de IVA {iva}, total {total}.', ['iva' => $this->money((int) $doc['fx']['tax_base_cents'], 'EUR'), 'total' => $this->money((int) $doc['fx']['total_base_cents'], 'EUR')]) ?>
        </p>
    <?php endif; ?>

    <p class="factura__pago">
        <?= $this->t('Pago simulado con {marca} •••• {ultimos} · referencia {referencia} · autorización {autorizacion} · {fecha}', [
            'marca'        => $pay['card_brand'],
            'ultimos'      => $pay['card_last4'],
            'referencia'   => $pay['reference'],
            'autorizacion' => $pay['authorization'],
            'fecha'        => $this->date($pay['processed_at']),
        ]) ?>
    </p>

    <footer class="factura__pie">
        <p><?= $this->e($doc['notice']) ?></p>
        <p><?= $this->e($seller['fictional']) ?></p>
    </footer>
</article>
