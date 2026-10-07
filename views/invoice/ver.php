<?php
/**
 * Factura en pantalla. La usan el cliente (desde su pedido o desde el enlace
 * del correo) y el back-office; solo cambia el marco y el enlace de vuelta.
 *
 * El marco (enlace de vuelta, botón y nota) sale en el idioma de quien mira la
 * página; el documento, siempre en el idioma en que se expidió la factura.
 *
 * @var \KitsuneNotes\Core\View $this
 * @var array<string, mixed>     $invoice
 * @var array<string, mixed>     $doc
 * @var string                   $backUrl
 * @var string                   $backLabel el controlador lo pasa ya en el idioma de la página
 */
// Las facturas anteriores a la versión en inglés no guardan el idioma: están en español.
$docLocale = (string) ($doc['locale'] ?? 'es');
// Si el documento no está en el idioma de la página se marca con «lang», para que un
// lector de pantalla lo lea con la voz adecuada. Se calcula aquí porque dentro de
// inLocale() el idioma activo ya es el del documento.
$docLang = $docLocale !== $this->locale() ? $this->app()->translator()->info('html', $docLocale) : '';
?>
<div class="factura-acciones">
    <div>
        <p class="factura-acciones__volver">
            <a href="<?= $this->url($backUrl) ?>">← <?= $this->e($backLabel) ?></a>
        </p>
        <h1><?= $this->t('Factura {numero}', ['numero' => $invoice['number']]) ?></h1>
    </div>
    <button class="btn btn--primario" type="button" data-imprimir><?= $this->t('Imprimir o guardar como PDF') ?></button>
</div>

<?= $this->inLocale($docLocale, fn (): string => $this->partial('partials/factura-documento', ['doc' => $doc, 'docLang' => $docLang])) ?>

<p class="factura-nota">
    <?= $this->th('Para obtener el PDF, pulsa «Imprimir o guardar como PDF» y elige <em>Guardar como PDF</em> como impresora. La página está preparada para salir en una hoja A4 sin menús ni botones.') ?>
</p>
