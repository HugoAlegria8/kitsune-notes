<?php
/**
 * @var \KitsuneGestion\Core\View $this
 * @var string $titulo
 * @var string $mensaje
 */
?>
<section class="tarjeta">
    <h1><?= $this->e($titulo) ?></h1>
    <p><?= $this->e($mensaje) ?></p>
    <p><a class="boton boton--suave" href="<?= $this->url('/') ?>">Volver al cuadro de mando</a></p>
</section>
