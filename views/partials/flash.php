<?php
/**
 * Mensajes flash de la petición anterior.
 *
 * @var \KitsuneNotes\Core\View $this
 * @var list<array{type:string, message:string}> $flashMessages
 */
if (empty($flashMessages)) {
    return;
}

$icons = ['exito' => '✓', 'error' => '!', 'aviso' => '!', 'info' => 'i'];
?>
<div role="status" aria-live="polite">
    <?php foreach ($flashMessages as $flash): ?>
        <div class="alerta alerta--<?= $this->e($flash['type']) ?>">
            <span class="alerta__icono" aria-hidden="true"><span><?= $icons[$flash['type']] ?? 'i' ?></span></span>
            <span><?= $this->e($flash['message']) ?></span>
        </div>
    <?php endforeach; ?>
</div>
