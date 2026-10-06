<?php
/**
 * Las cuatro colecciones (líneas de diseño) y sus personajes.
 *
 * @var \KitsuneNotes\Core\View $this
 * @var list<array<string, mixed>> $designLines
 */
?>
<div style="max-width:980px; margin-inline:auto">
    <h1>Conoce a los personajes</h1>
    <p style="font-size:1.06rem; color:var(--frambuesa-suave); max-width:68ch">
        El catálogo se organiza en dos ejes. La <strong>categoría</strong> dice qué es el producto:
        un cuaderno, un boli, una cinta washi o un organizador. La <strong>colección</strong> dice
        cómo es: cada una es una línea de diseño con su personaje, su color y su manera de ver
        el escritorio. Dos personajes vienen de Japón y dos de Corea, igual que el catálogo.
    </p>

    <div class="rejilla-productos" style="margin-top:2rem; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr))">
        <?php foreach ($designLines as $line): ?>
            <article class="tarjeta-enlace tarjeta-coleccion" style="--c-suave: <?= $this->e($line['color_soft']) ?>">
                <img src="<?= $this->asset('assets/img/mascotas/' . $line['slug'] . '.svg') ?>"
                     alt="<?= $this->e($line['name']) ?>, el <?= $this->e($line['mascot']) ?>" width="400" height="300">
                <div class="tarjeta-coleccion__texto">
                    <div class="tarjeta-coleccion__nombre">
                        <h2 style="font-size:1.35rem; margin:0"><?= $this->e($line['name']) ?></h2>
                        <span class="nombre-nativo" lang="<?= in_array($line['slug'], ['tokki', 'gom'], true) ? 'ko' : 'ja' ?>"><?= $this->e($line['native_name']) ?></span>
                        <span style="font-size:.85rem; font-weight:800; color:var(--frambuesa-suave)">· <?= $this->e($line['mascot']) ?></span>
                    </div>
                    <p style="font-weight:800; color:var(--frambuesa)"><?= $this->e($line['tagline']) ?></p>
                    <p><?= $this->e($line['description']) ?></p>
                    <p style="margin-top:.8rem">
                        <a class="btn btn--primario btn--pequeno" href="<?= $this->url('/coleccion/' . $line['slug']) ?>">
                            Ver sus <?= (int) $line['product_count'] ?> productos
                        </a>
                    </p>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

    <div class="alerta alerta--info" style="margin-top:2.2rem">
        <span class="alerta__icono" aria-hidden="true"><span>i</span></span>
        <div>
            Los cuatro personajes son <strong>diseños originales</strong> de este proyecto, dibujados como
            ilustración vectorial; no reproducen ningún personaje comercial existente.
        </div>
    </div>
</div>
