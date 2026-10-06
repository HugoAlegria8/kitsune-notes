<?php
/**
 * Un correo del buzón de pruebas: cabeceras, vista HTML tal y como la vería
 * el cliente y versión en texto plano.
 *
 * @var \KitsuneNotes\Core\View $this
 * @var array<string, mixed>     $mail
 * @var array<string, string>    $templates
 * @var array<string, mixed>|null $invoice
 */
$label    = $templates[$mail['template']] ?? $mail['template'];
$delivery = $this->deliveryBadge((string) $mail['delivery_status']);
?>
<div class="admin__cabecera">
    <div>
        <p style="margin:0 0 .2rem; font-size:.85rem">
            <a href="<?= $this->url('/admin/correos') ?>">← Volver al buzón de pruebas</a>
        </p>
        <h1 class="correo__titulo"><?= $this->e($mail['subject']) ?></h1>
    </div>
    <div style="display:flex; gap:.5rem; flex-wrap:wrap">
        <?php if (!empty($mail['order_reference'])): ?>
            <a class="btn btn--secundario btn--pequeno"
               href="<?= $this->url('/admin/pedidos/' . $mail['order_reference']) ?>">Ver pedido</a>
            <?php if ($invoice !== null): ?>
                <a class="btn btn--secundario btn--pequeno"
                   href="<?= $this->url('/admin/pedidos/' . $mail['order_reference'] . '/factura') ?>">
                    Ver factura <?= $this->e($invoice['number']) ?>
                </a>
            <?php endif; ?>
        <?php endif; ?>
        <a class="btn btn--lila btn--pequeno"
           href="<?= $this->url('/admin/correos/' . (int) $mail['id'] . '/descargar') ?>">Descargar .eml</a>
    </div>
</div>

<div class="panel">
    <div class="panel__cuerpo">
        <dl class="correo__meta">
            <dt>De</dt>
            <dd><?= $this->e($mail['from_name']) ?> &lt;<?= $this->e($mail['from_email']) ?>&gt;</dd>
            <dt>Para</dt>
            <dd><?= $this->e($mail['to_name']) ?> &lt;<?= $this->e($mail['to_email']) ?>&gt;</dd>
            <dt>Fecha</dt>
            <dd><?= $this->date($mail['created_at']) ?></dd>
            <dt>Tipo</dt>
            <dd><span class="insignia insignia--info"><?= $this->e($label) ?></span></dd>
            <dt>Entrega</dt>
            <dd>
                <span class="insignia insignia--<?= $this->e($delivery['tone']) ?>"><?= $this->e($delivery['label']) ?></span>
                <?php if ($mail['delivery_status'] === 'solo_buzon' && $mail['delivery_detail'] === ''): ?>
                    <span class="rompible" style="font-size:.88rem; color:var(--frambuesa-suave)">
                        Solo en el buzón de pruebas: no ha salido de la aplicación.
                    </span>
                <?php else: ?>
                    <span class="rompible" style="display:block; margin-top:.3rem; font-size:.88rem; color:var(--frambuesa-suave)">
                        <?= $this->e($mail['delivery_detail']) ?>
                        <?php if (!empty($mail['delivered_at'])): ?>
                            (<?= $this->date($mail['delivered_at']) ?>)
                        <?php endif; ?>
                    </span>
                <?php endif; ?>
            </dd>
            <dt>Message-ID</dt>
            <dd><code><?= $this->e($mail['message_id']) ?></code></dd>
        </dl>
    </div>
</div>

<div class="panel">
    <div class="panel__cabecera">
        <h2>Así lo ve el cliente</h2>
        <span class="insignia insignia--muted">Versión HTML</span>
    </div>
    <div class="panel__cuerpo panel__cuerpo--sin-relleno">
        <iframe class="correo__marco"
                title="Vista HTML del correo «<?= $this->e($mail['subject']) ?>»"
                sandbox="allow-popups allow-popups-to-escape-sandbox"
                srcdoc="<?= $this->e($mail['body_html']) ?>"></iframe>
    </div>
</div>

<div class="panel">
    <div class="panel__cabecera"><h2>Versión en texto plano</h2></div>
    <div class="panel__cuerpo">
        <details class="evento">
            <summary>Mostrar el texto plano</summary>
            <pre class="json correo__texto"><?= $this->e($mail['body_text']) ?></pre>
        </details>
        <p style="font-size:.8rem; color:var(--frambuesa-tenue); margin:.8rem 0 0">
            Cada correo se guarda con las dos versiones (HTML y texto), como hacen los envíos reales,
            para que los clientes de correo que no muestran HTML sigan viendo el mensaje completo.
        </p>
    </div>
</div>
