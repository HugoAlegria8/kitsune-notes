<?php
/**
 * Condiciones comerciales simuladas.
 *
 * @var \KitsuneNotes\Core\View $this
 * @var array<string, array<string, mixed>> $shippingMethods
 * @var int                                 $giftwrapCents
 * @var list<array<string, mixed>>          $coupons
 */
?>
<div style="max-width:760px; margin-inline:auto">
    <h1>Envíos, devoluciones y condiciones</h1>

    <div class="alerta alerta--aviso">
        <span class="alerta__icono" aria-hidden="true"><span>!</span></span>
        <div>Todas las condiciones de esta página son <strong>simuladas</strong>: describen las reglas
            de negocio implementadas en el prototipo, no un servicio real.</div>
    </div>

    <h2>Métodos de envío</h2>
    <div class="tabla-envoltorio">
        <table class="tabla">
            <thead>
            <tr>
                <th scope="col">Método</th>
                <th scope="col">Plazo</th>
                <th scope="col" class="num">Precio</th>
                <th scope="col">Gratis desde</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($shippingMethods as $method): ?>
                <tr>
                    <td><strong><?= $this->e($method['label']) ?></strong></td>
                    <td><?= $this->e($method['description']) ?></td>
                    <td class="num"><?= $this->money((int) $method['price_cents']) ?></td>
                    <td>
                        <?php if (($method['free_from_cents'] ?? null) !== null): ?>
                            <?= $this->money((int) $method['free_from_cents']) ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h2>Impuestos</h2>
    <p>
        Todos los precios del catálogo se muestran con el <strong>IVA español del 21 %</strong>
        incluido, como exige la normativa de protección al consumidor en venta a particulares.
        En el resumen del pedido desglosamos la base imponible y la cuota de IVA, y ese desglose
        se guarda junto al pedido para que la factura siga siendo reproducible aunque el tipo
        impositivo cambie en el futuro.
    </p>

    <h2>Servicios adicionales</h2>
    <p>
        Envoltorio furoshiki de regalo por <?= $this->money($giftwrapCents) ?> por pedido,
        seleccionable en el paso de datos de envío.
    </p>

    <h2>Códigos de descuento activos</h2>
    <div class="tabla-envoltorio">
        <table class="tabla">
            <thead>
            <tr>
                <th scope="col">Código</th>
                <th scope="col">Descuento</th>
                <th scope="col">Condición</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($coupons as $coupon): ?>
                <tr>
                    <td><code><?= $this->e($coupon['code']) ?></code></td>
                    <td>
                        <?= $coupon['type'] === 'percent'
                            ? (int) $coupon['value'] . ' %'
                            : $this->money((int) $coupon['value']) ?>
                    </td>
                    <td><?= $this->e($coupon['description']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h2>Devoluciones</h2>
    <p>
        Condición simulada: 30 días naturales desde la entrega para devolver artículos sin usar.
        En el prototipo, una devolución se solicita desde el
        <a href="<?= $this->url('/soporte') ?>">formulario de soporte</a> y genera una incidencia
        vinculada al pedido.
    </p>
</div>
