<?php
/**
 * Chip de colección con la mascota (reutilizado en tarjetas y fichas).
 *
 * @var \KitsuneNotes\Core\View $this
 * @var string $slug
 * @var string $name      nombre propio de la colección (Kitsune, Neko…): no se traduce
 * @var string $principal color principal de la colección
 * @var string $suave     color suave de la colección
 * @var bool   $enlace    si el chip enlaza a la página de la colección
 * @var bool   $completo  si el chip dice «Colección Neko» en lugar de solo «Neko»
 */
$enlace ??= true;
$completo ??= false;
$estilo = '--c-principal:' . $this->e($principal) . ';--c-suave:' . $this->e($suave);
// «Colección Neko» se traduce como frase entera (en inglés el nombre va delante);
// t() la devuelve ya escapada, igual que e() el nombre suelto.
$texto = $completo ? $this->t('Colección {nombre}', ['nombre' => $name]) : $this->e($name);
$contenido = '<img src="' . $this->asset('assets/img/mascotas/' . $slug . '.svg') . '" alt="" width="24" height="24">'
    . '<span>' . $texto . '</span>';
?>
<?php if ($enlace): ?>
    <a class="coleccion-chip" style="<?= $estilo ?>" href="<?= $this->url('/coleccion/' . $slug) ?>"><?= $contenido ?></a>
<?php else: ?>
    <span class="coleccion-chip" style="<?= $estilo ?>"><?= $contenido ?></span>
<?php endif; ?>
