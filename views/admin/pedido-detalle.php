<?php
/**
 * Detalle de pedido en el back-office, con cambio de estado.
 *
 * @var \KitsuneNotes\Core\View $this
 * @var array<string, mixed>       $order
 * @var list<array<string, mixed>> $lines
 * @var list<array<string, mixed>> $payments
 * @var list<array<string, mixed>> $history
 * @var list<string>               $transitions
 * @var array<string, mixed>|null  $invoice
 * @var list<array<string, mixed>> $mails
 * @var array<string, string>      $templates
 * @var bool                       $canInvoice
 */
$badge = $this->statusBadge((string) $order['status']);

// Moneda e idioma de la compra. Los importes del pedido se muestran siempre en
// SU moneda; si no es el euro, se añade el contravalor que se guardó con el pedido.
$moneda     = (string) $order['currency'];
$monedaBase = $this->app()->currency()->base();
$idioma     = $this->app()->translator()->info('label', (string) ($order['locale'] ?? 'es')) ?: (string) ($order['locale'] ?? 'es');
?>
<div class="admin__cabecera">
    <div>
        <p style="margin:0 0 .2rem; font-size:.85rem">
            <a href="<?= $this->url('/admin/pedidos') ?>">← Volver a pedidos</a>
        </p>
        <h1>Pedido <?= $this->e($order['reference']) ?></h1>
        <p style="margin:0; color:var(--frambuesa-suave)">
            Creado el <?= $this->date($order['created_at']) ?> ·
            Última actualización <?= $this->date($order['updated_at']) ?>
        </p>
        <p style="margin:.25rem 0 0; color:var(--frambuesa-suave); font-size:.9rem">
            Idioma de la compra: <strong><?= $this->e($idioma) ?></strong> ·
            Moneda: <strong><?= $this->e($moneda) ?></strong>.
            Los correos y la factura de este pedido salen en ese idioma y en esa moneda.
        </p>
    </div>
    <span class="insignia insignia--<?= $this->e($badge['tone']) ?>" style="padding:.5rem 1.1rem; font-size:.92rem">
        <?= $this->e($badge['label']) ?>
    </span>
</div>

<div style="display:grid; grid-template-columns:minmax(0,1.6fr) minmax(280px,1fr); gap:1.4rem; align-items:start">
    <div>
        <div class="panel">
            <div class="panel__cabecera"><h2>Líneas del pedido</h2></div>
            <div class="panel__cuerpo panel__cuerpo--sin-relleno">
                <div class="tabla-envoltorio">
                    <table class="tabla">
                        <thead>
                        <tr>
                            <th scope="col">SKU</th>
                            <th scope="col">Producto</th>
                            <th scope="col" class="num">P. unitario</th>
                            <th scope="col" class="num">Uds.</th>
                            <th scope="col" class="num">Importe</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($lines as $line): ?>
                            <tr>
                                <td><?= $this->e($line['sku']) ?></td>
                                <td>
                                    <?= $this->e($line['name']) ?><br>
                                    <span style="font-size:.8rem; color:var(--frambuesa-tenue)"><?= $this->e($line['design_line']) ?></span>
                                </td>
                                <td class="num"><?= $this->money((int) $line['unit_price_cents'], $moneda) ?></td>
                                <td class="num"><?= (int) $line['quantity'] ?></td>
                                <td class="num"><?= $this->money((int) $line['line_total_cents'], $moneda) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel__cabecera"><h2>Intentos de pago simulados</h2></div>
            <div class="panel__cuerpo panel__cuerpo--sin-relleno">
                <div class="tabla-envoltorio">
                    <table class="tabla">
                        <thead>
                        <tr>
                            <th scope="col">Referencia</th>
                            <th scope="col">Resultado</th>
                            <th scope="col">Tarjeta</th>
                            <th scope="col">Autorización</th>
                            <th scope="col">Momento</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?= $this->e($payment['reference']) ?></td>
                                <td>
                                    <span class="insignia insignia--<?= $payment['status'] === 'autorizado' ? 'success' : 'error' ?>">
                                        <?= $this->e($payment['status']) ?>
                                    </span>
                                    <?php if ($payment['decline_reason'] !== ''): ?>
                                        <br><span style="font-size:.8rem; color:var(--error)"><?= $this->e($payment['decline_reason']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $this->e($payment['card_brand']) ?> ••••<?= $this->e($payment['card_last4']) ?></td>
                                <td><?= $payment['authorization_code'] !== '' ? $this->e($payment['authorization_code']) : '—' ?></td>
                                <td><?= $this->date($payment['processed_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($payments === []): ?>
                            <tr><td colspan="5" style="text-align:center; color:var(--frambuesa-suave)">Sin intentos de pago.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel__cabecera"><h2>Histórico de estados</h2></div>
            <div class="panel__cuerpo">
                <ul class="cronologia">
                    <?php foreach ($history as $entry): ?>
                        <?php $entryBadge = $this->statusBadge((string) $entry['to_status']); ?>
                        <li>
                            <strong><?= $this->e($entryBadge['label']) ?></strong>
                            <?php if ($entry['from_status'] !== null): ?>
                                <span style="color:var(--frambuesa-tenue); font-size:.82rem">
                                    (desde <?= $this->e($entry['from_status']) ?>)
                                </span>
                            <?php endif; ?>
                            <time datetime="<?= $this->e($entry['created_at']) ?>">
                                <?= $this->date($entry['created_at']) ?> · <?= $this->e($entry['changed_by']) ?>
                            </time>
                            <?php if ($entry['note'] !== ''): ?>
                                <span style="color:var(--frambuesa-suave)"><?= $this->e($entry['note']) ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>

    <div>
        <div class="panel">
            <div class="panel__cabecera"><h2>Cambiar estado</h2></div>
            <div class="panel__cuerpo">
                <?php if ($transitions === []): ?>
                    <p style="margin:0; color:var(--frambuesa-suave)">
                        Este pedido está en un estado final: no admite más transiciones.
                    </p>
                <?php else: ?>
                    <form method="post" action="<?= $this->url('/admin/pedidos/' . $order['reference'] . '/estado') ?>">
                        <?= $this->csrf() ?>

                        <div class="campo">
                            <label for="estado">Nuevo estado</label>
                            <select name="estado" id="estado" required>
                                <?php foreach ($transitions as $status): ?>
                                    <?php $t = $this->statusBadge($status); ?>
                                    <option value="<?= $this->e($status) ?>"><?= $this->e($t['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="campo">
                            <label for="nota">Nota interna</label>
                            <textarea id="nota" name="nota" maxlength="500"
                                      placeholder="Motivo del cambio, número de seguimiento…"></textarea>
                        </div>

                        <button class="btn btn--primario btn--bloque" type="submit">Actualizar estado</button>
                        <p style="font-size:.78rem; color:var(--frambuesa-tenue); margin:.7rem 0 0">
                            El cambio queda registrado en el histórico y emite el evento
                            <code>order.status_changed</code>.
                        </p>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="panel">
            <div class="panel__cabecera"><h2>Cliente y envío</h2></div>
            <div class="panel__cuerpo" style="font-size:.9rem; color:var(--frambuesa-suave); line-height:1.75">
                <strong style="color:var(--frambuesa)"><?= $this->e($order['customer_name']) ?></strong><br>
                <?= $this->e($order['customer_email']) ?><br>
                <?= $this->e($order['customer_phone']) ?><br><br>
                <?= $this->e($order['shipping_address']) ?><br>
                <?= $this->e($order['shipping_postal_code']) ?> <?= $this->e($order['shipping_city']) ?>
                (<?= $this->e($order['shipping_province']) ?>)<br>
                Método: <?= $this->e($this->shippingLabel((string) $order['shipping_method'])) ?>
                <?php if ((int) $order['gift_wrap'] === 1): ?>
                    <br>Con envoltorio de regalo
                <?php endif; ?>
                <?php if ($order['customer_notes'] !== ''): ?>
                    <br><br><em><?= $this->e($order['customer_notes']) ?></em>
                <?php endif; ?>
            </div>
        </div>

        <div class="panel">
            <div class="panel__cabecera"><h2>Factura y correos</h2></div>
            <div class="panel__cuerpo">
                <?php if ($invoice !== null): ?>
                    <p style="margin:0 0 .8rem">
                        <strong><?= $this->e($invoice['number']) ?></strong><br>
                        <span style="font-size:.88rem; color:var(--frambuesa-suave)">
                            Expedida el <?= $this->date($invoice['issued_at']) ?> · <?= $this->money((int) $invoice['total_cents'], $moneda) ?>
                        </span>
                    </p>
                    <p style="margin:0 0 1rem">
                        <a class="btn btn--secundario btn--pequeno"
                           href="<?= $this->url('/admin/pedidos/' . $order['reference'] . '/factura') ?>">Ver factura</a>
                    </p>
                <?php else: ?>
                    <p style="margin:0 0 1rem; color:var(--frambuesa-suave); font-size:.9rem">
                        Este pedido todavía no tiene factura.
                        <?= $canInvoice
                            ? 'Tiene un pago autorizado, así que se puede expedir ahora.'
                            : 'Se expide sola cuando el pago queda autorizado.' ?>
                    </p>
                <?php endif; ?>

                <p class="etiqueta">Correos enviados al cliente</p>
                <?php if ($mails === []): ?>
                    <p style="margin:0; color:var(--frambuesa-suave); font-size:.9rem">Todavía no se ha enviado ninguno.</p>
                <?php else: ?>
                    <ul class="lista-correos">
                        <?php foreach ($mails as $mail): ?>
                            <li>
                                <a href="<?= $this->url('/admin/correos/' . (int) $mail['id']) ?>">
                                    <?= $this->e($templates[$mail['template']] ?? $mail['template']) ?>
                                </a>
                                <?php if ($mail['delivery_status'] !== 'solo_buzon'): ?>
                                    <?php $deliveryBadge = $this->deliveryBadge((string) $mail['delivery_status']); ?>
                                    <span class="insignia insignia--<?= $this->e($deliveryBadge['tone']) ?>">
                                        <?= $this->e($deliveryBadge['label']) ?>
                                    </span>
                                <?php endif; ?>
                                <time datetime="<?= $this->e($mail['created_at']) ?>"><?= $this->date($mail['created_at']) ?></time>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <?php if ($canInvoice): ?>
                    <form method="post" action="<?= $this->url('/admin/pedidos/' . $order['reference'] . '/factura') ?>"
                          style="margin-top:1rem">
                        <?= $this->csrf() ?>
                        <button class="btn btn--lila btn--bloque btn--pequeno" type="submit">
                            <?= $invoice === null ? 'Expedir factura y enviar correo' : 'Reenviar confirmación y factura' ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="panel">
            <div class="panel__cabecera"><h2>Importe</h2></div>
            <div class="panel__cuerpo">
                <div class="resumen__fila"><span>Artículos</span><span><?= $this->money((int) $order['items_total_cents'], $moneda) ?></span></div>
                <?php if ((int) $order['discount_cents'] > 0): ?>
                    <div class="resumen__fila resumen__fila--descuento">
                        <span>Descuento <?= $this->e($order['coupon_code']) ?></span>
                        <span>−<?= $this->money((int) $order['discount_cents'], $moneda) ?></span>
                    </div>
                <?php endif; ?>
                <div class="resumen__fila"><span>Envío</span><span><?= $this->money((int) $order['shipping_cents'], $moneda) ?></span></div>
                <?php if ((int) $order['giftwrap_cents'] > 0): ?>
                    <div class="resumen__fila"><span>Envoltorio</span><span><?= $this->money((int) $order['giftwrap_cents'], $moneda) ?></span></div>
                <?php endif; ?>
                <div class="resumen__fila" style="font-size:.85rem; color:var(--frambuesa-tenue)">
                    <span>Base imponible</span><span><?= $this->money((int) $order['taxable_base_cents'], $moneda) ?></span>
                </div>
                <div class="resumen__fila" style="font-size:.85rem; color:var(--frambuesa-tenue)">
                    <span>IVA</span><span><?= $this->money((int) $order['tax_cents'], $moneda) ?></span>
                </div>
                <div class="resumen__fila resumen__fila--total">
                    <span>Total</span><span><?= $this->money((int) $order['total_cents'], $moneda) ?></span>
                </div>
                <?php if ($moneda !== $monedaBase): ?>
                    <div class="resumen__fila" style="font-size:.85rem; color:var(--frambuesa-tenue)">
                        <span>Contravalor en euros</span>
                        <span><?= $this->money((int) $order['total_base_cents'], $monedaBase) ?></span>
                    </div>
                    <p class="pista" style="margin:.5rem 0 0; font-size:.82rem">
                        Tipo de cambio aplicado al crear el pedido:
                        <?= $this->e($this->app()->currency()->rateLabel($moneda, (int) $order['fx_rate_micros'])) ?>
                        (tipo fijo de demostración). El panel de control suma este contravalor.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
