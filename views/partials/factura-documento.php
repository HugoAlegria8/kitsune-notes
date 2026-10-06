<?php
/**
 * Documento de factura para pantalla e impresión.
 *
 * Imprime la copia congelada al expedir la factura ($doc), no el estado
 * actual del pedido ni de los datos maestros: una factura emitida no cambia.
 *
 * @var \KitsuneNotes\Core\View $this
 * @var array<string, mixed>     $doc
 */
$seller = $doc['seller'];
$buyer  = $doc['buyer'];
$tax    = $doc['tax'];
$pay    = $doc['payment'];
$rate   = number_format((float) $tax['rate'] * 100, 0, ',', '.');
?>
<article class="factura" aria-labelledby="factura-numero">
    <p class="factura__cinta">Factura de prueba · sin validez fiscal</p>

    <header class="factura__cabecera">
        <div class="factura__marca">
            <img src="<?= $this->asset('assets/img/brand/kitsune.svg') ?>" alt="" width="64" height="60">
            <div>
                <p class="factura__empresa"><?= $this->e($seller['name']) ?></p>
                <p class="factura__sub">
                    NIF <?= $this->e($seller['tax_id']) ?><br>
                    <?= $this->e($seller['address']) ?><br>
                    <?= $this->e($seller['postal_code']) ?> <?= $this->e($seller['city']) ?> (<?= $this->e($seller['province']) ?>)
                </p>
            </div>
        </div>

        <div class="factura__datos">
            <p class="factura__titulo">Factura</p>
            <p class="factura__numero" id="factura-numero"><?= $this->e($doc['number']) ?></p>
            <dl>
                <dt>Fecha de expedición</dt>
                <dd><?= $this->date($doc['issued_at'], false) ?></dd>
                <dt>Fecha de la operación</dt>
                <dd><?= $this->date($doc['operation_date'], false) ?></dd>
                <dt>Pedido</dt>
                <dd><?= $this->e($doc['order_reference']) ?></dd>
            </dl>
        </div>
    </header>

    <div class="factura__partes">
        <section class="factura__parte" aria-labelledby="factura-emisor">
            <h2 id="factura-emisor">Emisor</h2>
            <p>
                <strong><?= $this->e($seller['name']) ?></strong><br>
                NIF <?= $this->e($seller['tax_id']) ?><br>
                <?= $this->e($seller['address']) ?><br>
                <?= $this->e($seller['postal_code']) ?> <?= $this->e($seller['city']) ?>, <?= $this->e($seller['country']) ?><br>
                <?= $this->e($seller['email']) ?>
            </p>
        </section>

        <section class="factura__parte" aria-labelledby="factura-cliente">
            <h2 id="factura-cliente">Cliente</h2>
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
            <caption class="solo-lectores">Conceptos facturados en la factura <?= $this->e($doc['number']) ?></caption>
            <thead>
            <tr>
                <th scope="col">Concepto</th>
                <th scope="col" class="num">Uds.</th>
                <th scope="col" class="num">Precio (IVA incl.)</th>
                <th scope="col" class="num">Importe</th>
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
                    <td class="num"><?= $this->money((int) $line['unit_price_cents']) ?></td>
                    <td class="num"><?= $this->money((int) $line['total_cents']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="factura__totales">
        <section class="factura__bloque" aria-labelledby="factura-fiscal">
            <h2 id="factura-fiscal">Desglose de IVA</h2>
            <div class="factura__fila">
                <span>Base imponible</span>
                <span><?= $this->money((int) $tax['base_cents']) ?></span>
            </div>
            <div class="factura__fila">
                <span>IVA <?= $this->e($rate) ?> %</span>
                <span><?= $this->money((int) $tax['tax_cents']) ?></span>
            </div>
            <div class="factura__fila factura__fila--suma">
                <span>Total factura</span>
                <span><?= $this->money((int) $doc['total_cents']) ?></span>
            </div>
        </section>

        <section class="factura__bloque" aria-labelledby="factura-importes">
            <h2 id="factura-importes">Importes</h2>
            <div class="factura__fila">
                <span>Artículos</span>
                <span><?= $this->money((int) $doc['items_total_cents']) ?></span>
            </div>
            <?php if ((int) $doc['discount']['cents'] > 0): ?>
                <div class="factura__fila factura__fila--descuento">
                    <span>Descuento <?= $this->e($doc['discount']['code']) ?></span>
                    <span>−<?= $this->money((int) $doc['discount']['cents']) ?></span>
                </div>
            <?php endif; ?>
            <div class="factura__fila">
                <span><?= $this->e($doc['shipping']['label']) ?></span>
                <span><?= (int) $doc['shipping']['cents'] === 0 ? 'Gratis' : $this->money((int) $doc['shipping']['cents']) ?></span>
            </div>
            <?php if ((int) $doc['giftwrap_cents'] > 0): ?>
                <div class="factura__fila">
                    <span>Envoltorio furoshiki</span>
                    <span><?= $this->money((int) $doc['giftwrap_cents']) ?></span>
                </div>
            <?php endif; ?>
            <div class="factura__fila factura__fila--total">
                <span>Total (IVA incluido)</span>
                <span><?= $this->money((int) $doc['total_cents']) ?></span>
            </div>
        </section>
    </div>

    <p class="factura__pago">
        Pago simulado con <?= $this->e($pay['card_brand']) ?> •••• <?= $this->e($pay['card_last4']) ?>
        · referencia <?= $this->e($pay['reference']) ?> · autorización <?= $this->e($pay['authorization']) ?>
        · <?= $this->date($pay['processed_at']) ?>
    </p>

    <footer class="factura__pie">
        <p><?= $this->e($doc['notice']) ?></p>
        <p><?= $this->e($seller['fictional']) ?></p>
    </footer>
</article>
