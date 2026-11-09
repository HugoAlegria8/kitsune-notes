<?php
/**
 * Tabla con los datos de un gráfico (accesibilidad y comprobación).
 *
 * @var \KitsuneGestion\Core\View $this
 * @var array<string, int|float> $datos
 * @var string $columna
 */
?>
<details class="datos">
    <summary>Ver los datos</summary>
    <table class="tabla tabla--compacta">
        <thead><tr><th>Etiqueta</th><th><?= $this->e($columna) ?></th></tr></thead>
        <tbody>
        <?php foreach ($datos as $etiqueta => $valor): ?>
            <tr><td><?= $this->e($etiqueta) ?></td><td><?= $this->e($valor) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</details>
