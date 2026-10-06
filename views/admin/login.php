<?php
/**
 * Acceso al back-office.
 *
 * @var \KitsuneNotes\Core\View $this
 * @var string $demoEmail
 */
?>
<div style="text-align:center; margin-bottom:1.4rem">
    <?= $this->partial('partials/logo') ?>
    <h1 style="font-size:1.35rem; margin:.6rem 0 .2rem">Back-office</h1>
    <p style="color:var(--frambuesa-suave); margin:0; font-size:.9rem">Pedidos, productos, eventos e incidencias</p>
</div>

<form method="post" action="<?= $this->url('/admin/login') ?>">
    <?= $this->csrf() ?>

    <div class="campo">
        <label for="email">Usuario</label>
        <input type="email" id="email" name="email" required autocomplete="username"
               value="<?= $this->e($demoEmail) ?>">
    </div>

    <div class="campo">
        <label for="password">Contraseña</label>
        <input type="password" id="password" name="password" required autocomplete="current-password">
    </div>

    <button class="btn btn--primario btn--bloque" type="submit">Entrar</button>
</form>

<div class="alerta alerta--info" style="margin-top:1.4rem; font-size:.85rem">
    <span class="alerta__icono" aria-hidden="true"><span>i</span></span>
    <div>
        Credenciales de <strong>demostración</strong> documentadas en el README
        (usuario <code><?= $this->e($demoEmail) ?></code>). La contraseña se define en el
        fichero <code>.env</code> y se almacena cifrada con <code>password_hash()</code>.
    </div>
</div>

<p style="text-align:center; margin:1.2rem 0 0; font-size:.85rem">
    <a href="<?= $this->url('/') ?>">← Volver a la tienda</a>
</p>
