<?php
/**
 * @var \KitsuneGestion\Core\View $this
 * @var list<array<string, mixed>> $incidencias
 * @var string|null $estado
 */
use KitsuneGestion\Service\Incidencias;
?>
<div class="titular">
    <h1>Incidencias</h1>
    <p class="suave">Llegan desde el formulario de soporte de la tienda y desde Telegram. El agente de IA las analiza y propone una respuesta.</p>
</div>

<nav class="filtros" aria-label="Filtrar por estado">
    <a href="<?= $this->url('/incidencias') ?>"<?= $estado === null ? ' aria-current="true"' : '' ?>>Todas</a>
    <?php foreach (Incidencias::ESTADOS as $opcion): ?>
        <a href="<?= $this->url('/incidencias?estado=' . $opcion) ?>"<?= $estado === $opcion ? ' aria-current="true"' : '' ?>><?= $this->e($this->etiqueta($opcion)) ?></a>
    <?php endforeach; ?>
</nav>

<?php if ($incidencias === []): ?>
    <p class="vacio">No hay incidencias<?= $estado !== null ? ' en este estado' : '' ?>.</p>
<?php else: ?>
    <div class="tabla-envoltorio">
        <table class="tabla">
            <thead><tr><th>#</th><th>Asunto</th><th>Pedido</th><th>Cliente</th><th>Categoría</th><th>Prioridad</th><th>Estado</th><th>Propuestas</th><th>Alta</th></tr></thead>
            <tbody>
            <?php foreach ($incidencias as $i): ?>
                <tr>
                    <td><a href="<?= $this->url('/incidencias/' . $i['id']) ?>"><strong>#<?= (int) $i['id'] ?></strong></a></td>
                    <td><a href="<?= $this->url('/incidencias/' . $i['id']) ?>"><?= $this->e($i['asunto'] ?: Incidencias::TIPOS[$i['tipo']] ?? $i['tipo']) ?></a>
                        <br><span class="suave"><?= $this->e(Incidencias::TIPOS[$i['tipo']] ?? $i['tipo']) ?> · <?= $this->e($i['canal']) ?></span></td>
                    <td><?= $i['pedido_referencia'] ? '<a href="' . $this->url('/pedidos/' . $i['pedido_referencia']) . '">' . $this->e($i['pedido_referencia']) . '</a>' : '—' ?></td>
                    <td><?= $this->e($i['cliente_nombre'] ?? '—') ?></td>
                    <td><?= $this->e(Incidencias::CATEGORIAS[$i['categoria'] ?? ''] ?? 'Sin clasificar') ?></td>
                    <td><?= $i['prioridad'] ? $this->insignia($i['prioridad']) : '—' ?></td>
                    <td><?= $this->insignia($i['estado']) ?></td>
                    <td class="numero"><?= (int) $i['propuestas_pendientes'] > 0 ? '<span class="contador">' . (int) $i['propuestas_pendientes'] . '</span>' : '—' ?></td>
                    <td><?= $this->fecha($i['creada_en']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
