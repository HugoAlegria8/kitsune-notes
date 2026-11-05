<?php
/**
 * Tarea 2 · bandeja de salida de eventos hacia n8n.
 *
 * @var \KitsuneNotes\Core\View $this
 * @var bool                       $enabled
 * @var list<string>               $subscribed
 * @var array<string, int>         $counts
 * @var list<array<string, mixed>> $rows
 * @var string                     $telegramBot
 */
$tones = ['entregado' => 'exito', 'pendiente' => 'aviso', 'rechazado' => 'error', 'fallido' => 'error'];
?>
<div class="admin__cabecera">
    <div>
        <h1>Integración con n8n</h1>
        <p style="margin:0; color:var(--frambuesa-suave)">
            Eventos enviados al back-office «Kitsune Gestión» a través de n8n (firmados con HMAC-SHA256).
        </p>
    </div>
    <form method="post" action="<?= $this->url('/admin/integracion/reintentar') ?>">
        <?= $this->csrf() ?>
        <button class="btn btn--secundario btn--pequeno" type="submit">Enviar pendientes ahora</button>
    </form>
</div>

<?php if (!$enabled): ?>
    <div class="alerta alerta--aviso" role="status">
        <div>La integración está apagada: faltan <code>KN_WEBHOOK_URL</code> o <code>KN_WEBHOOK_SECRET</code> en el <code>.env</code>. La tienda funciona igual.</div>
    </div>
<?php endif; ?>

<div class="metricas">
    <?php foreach (['entregado' => 'Entregados', 'pendiente' => 'Pendientes de sincronización', 'rechazado' => 'Rechazados por n8n', 'fallido' => 'Fallidos (reintentos agotados)'] as $status => $label): ?>
        <div class="metrica">
            <p class="metrica__etiqueta"><?= $this->e($label) ?></p>
            <p class="metrica__valor"><?= (int) ($counts[$status] ?? 0) ?></p>
        </div>
    <?php endforeach; ?>
</div>

<p style="font-size:.88rem">
    Eventos suscritos: <?php foreach ($subscribed as $name): ?><code><?= $this->e($name) ?></code> <?php endforeach; ?>
    · Bot de Telegram: <?= $telegramBot !== '' ? '<code>@' . $this->e($telegramBot) . '</code>' : 'sin configurar' ?>
</p>

<div class="panel">
    <div class="panel__cuerpo panel__cuerpo--sin-relleno">
        <div class="tabla-envoltorio">
            <table class="tabla">
                <caption class="solo-lectores">Bandeja de salida de eventos</caption>
                <thead>
                <tr>
                    <th scope="col">#</th>
                    <th scope="col">Evento</th>
                    <th scope="col">Estado</th>
                    <th scope="col">Intentos</th>
                    <th scope="col">Respuesta de n8n</th>
                    <th scope="col">Creado</th>
                    <th scope="col"></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= (int) $row['id'] ?></td>
                        <td><span class="evento-nombre"><?= $this->e($row['event_name']) ?></span><br>
                            <span style="font-size:.74rem; color:var(--frambuesa-tenue)"><?= $this->e($row['event_id']) ?></span></td>
                        <td><span class="insignia insignia--<?= $this->e($tones[$row['status']] ?? 'neutro') ?>"><?= $this->e($row['status']) ?></span></td>
                        <td><?= (int) $row['attempts'] ?></td>
                        <td style="font-size:.8rem; max-width:360px; overflow-wrap:anywhere">
                            <?= $row['last_status_code'] ? 'HTTP ' . (int) $row['last_status_code'] . ' · ' : '' ?>
                            <?= $this->e(mb_strimwidth((string) ($row['last_error'] ?? $row['response_body'] ?? ''), 0, 220, '…')) ?>
                        </td>
                        <td style="white-space:nowrap"><?= $this->date((string) $row['created_at']) ?></td>
                        <td>
                            <?php if ($row['status'] !== 'entregado'): ?>
                                <form method="post" action="<?= $this->url('/admin/integracion/reintentar') ?>">
                                    <?= $this->csrf() ?>
                                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                    <button class="btn btn--secundario btn--pequeno" type="submit">Reintentar</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($rows === []): ?>
                    <tr><td colspan="7">Todavía no se ha enviado ningún evento.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
