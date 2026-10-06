<?php
/**
 * Buzón de correos de prueba del back-office.
 *
 * @var \KitsuneNotes\Core\View $this
 * @var list<array<string, mixed>> $mails
 * @var array<string, string>      $filters
 * @var array<string, string>      $templates
 * @var int                        $total
 * @var int                        $unread
 * @var array<string, mixed>       $mailer   estado del envío (Mailer::status())
 * @var array<string, int>         $delivery correos por estado de entrega
 */
$smtp         = $mailer['transport'] === 'smtp';
$sentCount    = (int) ($delivery['enviado'] ?? 0);
$failedCount  = (int) ($delivery['fallido'] ?? 0);
// La columna «Entrega» solo aporta información cuando el envío real está o ha estado en uso.
$showDelivery = $smtp || $sentCount + $failedCount > 0;
?>
<div class="admin__cabecera">
    <div>
        <h1>Buzón de pruebas</h1>
        <p style="margin:0; color:var(--frambuesa-suave)">
            <?= count($mails) ?> de <?= (int) $total ?> correos · <?= (int) $unread ?> sin leer
        </p>
    </div>
</div>

<?php if ($smtp): ?>
    <div class="alerta <?= $mailer['problems'] === [] ? 'alerta--exito' : 'alerta--aviso' ?>">
        <span class="alerta__icono" aria-hidden="true"><span><?= $mailer['problems'] === [] ? '✓' : '!' ?></span></span>
        <div>
            <strong>Envío real por SMTP activado</strong>
            (<?= $this->e($mailer['host'] !== '' ? $mailer['host'] : 'sin servidor') ?>:<?= (int) $mailer['port'] ?>,
            cifrado <?= $this->e($mailer['encryption']) ?>).
            Los correos dirigidos a las direcciones autorizadas
            (<?= $mailer['allowed'] === [] ? 'ninguna' : $this->e(implode(', ', $mailer['allowed'])) ?>)
            se envían de verdad y, además, quedan guardados aquí; el resto solo se guarda en este buzón.
            <?php foreach ($mailer['problems'] as $problem): ?>
                <br><strong>Atención:</strong> <?= $this->e($problem) ?>
            <?php endforeach; ?>
            <br>Hasta ahora: <?= $sentCount ?> <?= $sentCount === 1 ? 'enviado' : 'enviados' ?> por SMTP ·
            <?= $failedCount ?> con fallo de envío.
        </div>
    </div>
<?php else: ?>
    <div class="alerta alerta--info">
        <span class="alerta__icono" aria-hidden="true"><span>i</span></span>
        <div>
            <strong>Aquí llegan los correos que la tienda «envía» a los clientes.</strong>
            Con la configuración por defecto ningún mensaje sale de la aplicación (las direcciones
            <code>.test</code> no pueden recibir correo real): cada uno se guarda tal cual lo vería el
            cliente. Abre uno para ver la factura incluida, o descárgalo como fichero <code>.eml</code>.
            El envío real por SMTP es opcional y se activa en el fichero <code>.env</code>
            (README, apartado «Envío real de correos»).
        </div>
    </div>
<?php endif; ?>

<form class="filtros-linea" method="get" action="<?= $this->url('/admin/correos') ?>">
    <label class="solo-lectores" for="plantilla">Filtrar por tipo de correo</label>
    <select name="plantilla" id="plantilla">
        <option value="">Todos los tipos</option>
        <?php foreach ($templates as $key => $label): ?>
            <option value="<?= $this->e($key) ?>" <?= ($filters['plantilla'] ?? '') === $key ? 'selected' : '' ?>>
                <?= $this->e($label) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label class="solo-lectores" for="q">Buscar</label>
    <input type="search" name="q" id="q" placeholder="Destinatario, asunto o pedido"
           value="<?= $this->e($filters['q'] ?? '') ?>">

    <button class="btn btn--primario btn--pequeno" type="submit">Filtrar</button>
    <?php if (array_filter($filters)): ?>
        <a class="btn btn--secundario btn--pequeno" href="<?= $this->url('/admin/correos') ?>">Limpiar</a>
    <?php endif; ?>
</form>

<div class="panel panel--correos">
    <div class="panel__cuerpo panel__cuerpo--sin-relleno">
        <div class="tabla-envoltorio">
            <table class="tabla tabla--correos">
                <caption class="solo-lectores">Correos del buzón de pruebas</caption>
                <thead>
                <tr>
                    <th scope="col"><span class="solo-lectores">Estado</span></th>
                    <th scope="col" class="col-extra">Recibido</th>
                    <th scope="col" class="col-extra">Para</th>
                    <th scope="col">Asunto</th>
                    <th scope="col" class="col-extra"><?= $showDelivery ? 'Tipo y entrega' : 'Tipo' ?></th>
                    <th scope="col" class="col-extra">Pedido</th>
                    <th scope="col"><span class="solo-lectores">Acciones</span></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($mails as $mail): ?>
                    <?php $isNew = $mail['read_at'] === null; ?>
                    <tr class="<?= $isNew ? 'fila-correo--nuevo' : '' ?>">
                        <td>
                            <?php if ($isNew): ?>
                                <span class="punto-nuevo" aria-hidden="true"></span>
                                <span class="solo-lectores">Sin leer</span>
                            <?php else: ?>
                                <span class="solo-lectores">Leído</span>
                            <?php endif; ?>
                        </td>
                        <td class="col-extra"><?= $this->date($mail['created_at']) ?></td>
                        <td class="col-extra">
                            <span class="rompible"><?= $this->e($mail['to_name']) ?></span><br>
                            <span class="rompible" style="font-size:.8rem; color:var(--frambuesa-tenue)"><?= $this->e($mail['to_email']) ?></span>
                        </td>
                        <td>
                            <a class="rompible" href="<?= $this->url('/admin/correos/' . (int) $mail['id']) ?>"><?= $this->e($mail['subject']) ?></a>
                            <span class="resumen-compacto" style="font-size:.8rem; color:var(--frambuesa-tenue)">
                                Para <?= $this->e($mail['to_name']) ?> · <?= $this->date($mail['created_at']) ?>
                                <?php if ($showDelivery): ?>
                                    · <?= $this->e($this->deliveryBadge((string) $mail['delivery_status'])['label']) ?>
                                <?php endif; ?>
                            </span>
                        </td>
                        <td class="col-extra">
                            <div style="display:grid; gap:.3rem; justify-items:start">
                                <span class="insignia insignia--info">
                                    <?= $this->e($templates[$mail['template']] ?? $mail['template']) ?>
                                </span>
                                <?php if ($showDelivery): ?>
                                    <?php $deliveryBadge = $this->deliveryBadge((string) $mail['delivery_status']); ?>
                                    <span class="insignia insignia--<?= $this->e($deliveryBadge['tone']) ?>">
                                        <?= $this->e($deliveryBadge['label']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="col-extra" style="white-space:nowrap">
                            <?php if (!empty($mail['order_reference'])): ?>
                                <a href="<?= $this->url('/admin/pedidos/' . $mail['order_reference']) ?>">
                                    <?= $this->e($mail['order_reference']) ?>
                                </a>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td>
                            <a class="btn btn--secundario btn--pequeno"
                               href="<?= $this->url('/admin/correos/' . (int) $mail['id']) ?>">Abrir</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($mails === []): ?>
                    <tr>
                        <td colspan="7" style="text-align:center; color:var(--frambuesa-suave); padding:2rem">
                            No hay correos que coincidan con el filtro.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
