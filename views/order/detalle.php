<?php
/**
 * Detalle de pedido para el cliente (también sirve de confirmación).
 *
 * @var \KitsuneNotes\Core\View $this
 * @var array<string, mixed>       $order
 * @var list<array<string, mixed>> $lines
 * @var list<array<string, mixed>> $payments
 * @var list<array<string, mixed>> $history
 * @var bool                       $justPlaced
 * @var array<string, mixed>|null  $invoice
 * @var array{status:string, smtp:bool} $mailInfo resultado de la entrega del correo de confirmación
 */
$mailStatus = (string) ($mailInfo['status'] ?? 'solo_buzon');
$smtpOn     = !empty($mailInfo['smtp']);
$mailboxUrl = $this->url('/admin/correos') . '?q=' . urlencode((string) $order['reference']);
$badge      = $this->statusBadge((string) $order['status']);
$payment = $payments[0] ?? null;

foreach ($payments as $candidate) {
    if ($candidate['status'] === 'autorizado') {
        $payment = $candidate;
    }
}
?>
<?= $this->partial('partials/pasos', ['step' => 4]) ?>

<?php if ($justPlaced): ?>
    <div class="confirmacion">
        <img src="<?= $this->asset('assets/img/mascotas/kitsune.svg') ?>" alt="" width="130" height="98">
        <h1 style="margin:.2rem 0">¡Pedido confirmado! <span class="kaomoji" aria-hidden="true">٩(◕‿◕)۶</span></h1>
        <p class="confirmacion__referencia"><?= $this->e($order['reference']) ?></p>
        <p style="margin:0; color:var(--frambuesa-suave); font-weight:700">
            <?php if ($mailStatus === 'fallido'): ?>
                Tu pedido está confirmado, pero no hemos podido enviar el correo a
                <?= $this->e($order['customer_email']) ?>.
                <?php if ($invoice !== null): ?>
                    Tu factura <?= $this->e($invoice['number']) ?> está disponible en esta misma página.
                <?php endif; ?>
            <?php elseif ($invoice !== null): ?>
                Te hemos enviado la confirmación y la factura <?= $this->e($invoice['number']) ?>
                a <?= $this->e($order['customer_email']) ?>.
            <?php else: ?>
                Hemos enviado la confirmación a <?= $this->e($order['customer_email']) ?>.
            <?php endif; ?>
            <?php if ($mailStatus === 'enviado'): ?>
                Si no la ves en unos minutos, revisa la carpeta de spam.
            <?php endif; ?>
            Guarda la referencia: con ella y tu correo puedes consultar el estado cuando quieras.
        </p>
        <p style="margin:.8rem 0 0; font-size:.86rem; color:var(--frambuesa-suave)">
            <?php if ($mailStatus === 'enviado'): ?>
                Prototipo académico: el correo es real, pero el pedido, el pago y la factura son ficticios.
            <?php elseif ($mailStatus === 'fallido'): ?>
                Prototipo: el mensaje ha quedado guardado en el
                <a href="<?= $mailboxUrl ?>">buzón de pruebas del back-office</a>, donde el equipo puede ver el motivo del fallo.
            <?php elseif ($smtpOn): ?>
                Prototipo: esta dirección no está autorizada para recibir correo real, así que el mensaje
                solo queda en el <a href="<?= $mailboxUrl ?>">buzón de pruebas del back-office</a>.
            <?php else: ?>
                Prototipo: el correo no sale de la aplicación. El equipo puede abrirlo en el
                <a href="<?= $mailboxUrl ?>">buzón de pruebas del back-office</a>.
            <?php endif; ?>
        </p>
    </div>
<?php else: ?>
    <h1>Pedido <?= $this->e($order['reference']) ?></h1>
<?php endif; ?>

<div class="pagina-dos-columnas">
    <div>
        <section class="tarjeta" style="margin-bottom:1.4rem">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:1rem; flex-wrap:wrap">
                <h2 style="font-size:1.1rem; margin:0">Estado del pedido</h2>
                <span class="insignia insignia--<?= $this->e($badge['tone']) ?>"><?= $this->e($badge['label']) ?></span>
            </div>

            <ul class="cronologia" style="margin-top:1.2rem">
                <?php foreach ($history as $entry): ?>
                    <?php $entryBadge = $this->statusBadge((string) $entry['to_status']); ?>
                    <li>
                        <strong><?= $this->e($entryBadge['label']) ?></strong>
                        <time datetime="<?= $this->e($entry['created_at']) ?>"><?= $this->date($entry['created_at']) ?></time>
                        <?php if ($entry['note'] !== ''): ?>
                            <span style="color:var(--frambuesa-suave)"><?= $this->e($entry['note']) ?></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section class="tarjeta" style="margin-bottom:1.4rem">
            <h2 style="font-size:1.1rem">Artículos</h2>
            <div class="tabla-envoltorio">
                <table class="tabla">
                    <caption class="solo-lectores">Líneas del pedido <?= $this->e($order['reference']) ?></caption>
                    <thead>
                    <tr>
                        <th scope="col">Producto</th>
                        <th scope="col" class="num">Precio</th>
                        <th scope="col" class="num">Uds.</th>
                        <th scope="col" class="num">Importe</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($lines as $line): ?>
                        <tr>
                            <td>
                                <strong><?= $this->e($line['name']) ?></strong><br>
                                <span style="font-size:.82rem; color:var(--frambuesa-tenue)">
                                    <?= $this->e($line['sku']) ?> · <?= $this->e($line['design_line']) ?>
                                </span>
                            </td>
                            <td class="num"><?= $this->money((int) $line['unit_price_cents']) ?></td>
                            <td class="num"><?= (int) $line['quantity'] ?></td>
                            <td class="num"><?= $this->money((int) $line['line_total_cents']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="tarjeta">
            <h2 style="font-size:1.1rem">Entrega y pago</h2>
            <div class="rejilla-campos">
                <div>
                    <p class="etiqueta">Dirección de envío</p>
                    <p style="color:var(--frambuesa-suave); line-height:1.7">
                        <?= $this->e($order['shipping_name']) ?><br>
                        <?= $this->e($order['shipping_address']) ?><br>
                        <?= $this->e($order['shipping_postal_code']) ?> <?= $this->e($order['shipping_city']) ?>
                        (<?= $this->e($order['shipping_province']) ?>)
                    </p>
                </div>
                <div>
                    <p class="etiqueta">Pago simulado</p>
                    <?php if ($payment !== null): ?>
                        <p style="color:var(--frambuesa-suave); line-height:1.7">
                            <?= $this->e($payment['card_brand']) ?> •••• <?= $this->e($payment['card_last4']) ?><br>
                            Referencia <?= $this->e($payment['reference']) ?><br>
                            <?php if ($payment['authorization_code'] !== ''): ?>
                                Autorización <?= $this->e($payment['authorization_code']) ?>
                            <?php else: ?>
                                <span style="color:var(--error)"><?= $this->e($payment['decline_reason']) ?></span>
                            <?php endif; ?>
                        </p>
                    <?php else: ?>
                        <p style="color:var(--frambuesa-suave)">Todavía sin intentos de pago registrados.</p>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($order['customer_notes'] !== ''): ?>
                <p class="etiqueta" style="margin-top:1rem">Notas para la entrega</p>
                <p style="color:var(--frambuesa-suave)"><?= $this->e($order['customer_notes']) ?></p>
            <?php endif; ?>
        </section>
    </div>

    <aside class="resumen" aria-label="Importe del pedido">
        <h2>Importe</h2>

        <div class="resumen__fila">
            <span>Artículos</span>
            <span><?= $this->money((int) $order['items_total_cents']) ?></span>
        </div>

        <?php if ((int) $order['discount_cents'] > 0): ?>
            <div class="resumen__fila resumen__fila--descuento">
                <span>Descuento <?= $this->e($order['coupon_code']) ?></span>
                <span>−<?= $this->money((int) $order['discount_cents']) ?></span>
            </div>
        <?php endif; ?>

        <div class="resumen__fila">
            <span>Envío (<?= $this->e($this->shippingLabel((string) $order['shipping_method'])) ?>)</span>
            <span><?= (int) $order['shipping_cents'] === 0 ? 'Gratis' : $this->money((int) $order['shipping_cents']) ?></span>
        </div>

        <?php if ((int) $order['giftwrap_cents'] > 0): ?>
            <div class="resumen__fila">
                <span>Envoltorio furoshiki</span>
                <span><?= $this->money((int) $order['giftwrap_cents']) ?></span>
            </div>
        <?php endif; ?>

        <div class="resumen__fila" style="color:var(--frambuesa-tenue); font-size:.85rem">
            <span>Base imponible</span>
            <span><?= $this->money((int) $order['taxable_base_cents']) ?></span>
        </div>

        <div class="resumen__fila" style="color:var(--frambuesa-tenue); font-size:.85rem">
            <span>IVA 21 %</span>
            <span><?= $this->money((int) $order['tax_cents']) ?></span>
        </div>

        <div class="resumen__fila resumen__fila--total">
            <span>Total</span>
            <span><?= $this->money((int) $order['total_cents']) ?></span>
        </div>

        <?php if ($invoice !== null): ?>
            <p style="margin-top:1.2rem">
                <a class="btn btn--lila btn--bloque"
                   href="<?= $this->url('/pedido/' . $order['reference'] . '/factura') ?>">
                    Ver factura <?= $this->e($invoice['number']) ?>
                </a>
            </p>
        <?php endif; ?>
        <p style="margin-top:<?= $invoice !== null ? '.6rem' : '1.2rem' ?>">
            <a class="btn btn--secundario btn--bloque"
               href="<?= $this->url('/soporte') ?>?pedido=<?= urlencode((string) $order['reference']) ?>">
                Abrir una incidencia
            </a>
        </p>
        <p style="margin-top:.6rem">
            <a class="btn btn--primario btn--bloque" href="<?= $this->url('/catalogo') ?>">Seguir comprando</a>
        </p>

        <p class="resumen__nota">
            Pedido generado el <?= $this->date($order['created_at']) ?>. Ningún importe ha sido
            cobrado: el pago es una simulación del prototipo académico.
        </p>
    </aside>
</div>
