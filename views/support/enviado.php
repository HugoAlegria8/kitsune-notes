<?php
/**
 * Confirmación de solicitud de soporte registrada.
 *
 * @var \KitsuneNotes\Core\View $this
 * @var string                   $reference
 * @var bool                     $isIncident
 * @var array<string, mixed>|null $order
 * @var bool                     $mailSent
 * @var string                   $mailStatus resultado de la entrega: solo_buzon, enviado o fallido
 * @var bool                     $smtpOn     true si el envío real por SMTP está activado
 * @var string                   $email
 */
$mailboxUrl = $this->url('/admin/correos') . '?q=' . urlencode((string) $reference);
?>
<div style="max-width:620px; margin-inline:auto">
    <div class="confirmacion">
        <img src="<?= $this->asset('assets/img/mascotas/gom.svg') ?>" alt="" width="130" height="98">
        <h1 style="margin:.2rem 0">¡Recibido! Gom se pone con ello</h1>
        <p class="confirmacion__referencia"><?= $this->e($reference) ?></p>
        <p style="margin:0; color:var(--frambuesa-suave); font-weight:700">
            Guarda esta referencia para el seguimiento.
        </p>
    </div>

    <div class="tarjeta">
        <h2 style="font-size:1.05rem">Qué ha ocurrido por dentro</h2>
        <ul style="padding-left:1.15rem; color:var(--frambuesa-suave)">
            <li>Se ha creado un registro en la tabla <code>support_tickets</code> con estado «abierta».</li>
            <li>
                Se ha emitido el evento
                <code><?= $isIncident ? 'incident.created' : 'support.requested' ?></code>,
                almacenado en base de datos y en el fichero de eventos del día.
            </li>
            <?php if (!empty($mailSent)): ?>
                <li>
                    <?php if (($mailStatus ?? '') === 'enviado'): ?>
                        Se ha enviado un acuse de recibo a <strong><?= $this->e($email) ?></strong>
                        (si no lo ves, revisa la carpeta de spam). Además queda guardado en el
                        <a href="<?= $mailboxUrl ?>">buzón de pruebas</a> del back-office.
                    <?php elseif (($mailStatus ?? '') === 'fallido'): ?>
                        No se ha podido enviar el acuse de recibo a <strong><?= $this->e($email) ?></strong>;
                        queda guardado en el <a href="<?= $mailboxUrl ?>">buzón de pruebas</a> del back-office,
                        donde se ve el motivo del fallo.
                    <?php elseif (!empty($smtpOn)): ?>
                        El acuse de recibo para <strong><?= $this->e($email) ?></strong> no se ha enviado por
                        correo real porque esa dirección no está autorizada: queda en el
                        <a href="<?= $mailboxUrl ?>">buzón de pruebas</a> del back-office.
                    <?php else: ?>
                        Se ha generado un acuse de recibo para <strong><?= $this->e($email) ?></strong>. En el
                        prototipo no sale de la aplicación: queda en el
                        <a href="<?= $mailboxUrl ?>">buzón de pruebas</a> del back-office.
                    <?php endif; ?>
                </li>
            <?php endif; ?>
            <?php if ($isIncident && $order !== null): ?>
                <li>
                    El pedido <strong><?= $this->e($order['reference']) ?></strong> ha pasado al estado
                    «con incidencia» para que el equipo lo revise.
                </li>
            <?php endif; ?>
        </ul>

        <p style="margin-top:1.2rem; display:flex; gap:.6rem; flex-wrap:wrap">
            <a class="btn btn--primario" href="<?= $this->url('/catalogo') ?>">Volver al catálogo</a>
            <a class="btn btn--secundario" href="<?= $this->url('/pedidos') ?>">Consultar un pedido</a>
        </p>
    </div>
</div>
