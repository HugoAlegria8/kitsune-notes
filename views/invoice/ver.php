<?php
/**
 * Factura en pantalla. La usan el cliente (desde su pedido o desde el enlace
 * del correo) y el back-office; solo cambia el marco y el enlace de vuelta.
 *
 * @var \KitsuneNotes\Core\View $this
 * @var array<string, mixed>     $invoice
 * @var array<string, mixed>     $doc
 * @var string                   $backUrl
 * @var string                   $backLabel
 */
?>
<div class="factura-acciones">
    <div>
        <p class="factura-acciones__volver">
            <a href="<?= $this->url($backUrl) ?>">← <?= $this->e($backLabel) ?></a>
        </p>
        <h1>Factura <?= $this->e($invoice['number']) ?></h1>
    </div>
    <button class="btn btn--primario" type="button" data-imprimir>Imprimir o guardar como PDF</button>
</div>

<?= $this->partial('partials/factura-documento', ['doc' => $doc]) ?>

<p class="factura-nota">
    Para obtener el PDF, pulsa «Imprimir o guardar como PDF» y elige <em>Guardar como PDF</em> como
    impresora. La página está preparada para salir en una hoja A4 sin menús ni botones.
</p>
