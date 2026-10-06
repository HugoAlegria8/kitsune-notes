<?php
/**
 * Indicador de progreso del proceso de compra.
 *
 * @var \KitsuneNotes\Core\View $this
 * @var int $step 1 = carrito, 2 = datos, 3 = pago, 4 = confirmación
 */
$steps = [
    1 => 'Carrito',
    2 => 'Datos de envío',
    3 => 'Pago simulado',
    4 => 'Confirmación',
];
?>
<ol class="pasos" aria-label="Progreso de la compra">
    <?php foreach ($steps as $number => $label): ?>
        <li <?= $number === $step ? 'aria-current="step"' : '' ?>
            class="<?= $number < $step ? 'completado' : '' ?>">
            <span class="pasos__numero" aria-hidden="true"><?= $number < $step ? '✓' : $number ?></span>
            <span><?= $this->e($label) ?></span>
        </li>
    <?php endforeach; ?>
</ol>
