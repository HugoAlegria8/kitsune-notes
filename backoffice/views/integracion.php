<?php
/**
 * Trazabilidad de la integración.
 *
 * @var \KitsuneGestion\Core\View $this
 * @var list<array<string, mixed>> $registro
 * @var list<array<string, mixed>> $sincronizaciones
 * @var list<array<string, mixed>> $eventos
 * @var string|null $resultado
 * @var string|null $origen
 * @var bool $configurado
 */
$filtro = static fn (?string $r, ?string $o): string => '/integracion?' . http_build_query(array_filter(['resultado' => $r, 'origen' => $o])) . '#registro';
?>
<div class="titular">
    <h1>Integración y trazabilidad</h1>
    <p class="suave">Todo lo que entra por la API, lo que anotan los flujos de n8n y lo que sale hacia la tienda.</p>
</div>

<?php if (!$configurado): ?>
    <div class="aviso aviso--aviso">Falta configurar en el .env la dirección del webhook de n8n y su secreto (KG_N8N_WEBHOOK_URL y KG_N8N_SECRETO): los cambios quedarán pendientes hasta entonces.</div>
<?php endif; ?>

<section class="tarjeta" id="salida">
    <div class="tarjeta__cabecera">
        <h2>Bandeja de salida hacia n8n</h2>
        <form method="post" action="<?= $this->url('/integracion/reintentar') ?>">
            <?= $this->csrf() ?>
            <button class="boton boton--suave" type="submit">Reintentar pendientes ahora</button>
        </form>
    </div>
    <?php if ($sincronizaciones === []): ?>
        <p class="suave">Ningún cambio enviado todavía.</p>
    <?php else: ?>
        <div class="tabla-envoltorio">
            <table class="tabla">
                <thead><tr><th>#</th><th>Tipo</th><th>Entidad</th><th>Estado</th><th>Intentos</th><th>Último error</th><th>Próximo intento</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($sincronizaciones as $s): ?>
                    <tr>
                        <td><?= (int) $s['id'] ?></td>
                        <td><code><?= $this->e($s['tipo']) ?></code></td>
                        <td><?= $this->e($s['entidad']) ?></td>
                        <td><?= $this->insignia($s['estado']) ?></td>
                        <td class="numero"><?= (int) $s['intentos'] ?></td>
                        <td class="suave"><?= $this->e($s['ultimo_error'] ?? '') ?></td>
                        <td><?= $s['estado'] === 'pendiente' ? $this->fecha($s['siguiente_intento']) : '—' ?></td>
                        <td>
                            <?php if ($s['estado'] !== 'confirmada'): ?>
                                <form method="post" action="<?= $this->url('/integracion/reintentar') ?>">
                                    <?= $this->csrf() ?>
                                    <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                    <button class="boton boton--mini" type="submit">Reintentar</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="tarjeta" id="registro">
    <h2>Registro de integración</h2>
    <nav class="filtros" aria-label="Filtrar el registro">
        <a href="<?= $this->url($filtro(null, $origen)) ?>"<?= $resultado === null ? ' aria-current="true"' : '' ?>>Todos</a>
        <?php foreach (['ok', 'error', 'duplicado', 'ignorado'] as $r): ?>
            <a href="<?= $this->url($filtro($r, $origen)) ?>"<?= $resultado === $r ? ' aria-current="true"' : '' ?>><?= $this->e($this->etiqueta($r)) ?></a>
        <?php endforeach; ?>
        <span class="filtros__separador" aria-hidden="true">|</span>
        <?php foreach (['n8n' => 'Flujos de n8n', 'api' => 'Llamadas a la API', 'backoffice' => 'Salida a n8n'] as $o => $texto): ?>
            <a href="<?= $this->url($filtro($resultado, $origen === $o ? null : $o)) ?>"<?= $origen === $o ? ' aria-current="true"' : '' ?>><?= $this->e($texto) ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="tabla-envoltorio">
        <table class="tabla tabla--compacta">
            <thead><tr><th>Fecha</th><th>Origen</th><th>Flujo / ejecución</th><th>Operación</th><th>Entidad</th><th>Resultado</th><th>HTTP</th><th>Detalle</th><th>ms</th></tr></thead>
            <tbody>
            <?php foreach ($registro as $r): ?>
                <tr>
                    <td><?= $this->fecha($r['creado_en']) ?></td>
                    <td><?= $this->e($r['origen']) ?></td>
                    <td><?= $this->e($r['flujo'] ?? '') ?><?= $r['ejecucion'] ? '<br><span class="suave">' . $this->e($r['ejecucion']) . '</span>' : '' ?></td>
                    <td><code><?= $this->e($r['operacion']) ?></code><?= $r['event_id'] ? '<br><span class="suave">' . $this->e($r['event_id']) . '</span>' : '' ?></td>
                    <td><?= $this->e($r['entidad'] ?? '') ?></td>
                    <td><?= $this->insignia($r['resultado']) ?><?= $r['tipo_error'] ? '<br><span class="suave">' . $this->e($r['tipo_error']) . '</span>' : '' ?></td>
                    <td class="numero"><?= $this->e($r['codigo_http'] ?? '') ?></td>
                    <td class="detalle"><?= $this->e($r['mensaje'] ?? '') ?></td>
                    <td class="numero"><?= $this->e($r['duracion_ms'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="tarjeta" id="eventos">
    <h2>Eventos de la tienda recibidos (últimos 40)</h2>
    <p class="nota">Se guardan una sola vez por <code>event_id</code>: si n8n entrega el mismo evento dos veces, el segundo cuenta como duplicado.</p>
    <div class="tabla-envoltorio">
        <table class="tabla tabla--compacta">
            <thead><tr><th>Ocurrido</th><th>Evento</th><th>Pedido</th><th>Resultado</th><th>event_id</th></tr></thead>
            <tbody>
            <?php foreach ($eventos as $e): ?>
                <tr>
                    <td><?= $this->fecha($e['occurred_at']) ?></td>
                    <td><code><?= $this->e($e['event_name']) ?></code></td>
                    <td><?= $this->e($e['pedido_ref'] ?? '') ?></td>
                    <td><?= $this->e($e['resultado'] ?? '') ?></td>
                    <td class="suave"><?= $this->e($e['event_id']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
