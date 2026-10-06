<?php
/**
 * Paso 2 del proceso de compra: datos de envío y condiciones.
 *
 * @var \KitsuneNotes\Core\View $this
 * @var list<array<string, mixed>>          $items
 * @var array<string, mixed>                $summary
 * @var array<string, array<string, mixed>> $shippingMethods
 * @var int                                 $giftwrapCents
 * @var array<string, list<string>>         $errors
 * @var array<string, mixed>                $old
 */
$value = static fn (string $field, string $default = ''): string => (string) ($old[$field] ?? $default);
$error = static fn (string $field): ?string => $errors[$field][0] ?? null;
?>
<?= $this->partial('partials/pasos', ['step' => 2]) ?>

<h1>Datos de envío</h1>
<p style="color:var(--frambuesa-suave); max-width:62ch">
    Necesitamos estos datos para generar el pedido. Recuerda que se trata de un
    <strong>prototipo académico</strong>: usa datos ficticios, no introduzcas información
    personal real.
</p>

<?php if ($errors !== []): ?>
    <div class="alerta alerta--error" role="alert">
        <span class="alerta__icono" aria-hidden="true"><span>!</span></span>
        <div>
            <strong>Revisa el formulario</strong>
            <ul style="margin:.4rem 0 0; padding-left:1.1rem">
                <?php foreach ($errors as $fieldErrors): ?>
                    <li><?= $this->e($fieldErrors[0]) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>

<form method="post" action="<?= $this->url('/checkout') ?>" novalidate>
    <?= $this->csrf() ?>

    <div class="pagina-dos-columnas">
        <div>
            <section class="tarjeta" style="margin-bottom:1.4rem">
                <h2 style="font-size:1.1rem">Contacto</h2>

                <div class="rejilla-campos">
                    <div class="campo">
                        <label for="nombre">Nombre y apellidos *</label>
                        <input type="text" id="nombre" name="nombre" required autocomplete="name"
                               value="<?= $this->e($value('nombre')) ?>"
                               <?= $error('nombre') ? 'aria-invalid="true" aria-describedby="err-nombre"' : '' ?>>
                        <?php if ($error('nombre')): ?>
                            <p class="campo__error" id="err-nombre"><?= $this->e($error('nombre')) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="campo">
                        <label for="email">Correo electrónico *</label>
                        <input type="email" id="email" name="email" required autocomplete="email"
                               value="<?= $this->e($value('email')) ?>"
                               <?= $error('email') ? 'aria-invalid="true" aria-describedby="err-email"' : '' ?>>
                        <p class="pista">Lo usarás para consultar el pedido después.</p>
                        <?php if ($error('email')): ?>
                            <p class="campo__error" id="err-email"><?= $this->e($error('email')) ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="campo">
                    <label for="telefono">Teléfono de contacto *</label>
                    <input type="tel" id="telefono" name="telefono" required autocomplete="tel"
                           placeholder="+34 600 000 000"
                           value="<?= $this->e($value('telefono')) ?>"
                           <?= $error('telefono') ? 'aria-invalid="true" aria-describedby="err-telefono"' : '' ?>>
                    <?php if ($error('telefono')): ?>
                        <p class="campo__error" id="err-telefono"><?= $this->e($error('telefono')) ?></p>
                    <?php endif; ?>
                </div>
            </section>

            <section class="tarjeta" style="margin-bottom:1.4rem">
                <h2 style="font-size:1.1rem">Dirección de entrega</h2>

                <div class="campo">
                    <label for="direccion">Dirección *</label>
                    <input type="text" id="direccion" name="direccion" required autocomplete="street-address"
                           placeholder="Calle, número, piso"
                           value="<?= $this->e($value('direccion')) ?>"
                           <?= $error('direccion') ? 'aria-invalid="true" aria-describedby="err-direccion"' : '' ?>>
                    <?php if ($error('direccion')): ?>
                        <p class="campo__error" id="err-direccion"><?= $this->e($error('direccion')) ?></p>
                    <?php endif; ?>
                </div>

                <div class="rejilla-campos">
                    <div class="campo">
                        <label for="codigo_postal">Código postal *</label>
                        <input type="text" id="codigo_postal" name="codigo_postal" required inputmode="numeric"
                               maxlength="5" autocomplete="postal-code"
                               value="<?= $this->e($value('codigo_postal')) ?>"
                               <?= $error('codigo_postal') ? 'aria-invalid="true" aria-describedby="err-cp"' : '' ?>>
                        <?php if ($error('codigo_postal')): ?>
                            <p class="campo__error" id="err-cp"><?= $this->e($error('codigo_postal')) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="campo">
                        <label for="ciudad">Población *</label>
                        <input type="text" id="ciudad" name="ciudad" required autocomplete="address-level2"
                               value="<?= $this->e($value('ciudad')) ?>"
                               <?= $error('ciudad') ? 'aria-invalid="true"' : '' ?>>
                        <?php if ($error('ciudad')): ?>
                            <p class="campo__error"><?= $this->e($error('ciudad')) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="campo">
                        <label for="provincia">Provincia *</label>
                        <input type="text" id="provincia" name="provincia" required autocomplete="address-level1"
                               value="<?= $this->e($value('provincia')) ?>"
                               <?= $error('provincia') ? 'aria-invalid="true"' : '' ?>>
                        <?php if ($error('provincia')): ?>
                            <p class="campo__error"><?= $this->e($error('provincia')) ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="campo">
                    <label for="notas">Notas para la entrega</label>
                    <textarea id="notas" name="notas" maxlength="400"
                              placeholder="Portal, horario preferente, punto de recogida…"><?= $this->e($value('notas')) ?></textarea>
                </div>
            </section>

            <section class="tarjeta" style="margin-bottom:1.4rem">
                <h2 style="font-size:1.1rem">Método de envío</h2>

                <?php foreach ($shippingMethods as $key => $method): ?>
                    <label class="opcion-radio">
                        <input type="radio" name="metodo_envio" value="<?= $this->e($key) ?>"
                               <?= $value('metodo_envio', (string) $summary['shipping_method']) === $key ? 'checked' : '' ?>>
                        <span class="opcion-radio__texto">
                            <span class="opcion-radio__titulo"><?= $this->e($method['label']) ?></span>
                            <span class="opcion-radio__detalle"><?= $this->e($method['description']) ?></span>
                        </span>
                        <span class="opcion-radio__precio">
                            <?php if (($method['free_from_cents'] ?? null) !== null): ?>
                                <?= $this->money((int) $method['price_cents']) ?>
                                <span style="display:block; font-size:.76rem; font-weight:500; color:var(--exito)">
                                    Gratis desde <?= $this->money((int) $method['free_from_cents']) ?>
                                </span>
                            <?php else: ?>
                                <?= $this->money((int) $method['price_cents']) ?>
                            <?php endif; ?>
                        </span>
                    </label>
                <?php endforeach; ?>

                <div class="campo-casilla" style="margin-top:1rem">
                    <input type="checkbox" id="envoltorio" name="envoltorio" value="1"
                           <?= !empty($summary['gift_wrap']) ? 'checked' : '' ?>>
                    <label for="envoltorio" style="font-weight:500">
                        Añadir envoltorio furoshiki de regalo
                        (+<?= $this->money($giftwrapCents) ?>)
                    </label>
                </div>
            </section>

            <section class="tarjeta">
                <div class="campo-casilla">
                    <input type="checkbox" id="condiciones" name="condiciones" value="1" required
                           <?= $error('condiciones') ? 'aria-invalid="true"' : '' ?>>
                    <label for="condiciones" style="font-weight:500">
                        Entiendo que <strong>Kitsune Notes es un prototipo académico</strong>, que el pago
                        es simulado y que no se producirá ningún cobro ni ningún envío real. *
                    </label>
                </div>
                <?php if ($error('condiciones')): ?>
                    <p class="campo__error"><?= $this->e($error('condiciones')) ?></p>
                <?php endif; ?>
            </section>
        </div>

        <aside class="resumen" aria-label="Resumen del pedido">
            <?= $this->partial('partials/resumen', ['summary' => $summary]) ?>

            <button class="btn btn--primario btn--grande btn--bloque" type="submit" style="margin-top:1rem">
                Ir al pago simulado
            </button>

            <p class="resumen__nota">
                El importe del envío se recalcula al confirmar el método seleccionado.
            </p>

            <details style="margin-top:1rem; font-size:.85rem">
                <summary><?= count($items) ?> líneas en el pedido</summary>
                <ul style="padding-left:1.1rem; margin:.5rem 0 0">
                    <?php foreach ($items as $item): ?>
                        <li>
                            <?= (int) $item['quantity'] ?> × <?= $this->e($item['product']['name']) ?>
                            — <?= $this->money((int) $item['line_total_cents']) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </details>
        </aside>
    </div>
</form>
