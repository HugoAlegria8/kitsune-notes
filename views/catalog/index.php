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
<nav class="migas" aria-label="<?= $this->t('Migas de pan') ?>">
    <a href="<?= $this->url('/') ?>"><?= $this->t('Inicio') ?></a><span aria-hidden="true">♡</span><?= $this->e($heading) ?>
</nav>

<div class="catalogo">
    <aside class="filtros" aria-labelledby="titulo-filtros">
        <h2 id="titulo-filtros"><?= $this->t('Filtrar') ?></h2>

        <div class="filtros__grupo">
            <h3><?= $this->t('Categoría') ?></h3>
            <ul>
                <li>
                    <a href="<?= $queryFor(['categoria' => '']) ?>"
                       aria-current="<?= ($filters['categoria'] ?? '') === '' ? 'true' : 'false' ?>">
                        <span><?= $this->t('Todas') ?></span>
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
            <h3><?= $this->t('Colección') ?></h3>
            <ul>
                <li>
                    <a href="<?= $queryFor(['coleccion' => '']) ?>"
                       aria-current="<?= ($filters['coleccion'] ?? '') === '' ? 'true' : 'false' ?>">
                        <span><?= $this->t('Todas') ?></span>
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
            <a class="btn btn--secundario btn--pequeno btn--bloque" href="<?= $this->url('/catalogo') ?>"><?= $this->t('Quitar filtros') ?></a>
        <?php endif; ?>
    </aside>

    <div>
        <?php if ($collection !== null): ?>
            <div class="cabecera-coleccion" style="--c-suave: <?= $this->e($collection['color_soft']) ?>">
                <img src="<?= $this->asset('assets/img/mascotas/' . $collection['slug'] . '.svg') ?>"
                     alt="<?= $this->t('{nombre}, el {mascota} de la colección', ['nombre' => $collection['name'], 'mascota' => $collection['mascot']]) ?>" width="110" height="110">
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
                <?php if (($filters['q'] ?? '') !== ''): ?>
                    <?= $this->tn('{n} cosita mona para «{busqueda}»', '{n} cositas monas para «{busqueda}»', count($products), ['busqueda' => $filters['q']]) ?>
                <?php else: ?>
                    <?= $this->tn('{n} cosita mona', '{n} cositas monas', count($products)) ?>
                <?php endif; ?>
            </p>

            <form method="get" action="<?= $this->url('/catalogo') ?>">
                <?php foreach (['categoria', 'coleccion', 'q'] as $hidden): ?>
                    <?php if (($filters[$hidden] ?? '') !== ''): ?>
                        <input type="hidden" name="<?= $hidden ?>" value="<?= $this->e($filters[$hidden]) ?>">
                    <?php endif; ?>
                <?php endforeach; ?>
                <label for="orden"><?= $this->t('Ordenar por') ?></label>
                <select name="orden" id="orden" onchange="this.form.submit()">
                    <option value=""><?= $this->t('Recomendado') ?></option>
                    <option value="precio_asc"  <?= ($filters['orden'] ?? '') === 'precio_asc' ? 'selected' : '' ?>><?= $this->t('Precio: de menor a mayor') ?></option>
                    <option value="precio_desc" <?= ($filters['orden'] ?? '') === 'precio_desc' ? 'selected' : '' ?>><?= $this->t('Precio: de mayor a menor') ?></option>
                    <option value="nombre"      <?= ($filters['orden'] ?? '') === 'nombre' ? 'selected' : '' ?>><?= $this->t('Nombre (A-Z)') ?></option>
                    <option value="novedades"   <?= ($filters['orden'] ?? '') === 'novedades' ? 'selected' : '' ?>><?= $this->t('Novedades') ?></option>
                </select>
                <noscript><button class="btn btn--secundario btn--pequeno" type="submit"><?= $this->t('Aplicar') ?></button></noscript>
            </form>
        </div>

        <?php if ($products === []): ?>
            <div class="vacio tarjeta">
                <img class="vacio__mascota" src="<?= $this->asset('assets/img/mascotas/neko.svg') ?>" alt="" width="150" height="112">
                <h2><?= $this->t('No hemos encontrado nada') ?> <span class="kaomoji">(｡•́︿•̀｡)</span></h2>
                <p><?= $this->t('Prueba con otra búsqueda o quita algún filtro.') ?></p>
                <a class="btn btn--primario" href="<?= $this->url('/catalogo') ?>"><?= $this->t('Ver todo el catálogo') ?></a>
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
