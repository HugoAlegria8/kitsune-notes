<?php
/**
 * Consulta de eventos de negocio registrados.
 *
 * @var \KitsuneNotes\Core\View $this
 * @var list<array<string, mixed>> $events
 * @var array<string, string>      $filters
 * @var list<string>               $names
 * @var array<string, string>      $catalog
 * @var array<string, int>         $counts
 * @var string                     $apiToken
 * @var string                     $logPath
 */
?>
<div class="admin__cabecera">
    <div>
        <h1>Eventos registrados</h1>
        <p style="margin:0; color:var(--frambuesa-suave)">
            <?= count($events) ?> eventos mostrados · almacenados en base de datos y en
            <code><?= $this->e($logPath) ?></code>
        </p>
    </div>
    <div style="display:flex; gap:.5rem; flex-wrap:wrap">
        <a class="btn btn--secundario btn--pequeno"
           href="<?= $this->url('/api/eventos') ?>?formato=json&token=<?= urlencode($apiToken) ?>"
           target="_blank" rel="noopener">Exportar JSON ↗</a>
        <a class="btn btn--secundario btn--pequeno"
           href="<?= $this->url('/api/eventos') ?>?formato=csv&token=<?= urlencode($apiToken) ?>">Descargar CSV</a>
    </div>
</div>

<div class="metricas">
    <?php foreach ($counts as $name => $total): ?>
        <div class="metrica">
            <p class="metrica__etiqueta"><?= $this->e($name) ?></p>
            <p class="metrica__valor"><?= (int) $total ?></p>
            <p class="metrica__nota"><?= $this->e($catalog[$name] ?? 'Evento de negocio') ?></p>
        </div>
    <?php endforeach; ?>
    <?php if ($counts === []): ?>
        <div class="metrica">
            <p class="metrica__etiqueta">Sin eventos</p>
            <p class="metrica__valor">0</p>
            <p class="metrica__nota">Navega por la tienda para generar actividad.</p>
        </div>
    <?php endif; ?>
</div>

<form class="filtros-linea" method="get" action="<?= $this->url('/admin/eventos') ?>">
    <label class="solo-lectores" for="nombre">Tipo de evento</label>
    <select name="nombre" id="nombre">
        <option value="">Todos los eventos</option>
        <?php foreach ($names as $name): ?>
            <option value="<?= $this->e($name) ?>" <?= ($filters['nombre'] ?? '') === $name ? 'selected' : '' ?>>
                <?= $this->e($name) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label class="solo-lectores" for="sesion">Identificador de sesión</label>
    <input type="text" name="sesion" id="sesion" placeholder="Sesión concreta"
           value="<?= $this->e($filters['sesion'] ?? '') ?>">

    <button class="btn btn--primario btn--pequeno" type="submit">Filtrar</button>
    <?php if (array_filter($filters)): ?>
        <a class="btn btn--secundario btn--pequeno" href="<?= $this->url('/admin/eventos') ?>">Limpiar</a>
    <?php endif; ?>
</form>

<div class="panel">
    <div class="panel__cuerpo panel__cuerpo--sin-relleno">
        <div class="tabla-envoltorio">
            <table class="tabla">
                <caption class="solo-lectores">Eventos de negocio registrados</caption>
                <thead>
                <tr>
                    <th scope="col">Evento</th>
                    <th scope="col">Momento</th>
                    <th scope="col">Referencia</th>
                    <th scope="col">Sesión</th>
                    <th scope="col">Carga útil</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($events as $event): ?>
                    <?php
                    $payload = json_decode((string) $event['payload_json'], true);
                    $pretty  = json_encode(
                        is_array($payload) ? $payload : [],
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    );
                    ?>
                    <tr>
                        <td><span class="evento-nombre"><?= $this->e($event['event_name']) ?></span></td>
                        <td style="white-space:nowrap"><?= $this->date($event['occurred_at']) ?></td>
                        <td>
                            <?php if (!empty($event['order_reference'])): ?>
                                <a href="<?= $this->url('/admin/pedidos/' . $event['order_reference']) ?>">
                                    <?= $this->e($event['order_reference']) ?>
                                </a>
                            <?php elseif (!empty($event['product_sku'])): ?>
                                <?= $this->e($event['product_sku']) ?>
                            <?php elseif (is_array($payload) && !empty($payload['sku'])): ?>
                                <?= $this->e($payload['sku']) ?>
                                <?php if (str_starts_with((string) $event['event_name'], 'product.') && empty($event['product_name'])): ?>
                                    <br><span style="font-size:.76rem; color:var(--frambuesa-tenue)">producto eliminado</span>
                                <?php endif; ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td>
                            <code style="font-size:.76rem"><?= $this->e(substr((string) $event['session_id'], 0, 10)) ?>…</code>
                        </td>
                        <td>
                            <details class="evento">
                                <summary>Ver JSON</summary>
                                <pre class="json"><?= $this->e($pretty) ?></pre>
                                <p style="font-size:.76rem; color:var(--frambuesa-tenue); margin:.5rem 0 0">
                                    event_id: <?= $this->e($event['event_id']) ?> ·
                                    esquema <?= $this->e($event['schema_version']) ?> ·
                                    origen <?= $this->e($event['source']) ?>
                                </p>
                            </details>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($events === []): ?>
                    <tr>
                        <td colspan="5" style="text-align:center; color:var(--frambuesa-suave); padding:2rem">
                            No hay eventos que coincidan con el filtro.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="panel">
    <div class="panel__cabecera"><h2>Cómo consumir estos eventos (Tarea 2)</h2></div>
    <div class="panel__cuerpo">
        <p>Los eventos están disponibles por tres vías, todas con el mismo esquema:</p>
        <ol style="padding-left:1.2rem; color:var(--frambuesa-suave)">
            <li><strong>Base de datos</strong>: tabla <code>events</code>, de solo inserción.</li>
            <li><strong>Fichero JSON Lines</strong>: <code><?= $this->e($logPath) ?></code>, un evento por línea.</li>
            <li><strong>API interno</strong>: <code>GET /api/eventos?formato=json|csv</code> con la cabecera
                <code>X-API-Token</code>.</li>
        </ol>
        <pre class="json">curl -H "X-API-Token: <?= $this->e($apiToken) ?>" \
     "<?= $this->e($this->url('/api/eventos')) ?>?nombre=order.created&formato=json"</pre>
    </div>
</div>
