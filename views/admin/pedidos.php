<?php
/**
 * Listado de pedidos del back-office.
 *
 * @var \KitsuneNotes\Core\View $this
 * @var list<array<string, mixed>> $orders
 * @var array<string, string>      $filters
 * @var list<string>               $statuses
 */
?>
<div class="admin__cabecera">
    <div>
        <h1>Pedidos</h1>
        <p style="margin:0; color:var(--frambuesa-suave)"><?= count($orders) ?> pedidos encontrados</p>
    </div>
</div>

<form class="filtros-linea" method="get" action="<?= $this->url('/admin/pedidos') ?>">
    <label class="solo-lectores" for="estado">Filtrar por estado</label>
    <select name="estado" id="estado">
        <option value="">Todos los estados</option>
        <?php foreach ($statuses as $status): ?>
            <?php $badge = $this->statusBadge($status); ?>
            <option value="<?= $this->e($status) ?>" <?= ($filters['estado'] ?? '') === $status ? 'selected' : '' ?>>
                <?= $this->e($badge['label']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label class="solo-lectores" for="q">Buscar</label>
    <input type="search" name="q" id="q" placeholder="Referencia, cliente o correo"
           value="<?= $this->e($filters['q'] ?? '') ?>">

    <button class="btn btn--primario btn--pequeno" type="submit">Filtrar</button>
    <?php if (array_filter($filters)): ?>
        <a class="btn btn--secundario btn--pequeno" href="<?= $this->url('/admin/pedidos') ?>">Limpiar</a>
    <?php endif; ?>
</form>

<div class="panel">
    <div class="panel__cuerpo panel__cuerpo--sin-relleno">
        <div class="tabla-envoltorio">
            <table class="tabla">
                <caption class="solo-lectores">Pedidos registrados</caption>
                <thead>
                <tr>
                    <th scope="col">Referencia</th>
                    <th scope="col">Cliente</th>
                    <th scope="col">Estado</th>
                    <th scope="col" class="num">Líneas</th>
                    <th scope="col" class="num">Total</th>
                    <th scope="col">Creado</th>
                    <th scope="col"><span class="solo-lectores">Acciones</span></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($orders as $order): ?>
                    <?php $badge = $this->statusBadge((string) $order['status']); ?>
                    <tr>
                        <td><strong><?= $this->e($order['reference']) ?></strong></td>
                        <td>
                            <?= $this->e($order['customer_name']) ?><br>
                            <span style="font-size:.8rem; color:var(--frambuesa-tenue)"><?= $this->e($order['customer_email']) ?></span>
                        </td>
                        <td><span class="insignia insignia--<?= $this->e($badge['tone']) ?>"><?= $this->e($badge['label']) ?></span></td>
                        <td class="num"><?= (int) $order['line_count'] ?></td>
                        <td class="num"><?= $this->money((int) $order['total_cents']) ?></td>
                        <td><?= $this->date($order['created_at']) ?></td>
                        <td>
                            <a class="btn btn--secundario btn--pequeno"
                               href="<?= $this->url('/admin/pedidos/' . $order['reference']) ?>">Abrir</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($orders === []): ?>
                    <tr>
                        <td colspan="7" style="text-align:center; color:var(--frambuesa-suave); padding:2rem">
                            No hay pedidos que coincidan con el filtro.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
