<?php
/**
 * Indicador de progreso del proceso de compra.
 *
 * @var \KitsuneNotes\Core\View $this
 * @var int $step 1 = carrito, 2 = datos, 3 = pago, 4 = confirmación
 */
// t() devuelve el texto ya traducido y escapado: más abajo se imprime tal cual.
$steps = [
    1 => $this->t('Carrito'),
    2 => $this->t('Datos de envío'),
    3 => $this->t('Pago simulado'),
    4 => $this->t('Confirmación'),
];
?>
<ol class="pasos" aria-label="<?= $this->t('Progreso de la compra') ?>">
    <?php foreach ($steps as $number => $label): ?>
        <li <?= $number === $step ? 'aria-current="step"' : '' ?>
            class="<?= $number < $step ? 'completado' : '' ?>">
            <span class="pasos__numero" aria-hidden="true"><?= $number < $step ? '✓' : $number ?></span>
            <span><?= $label ?></span>
        </li>
    <?php endforeach; ?>
</ol>
