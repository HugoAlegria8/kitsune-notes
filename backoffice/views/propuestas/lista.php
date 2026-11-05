<?php
/**
 * @var \KitsuneGestion\Core\View $this
 * @var list<array<string, mixed>> $propuestas
 * @var string|null $estado
 * @var array<string, int> $conteo
 */
use KitsuneGestion\Service\Propuestas;
?>
<div class="titular">
    <h1>Propuestas del agente de IA</h1>
    <p class="suave">El agente analiza cada incidencia con sus herramientas y deja aquí lo que haría. Nada llega al cliente hasta que una persona lo aprueba.</p>
</div>

<nav class="filtros" aria-label="Filtrar por estado">
    <?php foreach (Propuestas::ESTADOS as $opcion): ?>
        <a href="<?= $this->url('/propuestas?estado=' . $opcion) ?>"<?= $estado === $opcion ? ' aria-current="true"' : '' ?>>
            <?= $this->e($this->etiqueta($opcion)) ?> (<?= (int) ($conteo[$opcion] ?? 0) ?>)
        </a>
    <?php endforeach; ?>
    <a href="<?= $this->url('/propuestas?estado=todas') ?>"<?= $estado === null ? ' aria-current="true"' : '' ?>>Todas</a>
</nav>

<?php if ($propuestas === []): ?>
    <p class="vacio">No hay propuestas en este estado.</p>
<?php endif; ?>

<?php foreach ($propuestas as $propuesta): ?>
    <?= $this->partial('partials/propuesta', ['p' => $propuesta, 'volver' => '/propuestas']) ?>
<?php endforeach; ?>
