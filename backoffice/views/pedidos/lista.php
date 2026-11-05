<?php
/**
 * @var \KitsuneGestion\Core\View $this
 * @var list<array<string, mixed>> $pedidos
 * @var string|null $estado
 */
use KitsuneGestion\Service\Pedidos;
?>
<div class="titular">
    <h1>Pedidos</h1>
    <p class="suave">Llegan solos desde la tienda a través de n8n (evento <code>order.created</code>).</p>
</div>

<nav class="filtros" aria-label="Filtrar por estado">
    <a href="<?= $this->url('/pedidos') ?>"<?= $estado === null ? ' aria-current="true"' : '' ?>>Todos</a>
    <?php foreach (Pedidos::ESTADOS as $opcion): ?>
        <a href="<?= $this->url('/pedidos?estado=' . $opcion) ?>"<?= $estado === $opcion ? ' aria-current="true"' : '' ?>><?= $this->e($this->etiqueta($opcion)) ?></a>
    <?php endforeach; ?>
</nav>

<?php if ($pedidos === []): ?>
    <p class="vacio">Todavía no ha llegado ningún pedido<?= $estado !== null ? ' en este estado' : '' ?>. Haz una compra en la tienda y aparecerá aquí.</p>
<?php else: ?>
    <div class="tabla-envoltorio">
        <table class="tabla">
            <thead>
            <tr><th>Referencia</th><th>Cliente</th><th>Fecha</th><th>Total</th><th>Estado</th><th>Pago</th><th>Incid.</th><th>Sincronización</th></tr>
            </thead>
            <tbody>
            <?php foreach ($pedidos as $p): ?>
                <tr>
                    <td><a href="<?= $this->url('/pedidos/' . $p['referencia']) ?>"><strong><?= $this->e($p['referencia']) ?></strong></a></td>
                    <td><?= $this->e($p['cliente_nombre'] ?? '—') ?></td>
                    <td><?= $this->fecha($p['creado_en_tienda']) ?></td>
                    <td class="numero"><?= $this->money((int) $p['total_cents'], (string) $p['moneda']) ?></td>
                    <td><?= $this->insignia($p['estado']) ?></td>
                    <td><?= $this->insignia($p['estado_pago']) ?></td>
                    <td class="numero"><?= (int) $p['incidencias_abiertas'] ?: '—' ?></td>
                    <td><?= $p['ultima_sincronizacion'] ? $this->insignia($p['ultima_sincronizacion']) : '<span class="suave">—</span>' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
