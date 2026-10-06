<?php
/**
 * Panel de control del back-office.
 *
 * @var \KitsuneNotes\Core\View $this
 * @var array<string, int>         $counts
 * @var int                        $totalOrders
 * @var int                        $revenueCents
 * @var array<string, int>         $eventCounts
 * @var int                        $totalEvents
 * @var list<array<string, mixed>> $recentOrders
 * @var list<array<string, mixed>> $recentEvents
 * @var int                        $openTickets
 * @var list<array<string, mixed>> $lowStock
 * @var int                        $customers
 * @var int                        $declinedPayments
 */
?>
<div class="admin__cabecera">
    <div>
        <h1>Panel de control</h1>
        <p style="margin:0; color:var(--frambuesa-suave)">Resumen del canal digital · datos de prueba</p>
    </div>
    <a class="btn btn--secundario btn--pequeno" href="<?= $this->url('/admin/eventos') ?>">Ver eventos</a>
</div>

<div class="metricas">
    <div class="metrica">
        <p class="metrica__etiqueta">Pedidos</p>
        <p class="metrica__valor"><?= $totalOrders ?></p>
        <p class="metrica__nota"><?= $customers ?> clientes registrados</p>
    </div>
    <div class="metrica">
        <p class="metrica__etiqueta">Importe acumulado</p>
        <p class="metrica__valor"><?= $this->money($revenueCents) ?></p>
        <p class="metrica__nota">Simulado, sin cancelados</p>
    </div>
    <div class="metrica">
        <p class="metrica__etiqueta">Eventos registrados</p>
        <p class="metrica__valor"><?= $totalEvents ?></p>
        <p class="metrica__nota"><?= count($eventCounts) ?> tipos distintos</p>
    </div>
    <div class="metrica">
        <p class="metrica__etiqueta">Incidencias abiertas</p>
        <p class="metrica__valor"><?= $openTickets ?></p>
        <p class="metrica__nota"><?= $declinedPayments ?> pagos rechazados</p>
    </div>
</div>

<div class="panel">
    <div class="panel__cabecera">
        <h2>Pedidos por estado</h2>
    </div>
    <div class="panel__cuerpo">
        <?php if ($counts === []): ?>
            <p style="margin:0; color:var(--frambuesa-suave)">Todavía no hay pedidos.</p>
        <?php else: ?>
            <div style="display:flex; gap:.6rem; flex-wrap:wrap">
                <?php foreach ($counts as $status => $total): ?>
                    <?php $badge = $this->statusBadge((string) $status); ?>
                    <a class="insignia insignia--<?= $this->e($badge['tone']) ?>"
                       style="text-decoration:none; padding:.5rem 1rem; font-size:.9rem"
                       href="<?= $this->url('/admin/pedidos') ?>?estado=<?= urlencode((string) $status) ?>">
                        <?= $this->e($badge['label']) ?>
                        <strong style="margin-left:.3rem"><?= (int) $total ?></strong>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="panel">
    <div class="panel__cabecera">
        <h2>Últimos pedidos</h2>
        <a class="btn btn--secundario btn--pequeno" href="<?= $this->url('/admin/pedidos') ?>">Ver todos</a>
    </div>
    <div class="panel__cuerpo panel__cuerpo--sin-relleno">
        <div class="tabla-envoltorio">
            <table class="tabla">
                <thead>
                <tr>
                    <th scope="col">Referencia</th>
                    <th scope="col">Cliente</th>
                    <th scope="col">Estado</th>
                    <th scope="col" class="num">Total</th>
                    <th scope="col">Fecha</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($recentOrders as $order): ?>
                    <?php $badge = $this->statusBadge((string) $order['status']); ?>
                    <tr>
                        <td><a href="<?= $this->url('/admin/pedidos/' . $order['reference']) ?>"><?= $this->e($order['reference']) ?></a></td>
                        <td>
                            <?= $this->e($order['customer_name']) ?><br>
                            <span style="font-size:.8rem; color:var(--frambuesa-tenue)"><?= $this->e($order['customer_email']) ?></span>
                        </td>
                        <td><span class="insignia insignia--<?= $this->e($badge['tone']) ?>"><?= $this->e($badge['label']) ?></span></td>
                        <td class="num"><?= $this->money((int) $order['total_cents']) ?></td>
                        <td><?= $this->date($order['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($recentOrders === []): ?>
                    <tr><td colspan="5" style="text-align:center; color:var(--frambuesa-suave)">Sin pedidos todavía.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="panel">
    <div class="panel__cabecera">
        <h2>Actividad reciente</h2>
        <a class="btn btn--secundario btn--pequeno" href="<?= $this->url('/admin/eventos') ?>">Ver todos los eventos</a>
    </div>
    <div class="panel__cuerpo panel__cuerpo--sin-relleno">
        <div class="tabla-envoltorio">
            <table class="tabla">
                <thead>
                <tr>
                    <th scope="col">Evento</th>
                    <th scope="col">Referencia</th>
                    <th scope="col">Momento</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($recentEvents as $event): ?>
                    <tr>
                        <td><span class="evento-nombre"><?= $this->e($event['event_name']) ?></span></td>
                        <td>
                            <?php if (!empty($event['order_reference'])): ?>
                                <?= $this->e($event['order_reference']) ?>
                            <?php elseif (!empty($event['product_sku'])): ?>
                                <?= $this->e($event['product_sku']) ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td><?= $this->date($event['occurred_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($recentEvents === []): ?>
                    <tr><td colspan="3" style="text-align:center; color:var(--frambuesa-suave)">Sin eventos registrados.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($lowStock !== []): ?>
    <div class="panel">
        <div class="panel__cabecera">
            <h2>Stock bajo (≤ 20 unidades)</h2>
        </div>
        <div class="panel__cuerpo panel__cuerpo--sin-relleno">
            <div class="tabla-envoltorio">
                <table class="tabla">
                    <thead>
                    <tr>
                        <th scope="col">Referencia</th>
                        <th scope="col">Producto</th>
                        <th scope="col" class="num">Unidades</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($lowStock as $product): ?>
                        <tr>
                            <td><?= $this->e($product['sku']) ?></td>
                            <td><?= $this->e($product['name']) ?></td>
                            <td class="num"><?= (int) $product['stock'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>
