<?php
/**
 * Plantilla principal de la tienda.
 *
 * @var \KitsuneNotes\Core\View $this
 * @var string $content
 * @var string $title
 * @var array{name:string, days:int}|null $cookieNotice Aviso de cookies: lo pasa el
 *      controlador solo cuando hay que mostrarlo (hoy, la portada y mientras no se haya cerrado).
 */
$currentPath = $this->app()->request()->path();
$activo = static fn (string $ruta): string => $currentPath === $ruta ? 'aria-current="page"' : '';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $this->e($title) ?> · <?= $this->e($appName) ?></title>
    <meta name="description" content="Kitsune Notes · papelería kawaii japonesa y coreana. Prototipo académico sin actividad comercial real.">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#FFD6E7">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=M+PLUS+Rounded+1c:wght@500;700;800&family=Mochiy+Pop+One&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@700&text=%ED%86%A0%EB%81%BC%EA%B3%B0&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $this->asset('assets/css/kitsune.css') ?>">
    <link rel="icon" href="<?= $this->asset('assets/img/brand/kitsune.svg') ?>" type="image/svg+xml">
</head>
<body>
<a class="salto-contenido" href="#contenido">Saltar al contenido principal</a>

<div class="aviso-prototipo">
    <div class="contenedor aviso-prototipo__interior">
        <span class="aviso-prototipo__corazon" aria-hidden="true">♡</span>
        <span>Prototipo académico · sin actividad comercial real: productos, precios, pagos y pedidos son ficticios.</span>
        <a href="<?= $this->url('/aviso-academico') ?>">Más información</a>
    </div>
</div>

<header class="cabecera">
    <div class="contenedor cabecera__fila">
        <a class="marca" href="<?= $this->url('/') ?>">
            <?= $this->partial('partials/logo') ?>
            <span>
                <span class="marca__nombre">Kitsune Notes</span>
                <span class="marca__claim">papelería kawaii de Japón y Corea</span>
            </span>
        </a>

        <form class="buscador" role="search" action="<?= $this->url('/catalogo') ?>" method="get">
            <label class="solo-lectores" for="buscador">Buscar productos</label>
            <input type="search" id="buscador" name="q" placeholder="Busca cuadernos, washi, Neko…"
                   value="<?= $this->e($filters['q'] ?? '') ?>">
            <button class="btn btn--primario btn--pequeno" type="submit">Buscar</button>
        </form>

        <div class="cabecera__acciones">
            <a class="btn btn--secundario btn--pequeno" href="<?= $this->url('/pedidos') ?>">Mi pedido</a>
            <a class="carrito-boton" href="<?= $this->url('/carrito') ?>">
                <svg class="carrito-boton__icono" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="M5 8h14l-1.2 11.2a2 2 0 0 1-2 1.8H8.2a2 2 0 0 1-2-1.8L5 8Z" fill="#fff" stroke="#5A2340" stroke-width="2" stroke-linejoin="round"/>
                    <path d="M9 8V6.5a3 3 0 0 1 6 0V8" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round"/>
                    <path d="M12 17.2c-2.3-1.5-3.2-2.6-3.2-3.7 0-.9.7-1.6 1.6-1.6.7 0 1.2.4 1.6 1 .4-.6.9-1 1.6-1 .9 0 1.6.7 1.6 1.6 0 1.1-.9 2.2-3.2 3.7Z" fill="#FF5C8A"/>
                </svg>
                <span>Carrito</span>
                <span class="carrito-boton__contador"><?= (int) ($cartUnits ?? 0) ?></span>
                <span class="solo-lectores">artículos en el carrito</span>
            </a>
        </div>
    </div>

    <nav class="navegacion" aria-label="Navegación principal">
        <div class="contenedor">
            <ul>
                <li><a href="<?= $this->url('/catalogo') ?>" <?= $activo('/catalogo') ?>>Todo el catálogo</a></li>
                <?php foreach (($navCategories ?? []) as $category): ?>
                    <li>
                        <a href="<?= $this->url('/categoria/' . $category['slug']) ?>" <?= $activo('/categoria/' . $category['slug']) ?>>
                            <?= $this->e($category['name']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
                <li><a href="<?= $this->url('/colecciones') ?>" <?= $activo('/colecciones') ?>>Colecciones</a></li>
                <li><a href="<?= $this->url('/soporte') ?>" <?= $activo('/soporte') ?>>Soporte</a></li>
            </ul>
        </div>
    </nav>
</header>

<main class="principal" id="contenido">
    <div class="contenedor">
        <?= $this->partial('partials/flash', ['flashMessages' => $flashMessages ?? []]) ?>
        <?= $content ?>
    </div>
</main>

<?php if (!empty($cookieNotice)): ?>
    <?= $this->partial('partials/aviso-cookies', ['cookieNotice' => $cookieNotice]) ?>
<?php endif; ?>

<footer class="pie">
    <div class="contenedor">
        <div class="pie__rejilla">
            <div>
                <h3>Kitsune Notes</h3>
                <p>Papelería kawaii importada de Japón y Corea: cuadernos, escritura, washi tape y organización, con cuatro personajes que lo llenan todo de mofletes.</p>
                <div class="pie__mascotas" aria-hidden="true">
                    <?php foreach (($navDesignLines ?? []) as $line): ?>
                        <img src="<?= $this->asset('assets/img/mascotas/' . $line['slug'] . '.svg') ?>" alt="" width="44" height="44" loading="lazy">
                    <?php endforeach; ?>
                </div>
            </div>
            <div>
                <h3>Catálogo</h3>
                <ul>
                    <?php foreach (($navCategories ?? []) as $category): ?>
                        <li><a href="<?= $this->url('/categoria/' . $category['slug']) ?>"><?= $this->e($category['name']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div>
                <h3>Colecciones</h3>
                <ul>
                    <?php foreach (($navDesignLines ?? []) as $line): ?>
                        <li><a href="<?= $this->url('/coleccion/' . $line['slug']) ?>"><?= $this->e($line['name']) ?> · <?= $this->e($line['mascot']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div>
                <h3>Ayuda</h3>
                <ul>
                    <li><a href="<?= $this->url('/pedidos') ?>">Consultar un pedido</a></li>
                    <li><a href="<?= $this->url('/soporte') ?>">Soporte e incidencias</a></li>
                    <li><a href="<?= $this->url('/envios') ?>">Envíos y devoluciones</a></li>
                    <li><a href="<?= $this->url('/sobre-kitsune') ?>">Sobre Kitsune Notes</a></li>
                    <li><a href="<?= $this->url('/aviso-academico') ?>">Aviso académico</a></li>
                    <li><a href="<?= $this->url('/admin/login') ?>">Acceso interno</a></li>
                </ul>
            </div>
        </div>
        <div class="pie__legal">
            <p>© <?= date('Y') ?> Kitsune Notes · Empresa ficticia creada para la asignatura <em>Soluciones Informáticas para la Empresa</em> (UCAM).</p>
            <p><?= $this->e($academicNotice ?? '') ?></p>
        </div>
    </div>
</footer>

<script src="<?= $this->asset('assets/js/kitsune.js') ?>" defer></script>
</body>
</html>
