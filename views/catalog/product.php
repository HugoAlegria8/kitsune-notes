<?php
/**
 * Ficha de producto: toda la información necesaria para decidir la compra.
 *
 * @var \KitsuneNotes\Core\View $this
 * @var array<string, mixed>       $product
 * @var array<string, string>      $specs
 * @var list<array<string, mixed>> $related
 * @var int                        $maxUnits
 */
$stock    = (int) $product['stock'];
$taxRate  = (float) $product['tax_rate'];
$base     = (int) round((int) $product['price_cents'] / (1 + $taxRate));
$taxCents = (int) $product['price_cents'] - $base;
?>
<nav class="migas" aria-label="Migas de pan">
    <a href="<?= $this->url('/') ?>">Inicio</a><span aria-hidden="true">♡</span>
    <a href="<?= $this->url('/categoria/' . $product['category_slug']) ?>"><?= $this->e($product['category_name']) ?></a>
    <span aria-hidden="true">♡</span><?= $this->e($product['name']) ?>
</nav>

<div class="ficha">
    <div class="ficha__imagen">
        <img src="<?= $this->asset($product['image_path']) ?>"
             alt="Ilustración del producto <?= $this->e($product['name']) ?>"
             width="640" height="480">
    </div>

    <div>
        <div class="ficha__meta">
            <?= $this->partial('partials/coleccion-chip', [
                'slug'      => $product['design_line_slug'],
                'name'      => 'Colección ' . $product['design_line_name'],
                'principal' => $product['design_line_color'],
                'suave'     => $product['design_line_soft'],
            ]) ?>
            <span class="insignia insignia--neutral"><?= $this->e($product['category_name']) ?></span>
            <span class="insignia insignia--neutral">Hecho en <?= $this->e($product['origin']) ?></span>
        </div>

        <h1 class="ficha__titulo"><?= $this->e($product['name']) ?></h1>
        <p style="color:var(--frambuesa-suave); font-weight:700">
            <?= $this->e($product['brand']) ?> · Ref. <?= $this->e($product['sku']) ?>
        </p>

        <p style="font-size:1.04rem"><?= $this->e($product['summary']) ?></p>

        <div class="ficha__precio">
            <?php if ($product['compare_at_cents'] !== null): ?>
                <span class="precio--tachado"><?= $this->money((int) $product['compare_at_cents']) ?></span>
            <?php endif; ?>
            <span class="precio"><?= $this->money((int) $product['price_cents']) ?></span>
            <span class="precio-iva">IVA incluido</span>
        </div>

        <p style="font-size:.84rem; color:var(--frambuesa-tenue); margin-bottom:.8rem; font-weight:700">
            Desglose: <?= $this->money($base) ?> de base imponible
            + <?= $this->money($taxCents) ?> de IVA (<?= (int) round($taxRate * 100) ?> %).
        </p>

        <?php if ($stock > 10): ?>
            <p class="ficha__stock ficha__stock--ok">✓ En stock y listo para salir volando</p>
        <?php elseif ($stock > 0): ?>
            <p class="ficha__stock ficha__stock--bajo">¡Solo quedan <?= $stock ?> unidades!</p>
        <?php else: ?>
            <p class="ficha__stock ficha__stock--no">Agotado por ahora <span class="kaomoji">(｡•́︿•̀｡)</span></p>
        <?php endif; ?>

        <?php if ($stock > 0): ?>
            <form class="ficha__compra" method="post" action="<?= $this->url('/carrito/anadir') ?>">
                <?= $this->csrf() ?>
                <input type="hidden" name="producto_id" value="<?= (int) $product['id'] ?>">
                <div class="selector-cantidad campo" style="margin:0">
                    <label for="cantidad">Cantidad</label>
                    <input type="number" id="cantidad" name="cantidad" value="1" min="1" max="<?= $maxUnits ?>" step="1">
                </div>
                <button class="btn btn--primario btn--grande" type="submit">Añadir al carrito ♡</button>
            </form>
        <?php else: ?>
            <p><a class="btn btn--secundario" href="<?= $this->url('/coleccion/' . $product['design_line_slug']) ?>">Ver más de esta colección</a></p>
        <?php endif; ?>

        <div class="confianza">
            <div class="confianza__item">
                <strong>Envío estándar 3,95 €</strong>
                Gratis a partir de 35 € (simulado).
            </div>
            <div class="confianza__item">
                <strong>Peso <?= (int) $product['weight_grams'] ?> g</strong>
                Embalaje de cartón reciclado.
            </div>
        </div>

        <section class="pestanas-info">
            <h2 style="font-size:1.15rem">Descripción</h2>
            <p><?= nl2br($this->e($product['description'])) ?></p>

            <?php if ($specs !== []): ?>
                <h2 style="font-size:1.15rem; margin-top:1.5rem">Ficha técnica</h2>
                <table class="especificaciones">
                    <caption class="solo-lectores">Características técnicas de <?= $this->e($product['name']) ?></caption>
                    <tbody>
                    <?php foreach ($specs as $label => $value): ?>
                        <tr>
                            <th scope="row"><?= $this->e($label) ?></th>
                            <td><?= $this->e($value) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    </div>
</div>

<?php if ($related !== []): ?>
    <section class="seccion" aria-labelledby="titulo-relacionados">
        <div class="seccion__cabecera">
            <div>
                <h2 id="titulo-relacionados" class="titulo-deco">Hace buena pareja con…</h2>
                <p>Más cositas de la misma colección o de la misma categoría.</p>
            </div>
        </div>
        <div class="rejilla-productos">
            <?php foreach ($related as $item): ?>
                <?= $this->partial('partials/producto-tarjeta', ['product' => $item]) ?>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
