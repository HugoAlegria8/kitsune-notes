<?php
/**
 * Tarjeta de producto reutilizada en portada, catálogo y relacionados.
 *
 * @var \KitsuneNotes\Core\View $this
 * @var array<string, mixed>    $product
 */
$stock = (int) $product['stock'];
?>
<article class="producto">
    <div class="producto__imagen">
        <a href="<?= $this->url('/producto/' . $product['slug']) ?>" tabindex="-1" aria-hidden="true">
            <img src="<?= $this->asset($product['image_path']) ?>"
                 alt="<?= $this->e($product['name']) ?>" loading="lazy" width="400" height="300">
        </a>
        <?php if ($product['compare_at_cents'] !== null): ?>
            <span class="producto__etiqueta"><?= $this->t('¡Oferta!') ?></span>
        <?php elseif ($stock > 0 && $stock <= 20): ?>
            <span class="producto__etiqueta producto__etiqueta--lila"><?= $this->t('¡Quedan poquitos!') ?></span>
        <?php endif; ?>
    </div>

    <div class="producto__cuerpo">
        <div>
            <?= $this->partial('partials/coleccion-chip', [
                'slug'      => $product['design_line_slug'],
                'name'      => $product['design_line_name'],
                'principal' => $product['design_line_color'],
                'suave'     => $product['design_line_soft'],
            ]) ?>
        </div>

        <h3 class="producto__nombre" id="producto-nombre-<?= (int) $product['id'] ?>">
            <a href="<?= $this->url('/producto/' . $product['slug']) ?>"><?= $this->e($product['name']) ?></a>
        </h3>

        <p class="producto__resumen"><?= $this->e($product['summary']) ?></p>

        <div class="producto__pie">
            <div class="producto__precio">
                <span class="producto__precio-linea">
                    <?php if ($product['compare_at_cents'] !== null): ?>
                        <span class="precio--tachado"><?= $this->money((int) $product['compare_at_cents']) ?></span>
                    <?php endif; ?>
                    <span class="precio"><?= $this->money((int) $product['price_cents']) ?></span>
                </span>
                <span class="precio-iva"><?= $this->t('IVA incluido') ?></span>
            </div>

            <?php if ($stock > 0): ?>
                <form class="producto__compra" method="post" action="<?= $this->url('/carrito/anadir') ?>">
                    <?= $this->csrf() ?>
                    <input type="hidden" name="producto_id" value="<?= (int) $product['id'] ?>">
                    <input type="hidden" name="cantidad" value="1">
                    <?php /* El botón solo dice «Añadir al carrito»; el producto al que se refiere se
                             anuncia a los lectores de pantalla como descripción (nombre de la tarjeta). */ ?>
                    <button class="btn btn--primario btn--pequeno" type="submit"
                            aria-describedby="producto-nombre-<?= (int) $product['id'] ?>"><?= $this->t('Añadir al carrito') ?></button>
                </form>
            <?php else: ?>
                <span class="insignia insignia--muted"><?= $this->t('Agotado') ?></span>
            <?php endif; ?>
        </div>
    </div>
</article>
