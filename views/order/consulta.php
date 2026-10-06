<?php
/**
 * Consulta de un pedido mediante referencia + correo electrónico.
 *
 * @var \KitsuneNotes\Core\View $this
 * @var string                     $reference
 * @var array<string, list<string>> $errors
 */
?>
<div style="max-width:520px; margin-inline:auto">
    <h1>Consulta tu pedido</h1>
    <p style="color:var(--frambuesa-suave)">
        Introduce la referencia del pedido y el correo electrónico con el que lo hiciste.
        Pedimos ambos datos para que nadie pueda ver los datos de envío conociendo solo la referencia.
    </p>

    <?php if ($errors !== []): ?>
        <div class="alerta alerta--error" role="alert">
            <span class="alerta__icono" aria-hidden="true"><span>!</span></span>
            <ul style="margin:0; padding-left:1.1rem">
                <?php foreach ($errors as $fieldErrors): ?>
                    <li><?= $this->e($fieldErrors[0]) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form class="tarjeta" method="post" action="<?= $this->url('/pedidos') ?>" novalidate>
        <?= $this->csrf() ?>

        <div class="campo">
            <label for="referencia">Referencia del pedido</label>
            <input type="text" id="referencia" name="referencia" required
                   placeholder="KN-2026-000001" value="<?= $this->e($reference) ?>">
        </div>

        <div class="campo">
            <label for="email">Correo electrónico de la compra</label>
            <input type="email" id="email" name="email" required placeholder="tu@correo.test">
        </div>

        <button class="btn btn--primario btn--bloque" type="submit">Ver el pedido</button>
    </form>

    <p style="margin-top:1rem; font-size:.86rem; color:var(--frambuesa-tenue)">
        ¿Necesitas ayuda con un pedido? Escríbenos desde el
        <a href="<?= $this->url('/soporte') ?>">formulario de soporte</a>.
    </p>
</div>
