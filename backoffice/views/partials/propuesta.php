<?php
/**
 * Tarjeta de una propuesta del agente, con la revisión humana.
 *
 * @var \KitsuneGestion\Core\View $this
 * @var array<string, mixed> $p
 * @var string $volver
 */
use KitsuneGestion\Service\Propuestas;

$datosConsultados = json_decode((string) $p['datos_consultados'], true) ?: [];
$riesgos          = json_decode((string) $p['riesgos'], true) ?: [];
$conMensaje       = !in_array($p['accion_propuesta'], ['escalar_equipo', 'cerrar_sin_accion'], true);
$salida           = json_encode(json_decode((string) $p['salida_agente'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
<article class="tarjeta propuesta propuesta--<?= $this->e($p['estado']) ?>" id="propuesta-<?= (int) $p['id'] ?>">
    <header class="propuesta__cabecera">
        <h3>Propuesta n.º <?= (int) $p['id'] ?> · <?= $this->e(Propuestas::ACCIONES[$p['accion_propuesta']] ?? ($p['accion_propuesta'] ?: 'sin acción')) ?></h3>
        <?= $this->insignia($p['estado']) ?>
    </header>
    <p class="suave">
        Incidencia <a href="<?= $this->url('/incidencias/' . $p['incidencia_id']) ?>">#<?= (int) $p['incidencia_id'] ?></a>
        <?= !empty($p['pedido_referencia']) ? '· pedido ' . $this->e($p['pedido_referencia']) : '' ?>
        <?= !empty($p['cliente_nombre']) ? '· ' . $this->e($p['cliente_nombre']) : '' ?>
        · <?= $this->fecha($p['creada_en']) ?> · <?= $this->e($p['modelo'] ?? '') ?>
        <?= !empty($p['ejecucion_n8n']) ? '· ejecución n8n ' . $this->e($p['ejecucion_n8n']) : '' ?>
    </p>

    <dl class="datos-lista datos-lista--propuesta">
        <div><dt>Tipo de caso</dt><dd><?= $this->e($p['tipo_caso'] ?: '—') ?></dd></div>
        <div><dt>Prioridad</dt><dd><?= $p['prioridad'] ? $this->insignia($p['prioridad']) : '—' ?></dd></div>
        <div><dt>Confianza</dt><dd><?= $p['confianza'] !== null ? (int) round((float) $p['confianza'] * 100) . ' %' : '—' ?></dd></div>
        <div><dt>Justificación</dt><dd><?= $this->e($p['justificacion']) ?></dd></div>
        <div><dt>Datos consultados</dt><dd><?= $datosConsultados === [] ? '—' : $this->e(implode(' · ', array_map('strval', $datosConsultados))) ?></dd></div>
        <div><dt>Riesgos</dt><dd><?= $riesgos === [] ? 'Ninguno señalado' : $this->e(implode(' · ', array_map('strval', $riesgos))) ?></dd></div>
    </dl>

    <?php if ($p['estado'] === 'pendiente_revision'): ?>
        <div class="revision">
            <form method="post" action="<?= $this->url('/propuestas/' . $p['id'] . '/aprobar') ?>" class="formulario">
                <?= $this->csrf() ?>
                <input type="hidden" name="volver" value="<?= $this->e($volver) ?>">
                <?php if ($conMensaje): ?>
                    <label for="mensaje-<?= (int) $p['id'] ?>">Mensaje para el cliente (puedes corregirlo antes de aprobar)</label>
                    <textarea id="mensaje-<?= (int) $p['id'] ?>" name="mensaje_final" rows="4" maxlength="1000" required><?= $this->e($p['mensaje_cliente']) ?></textarea>
                    <p class="nota"><?= !empty($p['telegram_chat_id']) ? 'Se enviará por Telegram al cliente.' : 'El cliente no ha vinculado Telegram: el envío quedará simulado.' ?></p>
                <?php else: ?>
                    <p class="nota">Acción interna: no se envía nada al cliente.</p>
                <?php endif; ?>
                <label for="comentario-<?= (int) $p['id'] ?>">Comentario de la revisión (opcional)</label>
                <input id="comentario-<?= (int) $p['id'] ?>" name="comentario" maxlength="500">
                <button class="boton" type="submit">Aprobar<?= $conMensaje ? ' y enviar' : '' ?></button>
            </form>
            <form method="post" action="<?= $this->url('/propuestas/' . $p['id'] . '/rechazar') ?>" class="formulario formulario--rechazo">
                <?= $this->csrf() ?>
                <input type="hidden" name="volver" value="<?= $this->e($volver) ?>">
                <label for="motivo-<?= (int) $p['id'] ?>">Motivo del rechazo</label>
                <input id="motivo-<?= (int) $p['id'] ?>" name="motivo" maxlength="500" required>
                <button class="boton boton--peligro" type="submit">Rechazar</button>
            </form>
        </div>
    <?php else: ?>
        <?php if ($p['mensaje_cliente'] !== '' && $p['mensaje_final'] === null): ?>
            <blockquote class="cita"><?= nl2br($this->e($p['mensaje_cliente'])) ?></blockquote>
        <?php endif; ?>
        <?php if ($p['mensaje_final']): ?>
            <p><strong>Mensaje aprobado:</strong></p>
            <blockquote class="cita"><?= nl2br($this->e($p['mensaje_final'])) ?></blockquote>
        <?php endif; ?>
        <dl class="datos-lista">
            <?php if ($p['revisado_por']): ?>
                <div><dt>Revisión</dt><dd><?= $this->e($p['revisado_por']) ?> · <?= $this->fecha($p['revisado_en']) ?></dd></div>
            <?php endif; ?>
            <?php if ($p['comentario_revision']): ?>
                <div><dt>Comentario</dt><dd><?= $this->e($p['comentario_revision']) ?></dd></div>
            <?php endif; ?>
            <?php if ($p['resultado']): ?>
                <div><dt>Resultado</dt><dd><?= $this->e($p['resultado']) ?> <?= $p['ejecutada_en'] ? '· ' . $this->fecha($p['ejecutada_en']) : '' ?></dd></div>
            <?php endif; ?>
        </dl>
    <?php endif; ?>

    <details class="datos">
        <summary>Salida estructurada completa del agente (JSON)</summary>
        <pre class="codigo"><?= $this->e($salida) ?></pre>
    </details>
</article>
