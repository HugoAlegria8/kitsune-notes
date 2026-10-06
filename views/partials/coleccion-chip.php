<?php
/**
 * Chip de colección con la mascota (reutilizado en tarjetas y fichas).
 *
 * @var \KitsuneNotes\Core\View $this
 * @var string $slug
 * @var string $name
 * @var string $principal color principal de la colección
 * @var string $suave     color suave de la colección
 * @var bool   $enlace    si el chip enlaza a la página de la colección
 */
$enlace ??= true;
$estilo = '--c-principal:' . $this->e($principal) . ';--c-suave:' . $this->e($suave);
$contenido = '<img src="' . $this->asset('assets/img/mascotas/' . $slug . '.svg') . '" alt="" width="24" height="24">'
    . '<span>' . $this->e($name) . '</span>';
?>
<?php if ($enlace): ?>
    <a class="coleccion-chip" style="<?= $estilo ?>" href="<?= $this->url('/coleccion/' . $slug) ?>"><?= $contenido ?></a>
<?php else: ?>
    <span class="coleccion-chip" style="<?= $estilo ?>"><?= $contenido ?></span>
<?php endif; ?>
