<?php
/**
 * Resumen económico del pedido (carrito, checkout y pago).
 *
 * @var \KitsuneNotes\Core\View $this
 * @var array<string, mixed> $summary
 */
?>
<h2>Tu cuenta</h2>

<?php if (!empty($summary['free_shipping_remaining'])): ?>
    <p class="progreso-envio">
        ¡Te faltan <?= $this->money((int) $summary['free_shipping_remaining']) ?>
        para el envío gratis!
    </p>
<?php endif; ?>

<div class="resumen__fila">
    <span><?= (int) $summary['unit_count'] ?> <?= (int) $summary['unit_count'] === 1 ? 'artículo' : 'artículos' ?></span>
    <span><?= $this->money((int) $summary['items_total_cents']) ?></span>
</div>

<?php if ((int) $summary['discount_cents'] > 0): ?>
    <div class="resumen__fila resumen__fila--descuento">
        <span>Descuento <?= $this->e($summary['coupon_code']) ?></span>
        <span>−<?= $this->money((int) $summary['discount_cents']) ?></span>
    </div>
<?php endif; ?>

<div class="resumen__fila">
    <span><?= $this->e($summary['shipping_label']) ?></span>
    <span>
        <?php if ((int) $summary['shipping_cents'] === 0): ?>
            <strong style="color:var(--exito)">Gratis</strong>
        <?php else: ?>
            <?= $this->money((int) $summary['shipping_cents']) ?>
        <?php endif; ?>
    </span>
</div>

<?php if ((int) $summary['giftwrap_cents'] > 0): ?>
    <div class="resumen__fila">
        <span>Envoltorio furoshiki</span>
        <span><?= $this->money((int) $summary['giftwrap_cents']) ?></span>
    </div>
<?php endif; ?>

<div class="resumen__fila resumen__fila--suave">
    <span>Base imponible</span>
    <span><?= $this->money((int) $summary['taxable_base_cents']) ?></span>
</div>

<div class="resumen__fila resumen__fila--suave">
    <span>IVA (<?= (int) round((float) $summary['tax_rate'] * 100) ?> %)</span>
    <span><?= $this->money((int) $summary['tax_cents']) ?></span>
</div>

<div class="resumen__fila resumen__fila--total">
    <span>Total</span>
    <span><?= $this->money((int) $summary['total_cents']) ?></span>
</div>
