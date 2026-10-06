<?php
/**
 * Portada de la tienda.
 *
 * @var \KitsuneNotes\Core\View $this
 * @var list<array<string, mixed>> $featured
 * @var list<array<string, mixed>> $categories
 * @var list<array<string, mixed>> $designLines
 */
?>
<section class="heroe">
    <div>
        <span class="heroe__eyebrow"><span class="kaomoji" aria-hidden="true">(◕‿◕)♡</span> Recién llegado de Japón y Corea</span>
        <h1>Papelería que te hace <mark>sonreír</mark></h1>
        <p>
            Cuadernos con orejitas, bolis con ositos, washi tape de fresas y agendas
            con gatitos dormilones. Cuatro personajes, cuatro colecciones y mucho,
            mucho kawaii para tu escritorio.
        </p>
        <div class="heroe__acciones">
            <a class="btn btn--primario btn--grande" href="<?= $this->url('/catalogo') ?>">Ver el catálogo</a>
            <a class="btn btn--secundario btn--grande" href="<?= $this->url('/colecciones') ?>">Conocer a los personajes</a>
        </div>
    </div>
    <div class="heroe__arte" aria-hidden="true">
        <img src="<?= $this->asset('assets/img/brand/portada.svg') ?>" alt="" width="520" height="380">
    </div>
</section>

<section class="seccion" aria-labelledby="titulo-colecciones">
    <div class="seccion__cabecera">
        <div>
            <h2 id="titulo-colecciones" class="titulo-deco">Elige tu personaje</h2>
            <p>Cada colección es una línea de diseño con su mascota, su color y su estilo.</p>
        </div>
        <a class="btn btn--secundario btn--pequeno" href="<?= $this->url('/colecciones') ?>">Ver colecciones</a>
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
                    <span class="tarjeta-enlace__pie"><?= (int) $line['product_count'] ?> productos del <?= $this->e($line['mascot']) ?> →</span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section class="seccion" aria-labelledby="titulo-destacados">
    <div class="seccion__cabecera">
        <div>
            <h2 id="titulo-destacados" class="titulo-deco">Los favoritos de la casa</h2>
            <p>Lo que más se lleva esta temporada.</p>
        </div>
        <a class="btn btn--secundario btn--pequeno" href="<?= $this->url('/catalogo') ?>">Ver todo</a>
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
            <h2 id="titulo-categorias" class="titulo-deco">Compra por categoría</h2>
            <p>Y si ya sabes lo que buscas, directo al grano.</p>
        </div>
    </div>

    <div class="rejilla-productos">
        <?php foreach ($categories as $category): ?>
            <a class="tarjeta-enlace" href="<?= $this->url('/categoria/' . $category['slug']) ?>">
                <h3><?= $this->e($category['name']) ?></h3>
                <p><?= $this->e($category['tagline']) ?></p>
                <span class="tarjeta-enlace__pie"><?= (int) $category['product_count'] ?> referencias →</span>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section class="seccion" aria-label="Condiciones de compra">
    <div class="confianza">
        <div class="confianza__item">
            <strong>Envío gratis desde 35 €</strong>
            Envío estándar simulado en 3-5 días laborables.
        </div>
        <div class="confianza__item">
            <strong>Pago simulado seguro</strong>
            Pasarela de pruebas: nunca guardamos números de tarjeta.
        </div>
        <div class="confianza__item">
            <strong>Envoltorio furoshiki</strong>
            Para regalar, por 1,50 € más.
        </div>
        <div class="confianza__item">
            <strong>Te ayudamos</strong>
            Incidencias con seguimiento por referencia.
        </div>
    </div>
</section>
