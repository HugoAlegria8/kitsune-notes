<?php
/**
 * Formulario de soporte y postventa.
 *
 * @var \KitsuneNotes\Core\View $this
 * @var array<string, string>       $types
 * @var string                      $reference
 * @var array<string, list<string>> $errors
 * @var array<string, mixed>        $old
 */
$value = static fn (string $field, string $default = ''): string => (string) ($old[$field] ?? $default);
$error = static fn (string $field): ?string => $errors[$field][0] ?? null;
?>
<div style="max-width:640px; margin-inline:auto">
    <h1>Soporte y postventa</h1>
    <p style="color:var(--frambuesa-suave)">
        Cuéntanos qué ha pasado. Cada solicitud genera una referencia de seguimiento y
        queda registrada como evento (<code>support.requested</code> o
        <code>incident.created</code>) para que el equipo interno pueda tratarla.
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

    <form class="tarjeta" method="post" action="<?= $this->url('/soporte') ?>" novalidate>
        <?= $this->csrf() ?>

        <div class="rejilla-campos">
            <div class="campo">
                <label for="nombre">Nombre *</label>
                <input type="text" id="nombre" name="nombre" required value="<?= $this->e($value('nombre')) ?>"
                       <?= $error('nombre') ? 'aria-invalid="true"' : '' ?>>
                <?php if ($error('nombre')): ?><p class="campo__error"><?= $this->e($error('nombre')) ?></p><?php endif; ?>
            </div>

            <div class="campo">
                <label for="email">Correo electrónico *</label>
                <input type="email" id="email" name="email" required value="<?= $this->e($value('email')) ?>"
                       <?= $error('email') ? 'aria-invalid="true"' : '' ?>>
                <?php if ($error('email')): ?><p class="campo__error"><?= $this->e($error('email')) ?></p><?php endif; ?>
            </div>
        </div>

        <div class="rejilla-campos">
            <div class="campo">
                <label for="tipo">Tipo de solicitud *</label>
                <select id="tipo" name="tipo" required>
                    <?php foreach ($types as $key => $label): ?>
                        <option value="<?= $this->e($key) ?>" <?= $value('tipo') === $key ? 'selected' : '' ?>>
                            <?= $this->e($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="campo">
                <label for="pedido">Referencia del pedido</label>
                <input type="text" id="pedido" name="pedido" placeholder="KN-2026-000001"
                       value="<?= $this->e($value('pedido', $reference)) ?>">
                <p class="pista">Si la indicas, la incidencia se vincula al pedido.</p>
            </div>
        </div>

        <div class="campo">
            <label for="asunto">Asunto *</label>
            <input type="text" id="asunto" name="asunto" required maxlength="120"
                   value="<?= $this->e($value('asunto')) ?>"
                   <?= $error('asunto') ? 'aria-invalid="true"' : '' ?>>
            <?php if ($error('asunto')): ?><p class="campo__error"><?= $this->e($error('asunto')) ?></p><?php endif; ?>
        </div>

        <div class="campo">
            <label for="mensaje">Mensaje *</label>
            <textarea id="mensaje" name="mensaje" required maxlength="1500"
                      placeholder="Describe lo que ha ocurrido con el mayor detalle posible."
                      <?= $error('mensaje') ? 'aria-invalid="true"' : '' ?>><?= $this->e($value('mensaje')) ?></textarea>
            <?php if ($error('mensaje')): ?><p class="campo__error"><?= $this->e($error('mensaje')) ?></p><?php endif; ?>
        </div>

        <button class="btn btn--primario btn--bloque" type="submit">Enviar solicitud</button>

        <p style="font-size:.82rem; color:var(--frambuesa-tenue); margin:.9rem 0 0">
            Prototipo académico: no envíes datos personales reales. El mensaje se guarda
            en la base de datos de pruebas y el acuse de recibo queda en el buzón de
            pruebas del equipo; solo se envía por correo real a las direcciones que el
            equipo ha autorizado expresamente.
        </p>
    </form>
</div>
