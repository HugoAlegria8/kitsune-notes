<?php
/**
 * Plantilla del back-office.
 *
 * Si no hay sesión iniciada se muestra únicamente el contenido (la
 * pantalla de acceso); con sesión, la barra lateral de navegación.
 *
 * @var \KitsuneNotes\Core\View $this
 * @var string $content
 * @var string $title
 */
$path       = $this->app()->request()->path();
$isLoggedIn = !empty($staffUser);

$navItems = [
    '/admin'             => 'Panel',
    '/admin/pedidos'     => 'Pedidos',
    '/admin/productos'   => 'Productos',
    '/admin/correos'     => 'Correos',
    '/admin/eventos'     => 'Eventos',
    '/admin/incidencias' => 'Incidencias',
];

// Correos del buzón de pruebas aún sin abrir (insignia del menú).
$unreadMail = $isLoggedIn ? $this->app()->mailRepository()->countUnread() : 0;
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $this->e($title) ?> · Back-office Kitsune Notes</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=M+PLUS+Rounded+1c:wght@500;700;800&family=Mochiy+Pop+One&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $this->asset('assets/css/kitsune.css') ?>">
    <link rel="icon" href="<?= $this->asset('assets/img/brand/kitsune.svg') ?>" type="image/svg+xml">
</head>
<body>
<?php if (!$isLoggedIn): ?>
    <div class="admin-login">
        <div class="admin-login__caja">
            <?= $this->partial('partials/flash', ['flashMessages' => $flashMessages ?? []]) ?>
            <?= $content ?>
        </div>
    </div>
<?php else: ?>
    <a class="salto-contenido" href="#contenido">Saltar al contenido</a>

    <div class="admin">
        <aside class="admin__lateral">
            <a class="admin__marca" href="<?= $this->url('/admin') ?>">
                <img src="<?= $this->asset('assets/img/brand/kitsune.svg') ?>" alt="" width="50" height="47">
                <span>
                    Kitsune Notes
                    <span>Back-office</span>
                </span>
            </a>

            <nav aria-label="Navegación del back-office">
                <ul class="admin__nav">
                    <?php foreach ($navItems as $href => $label): ?>
                        <li>
                            <a href="<?= $this->url($href) ?>"
                               <?= $path === $href || ($href !== '/admin' && str_starts_with($path, $href)) ? 'aria-current="page"' : '' ?>>
                                <?= $this->e($label) ?>
                                <?php if ($href === '/admin/correos' && $unreadMail > 0): ?>
                                    <span class="admin__contador" aria-hidden="true"><?= (int) $unreadMail ?></span>
                                    <span class="solo-lectores"><?= (int) $unreadMail ?> sin leer</span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    <li><a href="<?= $this->url('/') ?>" target="_blank" rel="noopener">Ver la tienda ↗</a></li>
                </ul>
            </nav>

            <div class="admin__pie">
                <p style="margin:0 0 .5rem">
                    Sesión: <strong><?= $this->e($staffUser['email']) ?></strong>
                </p>
                <form method="post" action="<?= $this->url('/admin/logout') ?>">
                    <?= $this->csrf() ?>
                    <button class="btn btn--secundario btn--pequeno" type="submit">Cerrar sesión</button>
                </form>
                <p style="margin:1rem 0 0; font-size:.74rem; line-height:1.5">
                    Prototipo académico · Tarea 1 SIE · datos ficticios
                </p>
            </div>
        </aside>

        <main class="admin__contenido" id="contenido">
            <?= $this->partial('partials/flash', ['flashMessages' => $flashMessages ?? []]) ?>
            <?= $content ?>
        </main>
    </div>
<?php endif; ?>

<script src="<?= $this->asset('assets/js/kitsune.js') ?>" defer></script>
</body>
</html>
