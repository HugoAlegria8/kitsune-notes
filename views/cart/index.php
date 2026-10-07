<?php
/**
 * Carrito de la compra.
 *
 * @var \KitsuneNotes\Core\View $this
 * @var list<array<string, mixed>> $items
 * @var array<string, mixed>       $summary
 * @var list<array<string, mixed>> $coupons
 * @var int                        $maxUnits
 */
?>
<?= $this->partial('partials/pasos', ['step' => 1]) ?>

<h1><?= $this->t('Tu carrito') ?></h1>

<?php if ($items === []): ?>
    <div class="vacio tarjeta">
        <img class="vacio__mascota" src="<?= $this->asset('assets/img/mascotas/tokki.svg') ?>" alt="" width="150" height="112">
        <h2><?= $this->t('Tu carrito está vacío') ?> <span class="kaomoji">(｡•́︿•̀｡)</span></h2>
        <p><?= $this->t('Tokki está triste. Anímalo con un cuaderno o un poco de washi tape.') ?></p>
        <a class="btn btn--primario btn--grande" href="<?= $this->url('/catalogo') ?>"><?= $this->t('Ir al catálogo') ?></a>
    </div>
<?php else: ?>
    <div class="pagina-dos-columnas">
        <section aria-label="<?= $this->t('Artículos del carrito') ?>">
            <?php foreach ($items as $item): ?>
                <?php $product = $item['product']; ?>
                <article class="linea-carrito">
                    <div class="linea-carrito__imagen">
                        <img src="<?= $this->asset($product['image_path']) ?>" alt="" width="96" height="96" loading="lazy">
                    </div>

                    <div>
                        <p class="linea-carrito__nombre" style="margin:0">
                            <a href="<?= $this->url('/producto/' . $product['slug']) ?>"><?= $this->e($product['name']) ?></a>
                        </p>
                        <p class="linea-carrito__meta" style="margin:.15rem 0 0">
                            <?= $this->t('Colección {nombre}', ['nombre' => $product['design_line_name']]) ?> ·
                            <?= $this->t('{precio} / unidad', ['precio' => $this->money((int) $product['price_cents'])]) ?> ·
                            <?= $this->t('Ref. {sku}', ['sku' => $product['sku']]) ?>
                        </p>

                        <div class="linea-carrito__controles">
                            <form method="post" action="<?= $this->url('/carrito/actualizar') ?>">
                                <?= $this->csrf() ?>
                                <input type="hidden" name="producto_id" value="<?= (int) $product['id'] ?>">
                                <label class="solo-lectores" for="cantidad-<?= (int) $product['id'] ?>">
                                    <?= $this->t('Cantidad de {producto}', ['producto' => $product['name']]) ?>
                                </label>
                                <input type="number" id="cantidad-<?= (int) $product['id'] ?>" name="cantidad"
                                       value="<?= (int) $item['quantity'] ?>" min="1"
                                       max="<?= min($maxUnits, (int) $product['stock']) ?>">
                                <button class="btn btn--secundario btn--pequeno" type="submit"><?= $this->t('Actualizar') ?></button>
                            </form>

                            <form method="post" action="<?= $this->url('/carrito/eliminar') ?>">
                                <?= $this->csrf() ?>
                                <input type="hidden" name="producto_id" value="<?= (int) $product['id'] ?>">
                                <button class="btn btn--fantasma" type="submit">
                                    <?= $this->th('Quitar<span class="solo-lectores"> {producto}</span>', ['producto' => $product['name']]) ?>
                                </button>
                            </form>
                        </div>
                    </div>

                    <p class="linea-carrito__importe"><?= $this->money((int) $item['line_total_cents']) ?></p>
                </article>
            <?php endforeach; ?>

            <p style="margin-top:1.4rem">
                <a class="btn btn--secundario" href="<?= $this->url('/catalogo') ?>">← <?= $this->t('Seguir mirando cositas') ?></a>
            </p>
        </section>

        <aside class="resumen" aria-label="<?= $this->t('Resumen del pedido') ?>">
            <?= $this->partial('partials/resumen', ['summary' => $summary]) ?>

            <form class="cupon" method="post" action="<?= $this->url('/carrito/cupon') ?>">
                <?= $this->csrf() ?>
                <label class="solo-lectores" for="cupon"><?= $this->t('Código de descuento') ?></label>
                <input type="text" id="cupon" name="cupon" placeholder="<?= $this->t('CÓDIGO') ?>"
                       value="<?= $this->e($summary['coupon_code'] ?? '') ?>" autocomplete="off">
                <?php if (!empty($summary['coupon_code'])): ?>
                    <input type="hidden" name="accion" value="quitar">
                    <button class="btn btn--secundario btn--pequeno" type="submit"><?= $this->t('Quitar') ?></button>
                <?php else: ?>
                    <button class="btn btn--secundario btn--pequeno" type="submit"><?= $this->t('Aplicar') ?></button>
                <?php endif; ?>
            </form>

            <a class="btn btn--primario btn--grande btn--bloque" href="<?= $this->url('/checkout') ?>">
                <?= $this->t('Continuar con el pedido') ?>
            </a>

            <p class="resumen__nota">
                <?= $this->th('El proceso de compra es <strong>simulado</strong>: no se cobra nada ni se piden datos de pago reales.') ?>
            </p>

            <?php if ($coupons !== []): ?>
                <details style="margin-top:1rem; font-size:.86rem">
                    <summary style="cursor:pointer; font-weight:800"><?= $this->t('Códigos de prueba disponibles') ?></summary>
                    <ul style="padding-left:1.1rem; margin:.5rem 0 0">
                        <?php foreach ($coupons as $coupon): ?>
                            <li><code><?= $this->e($coupon['code']) ?></code> — <?= $this->e($coupon['description']) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </details>
            <?php endif; ?>
        </aside>
    </div>
<?php endif; ?>
