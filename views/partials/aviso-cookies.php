<?php
/**
 * Aviso informativo de cookies.
 *
 * Lo incluye layout/main.php (justo antes del pie) cuando el controlador le pasa
 * `cookieNotice`; hoy solo lo hace la portada y solo mientras no se haya cerrado.
 *
 * La tienda solo usa cookies técnicas (sesión, recuerdo de este aviso e idioma
 * elegido), así que no hay nada que aceptar o rechazar: basta con «Entendido». Sin JavaScript el
 * botón envía el formulario (PageController::acknowledgeCookies); con JavaScript,
 * kitsune.js guarda lo mismo sin recargar la página.
 *
 * @var \KitsuneNotes\Core\View $this
 * @var array{name:string, days:int} $cookieNotice
 */
?>
<section class="aviso-cookies" aria-label="<?= $this->t('Aviso de cookies') ?>"
         data-aviso-cookies data-cookie="<?= $this->e($cookieNotice['name']) ?>" data-dias="<?= (int) $cookieNotice['days'] ?>">
    <svg class="aviso-cookies__icono" viewBox="0 0 64 64" width="52" height="52" aria-hidden="true" focusable="false">
        <circle cx="32" cy="33" r="26" fill="#F6C98D" stroke="#5A2340" stroke-width="3"/>
        <ellipse cx="19" cy="22" rx="3.4" ry="2.6" fill="#8A4A32" transform="rotate(-20 19 22)"/>
        <ellipse cx="45" cy="19" rx="3.4" ry="2.6" fill="#8A4A32" transform="rotate(25 45 19)"/>
        <ellipse cx="52" cy="35" rx="3.2" ry="2.5" fill="#8A4A32" transform="rotate(-10 52 35)"/>
        <ellipse cx="33" cy="52" rx="3.4" ry="2.6" fill="#8A4A32" transform="rotate(15 33 52)"/>
        <ellipse cx="13" cy="38" rx="3" ry="2.4" fill="#8A4A32"/>
        <circle cx="24" cy="31" r="2.6" fill="#5A2340"/>
        <circle cx="40" cy="31" r="2.6" fill="#5A2340"/>
        <path d="M28 37.5c1.2 1.7 6.8 1.7 8 0" fill="none" stroke="#5A2340" stroke-width="2.4" stroke-linecap="round"/>
        <ellipse cx="19" cy="37" rx="3.6" ry="2.3" fill="#FF9EC4"/>
        <ellipse cx="45" cy="37" rx="3.6" ry="2.3" fill="#FF9EC4"/>
    </svg>

    <p class="aviso-cookies__texto">
        <strong><?= $this->t('Usamos cookies, pero solo las necesarias.') ?></strong>
        <?= $this->t('Una de sesión para guardar tu carrito y proteger los formularios y otras dos para recordar este aviso y tu idioma. No hay cookies de publicidad ni de análisis.') ?>
        <a href="<?= $this->url('/aviso-academico') ?>#cookies"><?= $this->t('Más información') ?></a>
    </p>

    <form class="aviso-cookies__accion" method="post" action="<?= $this->url('/cookies/entendido') ?>">
        <?= $this->csrf() ?>
        <button class="btn btn--primario btn--pequeno" type="submit" data-cookies-cerrar><?= $this->t('Entendido') ?></button>
    </form>
</section>
