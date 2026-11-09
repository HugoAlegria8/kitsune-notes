<?php
/**
 * @var \KitsuneGestion\Core\View $this
 * @var string $email
 * @var string|null $error
 */
?>
<section class="acceso tarjeta">
    <p class="marca marca--grande"><span class="marca__sello" aria-hidden="true">KG</span> Kitsune Gestión</p>
    <h1>Acceso del equipo</h1>
    <p class="suave">Back-office de operaciones: pedidos, incidencias, propuestas del agente de IA e integración.</p>

    <?php if (!empty($error)): ?>
        <div class="aviso aviso--error" role="alert"><?= $this->e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= $this->url('/login') ?>" class="formulario">
        <?= $this->csrf() ?>
        <label for="email">Correo</label>
        <input type="email" id="email" name="email" required autocomplete="username" value="<?= $this->e($email) ?>">
        <label for="password">Contraseña</label>
        <input type="password" id="password" name="password" required autocomplete="current-password">
        <button class="boton" type="submit">Entrar</button>
    </form>
    <p class="nota">Usuario de prueba en el README (datos ficticios).</p>
</section>
