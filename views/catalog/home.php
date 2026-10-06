<?php
/**
 * Portada de la tienda.
 *
 * @var \KitsuneNotes\Core\View $this
 * @var list<array<string, mixed>> $featured
 * @var list<array<string, mixed>> $categories
 * @var list<array<string, mixed>> $designLines
 */
// Importes de las «condiciones de compra» del final de la página. Se piden al motor de
// precios para que salgan en la moneda del visitante (euros o libras), como en el carrito.
$freeFromCents = $this->app()->pricing()->shippingMethods()['estandar']['free_from_cents'] ?? null;
$giftwrapCents = $this->app()->pricing()->giftwrapCents();

// Importe sin decimales cuando es redondo («35 €», no «35,00 €»), como en un titular.
$shortMoney = fn (int $cents): string => (string) preg_replace('/[.,]00(?!\d)/', '', $this->money($cents));
?>
<section class="heroe">
    <div>
        <span class="heroe__eyebrow"><span class="kaomoji" aria-hidden="true">(◕‿◕)♡</span> <?= $this->t('Recién llegado de Japón y Corea') ?></span>
        <h1><?= $this->th('Papelería que te hace <mark>sonreír</mark>') ?></h1>
        <p>
            <?= $this->t('Cuadernos con orejitas, bolis con ositos, washi tape de fresas y agendas con gatitos dormilones. Cuatro personajes, cuatro colecciones y mucho, mucho kawaii para tu escritorio.') ?>
        </p>
        <div class="heroe__acciones">
            <a class="btn btn--primario btn--grande" href="<?= $this->url('/catalogo') ?>"><?= $this->t('Ver el catálogo') ?></a>
            <a class="btn btn--secundario btn--grande" href="<?= $this->url('/colecciones') ?>"><?= $this->t('Conocer a los personajes') ?></a>
        </div>
    </div>
    <div class="heroe__arte" aria-hidden="true">
        <img src="<?= $this->asset('assets/img/brand/portada.svg') ?>" alt="" width="520" height="380">
    </div>
</section>

<section class="seccion" aria-labelledby="titulo-colecciones">
    <div class="seccion__cabecera">
        <div>
            <h2 id="titulo-colecciones" class="titulo-deco"><?= $this->t('Elige tu personaje') ?></h2>
            <p><?= $this->t('Cada colección es una línea de diseño con su mascota, su color y su estilo.') ?></p>
        </div>
        <a class="btn btn--secundario btn--pequeno" href="<?= $this->url('/colecciones') ?>"><?= $this->t('Ver colecciones') ?></a>
    </div>

    <div class="rejilla-productos">
        <?php foreach ($designLines as $line): ?>
            <a class="tarjeta-enlace tarjeta-coleccion"
               style="--c-suave: <?= $this->e($line['color_soft']) ?>"
               href="<?= $this->url('/coleccion/' . $line['slug']) ?>">
                <img src="<?= $this->asset('assets/img/mascotas/' . $line['slug'] . '.svg') ?>"
                     alt="" width="400" height="300" loading="lazy">
                <div class="tarjeta-coleccion__texto">
                    <div class="tarjeta-coleccion__nombre">
                        <h3><?= $this->e($line['name']) ?></h3>
                        <span class="nombre-nativo" lang="<?= in_array($line['slug'], ['tokki', 'gom'], true) ? 'ko' : 'ja' ?>"><?= $this->e($line['native_name']) ?></span>
                    </div>
                    <p><?= $this->e($line['tagline']) ?></p>
                    <span class="tarjeta-enlace__pie"><?= $this->tn('{n} producto del {mascota}', '{n} productos del {mascota}', (int) $line['product_count'], ['mascota' => $line['mascot']]) ?> →</span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section class="seccion" aria-labelledby="titulo-destacados">
    <div class="seccion__cabecera">
        <div>
            <h2 id="titulo-destacados" class="titulo-deco"><?= $this->t('Los favoritos de la casa') ?></h2>
            <p><?= $this->t('Lo que más se lleva esta temporada.') ?></p>
        </div>
        <a class="btn btn--secundario btn--pequeno" href="<?= $this->url('/catalogo') ?>"><?= $this->t('Ver todo') ?></a>
    </div>

    <div class="rejilla-productos">
        <?php foreach ($featured as $product): ?>
            <?= $this->partial('partials/producto-tarjeta', ['product' => $product]) ?>
        <?php endforeach; ?>
    </div>
</section>

<section class="seccion" aria-labelledby="titulo-categorias">
    <div class="seccion__cabecera">
        <div>
            <h2 id="titulo-categorias" class="titulo-deco"><?= $this->t('Compra por categoría') ?></h2>
            <p><?= $this->t('Y si ya sabes lo que buscas, directo al grano.') ?></p>
        </div>
    </div>

    <div class="rejilla-productos">
        <?php foreach ($categories as $category): ?>
            <a class="tarjeta-enlace" href="<?= $this->url('/categoria/' . $category['slug']) ?>">
                <h3><?= $this->e($category['name']) ?></h3>
                <p><?= $this->e($category['tagline']) ?></p>
                <span class="tarjeta-enlace__pie"><?= $this->tn('{n} referencia', '{n} referencias', (int) $category['product_count']) ?> →</span>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section class="seccion" aria-label="<?= $this->t('Condiciones de compra') ?>">
    <div class="confianza">
        <?php if ($freeFromCents !== null): ?>
            <div class="confianza__item">
                <strong><?= $this->t('Envío gratis desde {importe}', ['importe' => $shortMoney((int) $freeFromCents)]) ?></strong>
                <?= $this->t('Envío estándar simulado en 3-5 días laborables.') ?>
            </div>
        <?php endif; ?>
        <div class="confianza__item">
            <strong><?= $this->t('Pago simulado seguro') ?></strong>
            <?= $this->t('Pasarela de pruebas: nunca guardamos números de tarjeta.') ?>
        </div>
        <div class="confianza__item">
            <strong><?= $this->t('Envoltorio furoshiki') ?></strong>
            <?= $this->t('Para regalar, por {importe} más.', ['importe' => $this->money($giftwrapCents)]) ?>
        </div>
        <div class="confianza__item">
            <strong><?= $this->t('Te ayudamos') ?></strong>
            <?= $this->t('Incidencias con seguimiento por referencia.') ?>
        </div>
    </div>
</section>
