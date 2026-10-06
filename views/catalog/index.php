<?php
/**
 * Catálogo con filtros por categoría y colección (línea de diseño).
 *
 * @var \KitsuneNotes\Core\View $this
 * @var list<array<string, mixed>> $products
 * @var array<string, string>      $filters
 * @var list<array<string, mixed>> $categories
 * @var list<array<string, mixed>> $designLines
 * @var array<string, mixed>|null  $collection
 */
$heading    = $heading ?? $title;
$intro      = $intro ?? '';
$collection = $collection ?? null;

$queryFor = function (array $overrides) use ($filters): string {
    $query = array_filter(array_merge($filters, $overrides), static fn ($v): bool => $v !== '' && $v !== null);

    return $this->url('/catalogo') . ($query === [] ? '' : '?' . http_build_query($query));
};
?>
<nav class="migas" aria-label="Migas de pan">
    <a href="<?= $this->url('/') ?>">Inicio</a><span aria-hidden="true">♡</span><?= $this->e($heading) ?>
</nav>

<div class="catalogo">
    <aside class="filtros" aria-labelledby="titulo-filtros">
        <h2 id="titulo-filtros">Filtrar</h2>

        <div class="filtros__grupo">
            <h3>Categoría</h3>
            <ul>
                <li>
                    <a href="<?= $queryFor(['categoria' => '']) ?>"
                       aria-current="<?= ($filters['categoria'] ?? '') === '' ? 'true' : 'false' ?>">
                        <span>Todas</span>
                    </a>
                </li>
                <?php foreach ($categories as $category): ?>
                    <li>
                        <a href="<?= $queryFor(['categoria' => $category['slug']]) ?>"
                           aria-current="<?= ($filters['categoria'] ?? '') === $category['slug'] ? 'true' : 'false' ?>">
                            <span><?= $this->e($category['name']) ?></span>
                            <span class="filtros__contador"><?= (int) $category['product_count'] ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="filtros__grupo">
            <h3>Colección</h3>
            <ul>
                <li>
                    <a href="<?= $queryFor(['coleccion' => '']) ?>"
                       aria-current="<?= ($filters['coleccion'] ?? '') === '' ? 'true' : 'false' ?>">
                        <span>Todas</span>
                    </a>
                </li>
                <?php foreach ($designLines as $line): ?>
                    <li>
                        <a href="<?= $queryFor(['coleccion' => $line['slug']]) ?>"
                           aria-current="<?= ($filters['coleccion'] ?? '') === $line['slug'] ? 'true' : 'false' ?>">
                            <span>
                                <img src="<?= $this->asset('assets/img/mascotas/' . $line['slug'] . '.svg') ?>" alt="" width="24" height="24">
                                <?= $this->e($line['name']) ?>
                            </span>
                            <span class="filtros__contador"><?= (int) $line['product_count'] ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <?php if (array_filter($filters)): ?>
            <a class="btn btn--secundario btn--pequeno btn--bloque" href="<?= $this->url('/catalogo') ?>">Quitar filtros</a>
        <?php endif; ?>
    </aside>

    <div>
        <?php if ($collection !== null): ?>
            <div class="cabecera-coleccion" style="--c-suave: <?= $this->e($collection['color_soft']) ?>">
                <img src="<?= $this->asset('assets/img/mascotas/' . $collection['slug'] . '.svg') ?>"
                     alt="<?= $this->e($collection['name']) ?>, el <?= $this->e($collection['mascot']) ?> de la colección" width="110" height="110">
                <div>
                    <h1><?= $this->e($heading) ?></h1>
                    <p><strong><?= $this->e($collection['tagline']) ?>.</strong> <?= $this->e($intro) ?></p>
                </div>
            </div>
        <?php else: ?>
            <h1><?= $this->e($heading) ?></h1>
            <?php if ($intro !== ''): ?>
                <p style="color:var(--frambuesa-suave); max-width:65ch"><?= $this->e($intro) ?></p>
            <?php endif; ?>
        <?php endif; ?>

        <div class="barra-orden">
            <p class="resultado">
                <?= count($products) ?> <?= count($products) === 1 ? 'cosita mona' : 'cositas monas' ?>
                <?php if (($filters['q'] ?? '') !== ''): ?>
                    para «<?= $this->e($filters['q']) ?>»
                <?php endif; ?>
            </p>

            <form method="get" action="<?= $this->url('/catalogo') ?>">
                <?php foreach (['categoria', 'coleccion', 'q'] as $hidden): ?>
                    <?php if (($filters[$hidden] ?? '') !== ''): ?>
                        <input type="hidden" name="<?= $hidden ?>" value="<?= $this->e($filters[$hidden]) ?>">
                    <?php endif; ?>
                <?php endforeach; ?>
                <label for="orden">Ordenar por</label>
                <select name="orden" id="orden" onchange="this.form.submit()">
                    <option value="">Recomendado</option>
                    <option value="precio_asc"  <?= ($filters['orden'] ?? '') === 'precio_asc' ? 'selected' : '' ?>>Precio: de menor a mayor</option>
                    <option value="precio_desc" <?= ($filters['orden'] ?? '') === 'precio_desc' ? 'selected' : '' ?>>Precio: de mayor a menor</option>
                    <option value="nombre"      <?= ($filters['orden'] ?? '') === 'nombre' ? 'selected' : '' ?>>Nombre (A-Z)</option>
                    <option value="novedades"   <?= ($filters['orden'] ?? '') === 'novedades' ? 'selected' : '' ?>>Novedades</option>
                </select>
                <noscript><button class="btn btn--secundario btn--pequeno" type="submit">Aplicar</button></noscript>
            </form>
        </div>

        <?php if ($products === []): ?>
            <div class="vacio tarjeta">
                <img class="vacio__mascota" src="<?= $this->asset('assets/img/mascotas/neko.svg') ?>" alt="" width="150" height="112">
                <h2>No hemos encontrado nada <span class="kaomoji">(｡•́︿•̀｡)</span></h2>
                <p>Prueba con otra búsqueda o quita algún filtro.</p>
                <a class="btn btn--primario" href="<?= $this->url('/catalogo') ?>">Ver todo el catálogo</a>
            </div>
        <?php else: ?>
            <div class="rejilla-productos">
                <?php foreach ($products as $product): ?>
                    <?= $this->partial('partials/producto-tarjeta', ['product' => $product]) ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
