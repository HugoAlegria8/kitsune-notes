<?php
/**
 * Plantilla común del back-office.
 *
 * @var \KitsuneGestion\Core\View $this
 * @var string $content
 * @var array<string, mixed>|null $usuario
 * @var list<array{tipo:string, texto:string}> $avisos
 * @var int $propuestasPendientes
 */
$usuario              ??= null;
$avisos               ??= [];
$propuestasPendientes ??= 0;
$ruta  = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$menu  = [
    '/'            => 'Cuadro de mando',
    '/pedidos'     => 'Pedidos',
    '/incidencias' => 'Incidencias',
    '/propuestas'  => 'Propuestas del agente',
    '/integracion' => 'Integración',
];
$activa = static fn (string $enlace): bool => $enlace === '/' ? $ruta === '/' : str_starts_with($ruta, $enlace);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $this->e($titulo ?? 'Kitsune Gestión') ?> · Kitsune Gestión</title>
    <link rel="icon" href="data:,">
    <link rel="stylesheet" href="<?= $this->asset('assets/gestion.css') ?>">
</head>
<body>
<p class="aviso-academico">
    Prototipo académico (UCAM · Tarea 2). Kitsune Gestión es el back-office de la tienda Kitsune Notes:
    datos ficticios, pagos simulados y sin actividad comercial real.
</p>

<?php if ($usuario !== null): ?>
    <header class="cabecera">
        <a class="marca" href="<?= $this->url('/') ?>">
            <span class="marca__sello" aria-hidden="true">KG</span>
            <span>Kitsune Gestión<small>back-office</small></span>
        </a>
        <nav class="menu" aria-label="Secciones">
            <?php foreach ($menu as $enlace => $texto): ?>
                <a href="<?= $this->url($enlace) ?>"<?= $activa($enlace) ? ' aria-current="page"' : '' ?>>
                    <?= $this->e($texto) ?>
                    <?php if ($enlace === '/propuestas' && $propuestasPendientes > 0): ?>
                        <span class="contador" title="Pendientes de revisión"><?= $propuestasPendientes ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <form class="salir" method="post" action="<?= $this->url('/salir') ?>">
            <?= $this->csrf() ?>
            <span class="salir__usuario"><?= $this->e($usuario['email']) ?></span>
            <button class="boton boton--suave" type="submit">Salir</button>
        </form>
    </header>
<?php endif; ?>

<main class="contenido" id="contenido">
    <?php foreach ($avisos as $aviso): ?>
        <div class="aviso aviso--<?= $this->e($aviso['tipo']) ?>" role="status"><?= $this->e($aviso['texto']) ?></div>
    <?php endforeach; ?>

    <?= $content ?>
</main>

<footer class="pie">
    Kitsune Gestión · sistema independiente de la tienda, con su propia base de datos, usuarios y API.
</footer>
</body>
</html>
