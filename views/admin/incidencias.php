<?php
/**
 * Bandeja de incidencias y solicitudes de soporte.
 *
 * @var \KitsuneNotes\Core\View $this
 * @var list<array<string, mixed>> $tickets
 * @var array<string, string>      $types
 * @var string                     $estado
 */
$statusTone = [
    'abierta'  => 'warning',
    'en_curso' => 'info',
    'resuelta' => 'success',
];
?>
<div class="admin__cabecera">
    <div>
        <h1>Soporte e incidencias</h1>
        <p style="margin:0; color:var(--frambuesa-suave)"><?= count($tickets) ?> solicitudes</p>
    </div>
</div>

<form class="filtros-linea" method="get" action="<?= $this->url('/admin/incidencias') ?>">
    <label class="solo-lectores" for="estado">Filtrar por estado</label>
    <select name="estado" id="estado" onchange="this.form.submit()">
        <option value="">Todos los estados</option>
        <?php foreach (['abierta' => 'Abiertas', 'en_curso' => 'En curso', 'resuelta' => 'Resueltas'] as $key => $label): ?>
            <option value="<?= $key ?>" <?= $estado === $key ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
    </select>
    <noscript><button class="btn btn--primario btn--pequeno" type="submit">Filtrar</button></noscript>
</form>

<?php if ($tickets === []): ?>
    <div class="panel">
        <div class="panel__cuerpo" style="text-align:center; color:var(--frambuesa-suave); padding:2.5rem">
            No hay solicitudes registradas con ese filtro.
        </div>
    </div>
<?php else: ?>
    <?php foreach ($tickets as $ticket): ?>
        <div class="panel">
            <div class="panel__cabecera">
                <div>
                    <h2 style="margin:0"><?= $this->e($ticket['subject']) ?></h2>
                    <p style="margin:.2rem 0 0; font-size:.85rem; color:var(--frambuesa-suave)">
                        <?= $this->e($ticket['reference']) ?> ·
                        <?= $this->e($types[$ticket['type']] ?? $ticket['type']) ?> ·
                        <?= $this->date($ticket['created_at']) ?>
                    </p>
                </div>
                <span class="insignia insignia--<?= $this->e($statusTone[$ticket['status']] ?? 'neutral') ?>">
                    <?= $this->e(str_replace('_', ' ', (string) $ticket['status'])) ?>
                </span>
            </div>

            <div class="panel__cuerpo">
                <p style="margin:0 0 .8rem; font-size:.88rem; color:var(--frambuesa-suave)">
                    <strong style="color:var(--frambuesa)"><?= $this->e($ticket['customer_name']) ?></strong>
                    · <?= $this->e($ticket['customer_email']) ?>
                    <?php if ($ticket['order_reference'] !== ''): ?>
                        · Pedido
                        <a href="<?= $this->url('/admin/pedidos/' . $ticket['order_reference']) ?>">
                            <?= $this->e($ticket['order_reference']) ?>
                        </a>
                    <?php endif; ?>
                </p>

                <blockquote style="margin:0 0 1rem; padding:.8rem 1rem; background:var(--nata-hundida); border-radius:var(--radio-s)">
                    <?= nl2br($this->e($ticket['message'])) ?>
                </blockquote>

                <form method="post"
                      action="<?= $this->url('/admin/incidencias/' . $ticket['reference'] . '/estado') ?>"
                      style="display:flex; gap:.5rem; align-items:center; flex-wrap:wrap">
                    <?= $this->csrf() ?>
                    <label class="solo-lectores" for="estado-<?= $this->e($ticket['reference']) ?>">
                        Nuevo estado de <?= $this->e($ticket['reference']) ?>
                    </label>
                    <select name="estado" id="estado-<?= $this->e($ticket['reference']) ?>" style="width:auto">
                        <option value="abierta"  <?= $ticket['status'] === 'abierta' ? 'selected' : '' ?>>Abierta</option>
                        <option value="en_curso" <?= $ticket['status'] === 'en_curso' ? 'selected' : '' ?>>En curso</option>
                        <option value="resuelta" <?= $ticket['status'] === 'resuelta' ? 'selected' : '' ?>>Resuelta</option>
                    </select>
                    <button class="btn btn--secundario btn--pequeno" type="submit">Guardar</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
