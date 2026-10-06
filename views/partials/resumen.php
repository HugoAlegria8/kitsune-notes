<?php
/**
 * Resumen económico del pedido (carrito, checkout y pago).
 *
 * Los importes de $summary ya vienen en la moneda de la compra y
 * $this->money() los pinta en ella, porque es la del idioma activo.
 *
 * @var \KitsuneNotes\Core\View $this
 * @var array<string, mixed> $summary
 */
?>
<h2><?= $this->t('Tu cuenta') ?></h2>

<?php if (!empty($summary['free_shipping_remaining'])): ?>
    <p class="progreso-envio">
        <?= $this->t('¡Te faltan {importe} para el envío gratis!', ['importe' => $this->money((int) $summary['free_shipping_remaining'])]) ?>
    </p>
<?php endif; ?>

<div class="resumen__fila">
    <span><?= $this->tn('{n} artículo', '{n} artículos', (int) $summary['unit_count']) ?></span>
    <span><?= $this->money((int) $summary['items_total_cents']) ?></span>
</div>

<?php if ((int) $summary['discount_cents'] > 0): ?>
    <div class="resumen__fila resumen__fila--descuento">
        <span><?= $this->t('Descuento {codigo}', ['codigo' => $summary['coupon_code']]) ?></span>
        <span>−<?= $this->money((int) $summary['discount_cents']) ?></span>
    </div>
<?php endif; ?>

<div class="resumen__fila">
    <span><?= $this->e($summary['shipping_label']) ?></span>
    <span>
        <?php if ((int) $summary['shipping_cents'] === 0): ?>
            <strong style="color:var(--exito)"><?= $this->t('Gratis') ?></strong>
        <?php else: ?>
            <?= $this->money((int) $summary['shipping_cents']) ?>
        <?php endif; ?>
    </span>
</div>

<?php if ((int) $summary['giftwrap_cents'] > 0): ?>
    <div class="resumen__fila">
        <span><?= $this->t('Envoltorio furoshiki') ?></span>
        <span><?= $this->money((int) $summary['giftwrap_cents']) ?></span>
    </div>
<?php endif; ?>

<div class="resumen__fila resumen__fila--suave">
    <span><?= $this->t('Base imponible') ?></span>
    <span><?= $this->money((int) $summary['taxable_base_cents']) ?></span>
</div>

<div class="resumen__fila resumen__fila--suave">
    <span><?= $this->t('IVA ({porcentaje} %)', ['porcentaje' => (int) round((float) $summary['tax_rate'] * 100)]) ?></span>
    <span><?= $this->money((int) $summary['tax_cents']) ?></span>
</div>

<div class="resumen__fila resumen__fila--total">
    <span><?= $this->t('Total') ?></span>
    <span><?= $this->money((int) $summary['total_cents']) ?></span>
</div>
