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
        <h1 style="margin:.2rem 0"><?= $this->t('¡Recibido! Gom se pone con ello') ?></h1>
        <p class="confirmacion__referencia"><?= $this->e($reference) ?></p>
        <p style="margin:0; color:var(--frambuesa-suave); font-weight:700">
            <?= $this->t('Guarda esta referencia para el seguimiento.') ?>
        </p>
    </div>

    <div class="tarjeta">
        <h2 style="font-size:1.05rem"><?= $this->t('Qué ha ocurrido por dentro') ?></h2>
        <ul style="padding-left:1.15rem; color:var(--frambuesa-suave)">
            <li><?= $this->th('Se ha creado un registro en la tabla <code>support_tickets</code> con estado «abierta».') ?></li>
            <li>
                <?= $this->th('Se ha emitido el evento <code>{evento}</code>, almacenado en base de datos y en el fichero de eventos del día.', ['evento' => $isIncident ? 'incident.created' : 'support.requested']) ?>
            </li>
            <?php if (!empty($mailSent)): ?>
                <li>
                    <?php if (($mailStatus ?? '') === 'enviado'): ?>
                        <?= $this->th('Se ha enviado un acuse de recibo a <strong>{correo}</strong> (si no lo ves, revisa la carpeta de spam).', ['correo' => $email]) ?>
                        <?= $this->th('Además queda guardado en el <a href="{url}">buzón de pruebas</a> del back-office.', ['url' => $mailboxUrl]) ?>
                    <?php elseif (($mailStatus ?? '') === 'fallido'): ?>
                        <?= $this->th('No se ha podido enviar el acuse de recibo a <strong>{correo}</strong>; queda guardado en el <a href="{url}">buzón de pruebas</a> del back-office, donde se ve el motivo del fallo.', ['correo' => $email, 'url' => $mailboxUrl]) ?>
                    <?php elseif (!empty($smtpOn)): ?>
                        <?= $this->th('El acuse de recibo para <strong>{correo}</strong> no se ha enviado por correo real porque esa dirección no está autorizada: queda en el <a href="{url}">buzón de pruebas</a> del back-office.', ['correo' => $email, 'url' => $mailboxUrl]) ?>
                    <?php else: ?>
                        <?= $this->th('Se ha generado un acuse de recibo para <strong>{correo}</strong>.', ['correo' => $email]) ?>
                        <?= $this->th('En el prototipo no sale de la aplicación: queda en el <a href="{url}">buzón de pruebas</a> del back-office.', ['url' => $mailboxUrl]) ?>
                    <?php endif; ?>
                </li>
            <?php endif; ?>
            <?php if ($isIncident && $order !== null): ?>
                <li>
                    <?= $this->th('El pedido <strong>{referencia}</strong> ha pasado al estado «con incidencia» para que el equipo lo revise.', ['referencia' => $order['reference']]) ?>
                </li>
            <?php endif; ?>
        </ul>

        <p style="margin-top:1.2rem; display:flex; gap:.6rem; flex-wrap:wrap">
            <a class="btn btn--primario" href="<?= $this->url('/catalogo') ?>"><?= $this->t('Volver al catálogo') ?></a>
            <a class="btn btn--secundario" href="<?= $this->url('/pedidos') ?>"><?= $this->t('Consultar un pedido') ?></a>
        </p>
    </div>
</div>
