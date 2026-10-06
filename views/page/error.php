<?php
/**
 * Página de error (404 / 500).
 *
 * @var \KitsuneNotes\Core\View $this
 * @var string $code
 * @var string $message
 */
?>
<div class="vacio tarjeta" style="max-width:580px; margin-inline:auto">
    <img class="vacio__mascota" src="<?= $this->asset('assets/img/mascotas/neko.svg') ?>" alt="" width="150" height="112">
    <p style="font-family:var(--fuente-display); font-size:3rem; margin:0; color:var(--fresa-texto)">
        <?= $this->e($code) ?>
    </p>
    <h1 style="font-size:1.4rem"><?= $this->e($title) ?> <span class="kaomoji" aria-hidden="true">(⊙_⊙)?</span></h1>
    <p><?= $this->e($message) ?></p>
    <p style="display:flex; gap:.7rem; justify-content:center; flex-wrap:wrap; margin-top:1.2rem">
        <a class="btn btn--primario" href="<?= $this->url('/') ?>"><?= $this->t('Volver a la portada') ?></a>
        <a class="btn btn--secundario" href="<?= $this->url('/catalogo') ?>"><?= $this->t('Ir al catálogo') ?></a>
    </p>
</div>
