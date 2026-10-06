<?php
/**
 * Paso 3 del proceso de compra: pasarela de pago simulada.
 *
 * @var \KitsuneNotes\Core\View $this
 * @var list<array<string, mixed>>  $items
 * @var array<string, mixed>        $summary
 * @var array<string, mixed>        $checkout
 * @var array<string, array<string, string>> $testCards
 * @var array<string, list<string>> $errors
 */
$error = static fn (string $field): ?string => $errors[$field][0] ?? null;
?>
<?= $this->partial('partials/pasos', ['step' => 3]) ?>

<h1><?= $this->t('Pago simulado') ?></h1>

<div class="pagina-dos-columnas">
    <div>
        <div class="caja-simulacion">
            <h3>⚠ <?= $this->t('Esta pasarela no cobra dinero') ?></h3>
            <p style="margin:0">
                <?= $this->th('El pago se resuelve con un simulador interno. <strong>No introduzcas una tarjeta real.</strong> Usa cualquiera de estas tarjetas de prueba para ver los dos resultados posibles:') ?>
            </p>
            <table class="tarjetas-prueba">
                <caption class="solo-lectores"><?= $this->t('Tarjetas de prueba admitidas') ?></caption>
                <tbody>
                <?php foreach ($testCards as $number => $card): ?>
                    <tr>
                        <td><code><?= $this->e($number) ?></code></td>
                        <td>
                            <span class="insignia insignia--<?= $card['status'] === 'autorizado' ? 'success' : 'error' ?>">
                                <?php /* El resultado es un código interno del simulador: cada valor se traduce con
                                         su texto literal para que el comprobador de traducciones lo vea. */ ?>
                                <?php if ($card['status'] === 'autorizado'): ?>
                                    <?= $this->t('autorizado') ?>
                                <?php elseif ($card['status'] === 'rechazado'): ?>
                                    <?= $this->t('rechazado') ?>
                                <?php else: ?>
                                    <?= $this->e($card['status']) ?>
                                <?php endif; ?>
                            </span>
                        </td>
                        <td><?= $this->t($card['description']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p style="margin:.6rem 0 0; font-size:.82rem">
                <?= $this->t('Caducidad: cualquier fecha futura (por ejemplo 12/28). CVV: tres dígitos cualesquiera.') ?>
            </p>
        </div>

        <?php if ($errors !== []): ?>
            <div class="alerta alerta--error" role="alert">
                <span class="alerta__icono" aria-hidden="true"><span>!</span></span>
                <div>
                    <strong><?= $this->t('Revisa los datos de la tarjeta') ?></strong>
                    <ul style="margin:.4rem 0 0; padding-left:1.1rem">
                        <?php foreach ($errors as $fieldErrors): ?>
                            <li><?= $this->e($fieldErrors[0]) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <section class="tarjeta" style="margin-bottom:1.4rem">
            <h2 style="font-size:1.1rem"><?= $this->t('Enviar a') ?></h2>
            <p style="margin:0; color:var(--frambuesa-suave); line-height:1.7">
                <strong style="color:var(--frambuesa)"><?= $this->e($checkout['nombre']) ?></strong><br>
                <?= $this->e($checkout['direccion']) ?><br>
                <?= $this->e($checkout['codigo_postal']) ?> <?= $this->e($checkout['ciudad']) ?>
                (<?= $this->e($checkout['provincia']) ?>)<br>
                <?= $this->e($checkout['email']) ?> · <?= $this->e($checkout['telefono']) ?>
            </p>
            <p style="margin:.8rem 0 0">
                <a class="btn btn--secundario btn--pequeno" href="<?= $this->url('/checkout') ?>"><?= $this->t('Modificar datos') ?></a>
            </p>
        </section>

        <form method="post" action="<?= $this->url('/pago') ?>" novalidate autocomplete="off">
            <?= $this->csrf() ?>

            <section class="tarjeta">
                <h2 style="font-size:1.1rem"><?= $this->t('Datos de la tarjeta de prueba') ?></h2>

                <div class="campo">
                    <label for="titular"><?= $this->t('Titular de la tarjeta') ?> *</label>
                    <input type="text" id="titular" name="titular" required
                           placeholder="<?= $this->t('Nombre que aparece en la tarjeta') ?>"
                           value="<?= $this->e($checkout['nombre'] ?? '') ?>"
                           <?= $error('titular') ? 'aria-invalid="true"' : '' ?>>
                    <?php if ($error('titular')): ?>
                        <p class="campo__error"><?= $this->e($error('titular')) ?></p>
                    <?php endif; ?>
                </div>

                <div class="campo">
                    <label for="numero_tarjeta"><?= $this->t('Número de tarjeta') ?> *</label>
                    <input type="text" id="numero_tarjeta" name="numero_tarjeta" required
                           inputmode="numeric" maxlength="23" placeholder="4242 4242 4242 4242"
                           data-formato="tarjeta"
                           <?= $error('numero_tarjeta') ? 'aria-invalid="true" aria-describedby="err-tarjeta"' : '' ?>>
                    <p class="pista"><?= $this->t('Se valida con el algoritmo de Luhn. Solo guardamos los cuatro últimos dígitos.') ?></p>
                    <?php if ($error('numero_tarjeta')): ?>
                        <p class="campo__error" id="err-tarjeta"><?= $this->e($error('numero_tarjeta')) ?></p>
                    <?php endif; ?>
                </div>

                <div class="rejilla-campos">
                    <div class="campo">
                        <label for="caducidad"><?= $this->t('Caducidad (MM/AA)') ?> *</label>
                        <input type="text" id="caducidad" name="caducidad" required
                               placeholder="12/28" maxlength="5" data-formato="caducidad"
                               <?= $error('caducidad') ? 'aria-invalid="true"' : '' ?>>
                        <?php if ($error('caducidad')): ?>
                            <p class="campo__error"><?= $this->e($error('caducidad')) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="campo">
                        <label for="cvv"><?= $this->t('CVV') ?> *</label>
                        <input type="text" id="cvv" name="cvv" required inputmode="numeric"
                               maxlength="4" placeholder="123"
                               <?= $error('cvv') ? 'aria-invalid="true"' : '' ?>>
                        <p class="pista"><?= $this->t('No se almacena en ningún momento.') ?></p>
                        <?php if ($error('cvv')): ?>
                            <p class="campo__error"><?= $this->e($error('cvv')) ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php /* «data-texto-procesando» es el texto que kitsune.js pone en el botón mientras se
                         envía el formulario: va aquí para que salga en el idioma de la página. */ ?>
                <button class="btn btn--primario btn--grande btn--bloque" type="submit"
                        data-texto-procesando="<?= $this->t('Procesando el pago simulado…') ?>">
                    <?= $this->t('Pagar {importe} (simulado)', ['importe' => $this->money((int) $summary['total_cents'])]) ?>
                </button>

                <p style="font-size:.8rem; color:var(--frambuesa-tenue); margin:.8rem 0 0; text-align:center">
                    <?= $this->th('Al pulsar se genera el pedido y se registra el evento <code>payment.simulated</code>.') ?>
                </p>
            </section>
        </form>
    </div>

    <aside class="resumen" aria-label="<?= $this->t('Resumen del pedido') ?>">
        <?= $this->partial('partials/resumen', ['summary' => $summary]) ?>

        <ul style="list-style:none; padding:0; margin:1rem 0 0; font-size:.85rem; color:var(--frambuesa-suave)">
            <?php foreach ($items as $item): ?>
                <li style="display:flex; justify-content:space-between; gap:.6rem; padding:.2rem 0">
                    <span><?= (int) $item['quantity'] ?> × <?= $this->e($item['product']['name']) ?></span>
                    <span><?= $this->money((int) $item['line_total_cents']) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </aside>
</div>
