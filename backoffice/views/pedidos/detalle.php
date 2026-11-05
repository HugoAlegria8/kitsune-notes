<?php
/**
 * @var \KitsuneGestion\Core\View $this
 * @var array<string, mixed> $pedido
 * @var list<array<string, mixed>> $lineas
 * @var list<array<string, mixed>> $historial
 * @var list<array<string, mixed>> $pagos
 * @var list<array<string, mixed>> $sincronizaciones
 * @var list<array<string, mixed>> $incidencias
 * @var list<string> $transiciones
 */
$moneda = (string) $pedido['moneda'];
?>
<p class="miga"><a href="<?= $this->url('/pedidos') ?>">← Pedidos</a></p>
<div class="titular">
    <h1>Pedido <?= $this->e($pedido['referencia']) ?> <?= $this->insignia($pedido['estado']) ?></h1>
    <p class="suave">Creado en la tienda el <?= $this->fecha($pedido['creado_en_tienda']) ?> · recibido aquí el <?= $this->fecha($pedido['recibido_en']) ?></p>
</div>

<div class="rejilla-dos">
    <section class="tarjeta">
        <h2>Datos</h2>
        <dl class="datos-lista">
            <div><dt>Cliente</dt><dd><?= $this->e($pedido['cliente_nombre'] ?? '—') ?></dd></div>
            <div><dt>Correo</dt><dd><?= $this->e($pedido['cliente_email'] ?? '—') ?></dd></div>
            <div><dt>Telegram</dt><dd><?= !empty($pedido['telegram_chat_id']) ? 'Vinculado (recibe avisos)' : 'Sin vincular' ?></dd></div>
            <div><dt>Pago</dt><dd><?= $this->insignia($pedido['estado_pago']) ?></dd></div>
            <div><dt>Envío</dt><dd><?= $this->e($pedido['metodo_envio'] ?? '—') ?> · <?= $this->e($pedido['provincia_envio'] ?? '') ?></dd></div>
            <?php if ($pedido['seguimiento']): ?>
                <div><dt>Seguimiento</dt><dd><?= $this->e($pedido['transportista']) ?> · <?= $this->e($pedido['seguimiento']) ?></dd></div>
            <?php endif; ?>
            <div><dt>Cupón</dt><dd><?= $this->e($pedido['cupon'] ?: '—') ?></dd></div>
            <div><dt>Idioma y moneda</dt><dd><?= $this->e($pedido['idioma']) ?> · <?= $this->e($moneda) ?></dd></div>
            <div><dt>Total</dt><dd><strong><?= $this->money((int) $pedido['total_cents'], $moneda) ?></strong>
                <?php if ($moneda !== 'EUR'): ?><span class="suave">(<?= $this->money((int) $pedido['total_eur_cents']) ?>)</span><?php endif; ?></dd></div>
            <div><dt>Evento de origen</dt><dd><code><?= $this->e($pedido['evento_origen'] ?? '—') ?></code></dd></div>
        </dl>
    </section>

    <section class="tarjeta">
        <h2>Cambiar el estado</h2>
        <?php if ($transiciones === []): ?>
            <p class="suave">El pedido está en un estado final.</p>
        <?php else: ?>
            <form method="post" action="<?= $this->url('/pedidos/' . $pedido['referencia'] . '/estado') ?>" class="formulario">
                <?= $this->csrf() ?>
                <label for="estado">Nuevo estado</label>
                <select id="estado" name="estado" required>
                    <?php foreach ($transiciones as $opcion): ?>
                        <option value="<?= $this->e($opcion) ?>"><?= $this->e($this->etiqueta($opcion)) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (in_array('enviado', $transiciones, true)): ?>
                    <div class="campos-fila">
                        <div>
                            <label for="transportista">Transportista (si es «Enviado»)</label>
                            <input id="transportista" name="transportista" maxlength="60" placeholder="Correos Express (simulado)">
                        </div>
                        <div>
                            <label for="seguimiento">N.º de seguimiento</label>
                            <input id="seguimiento" name="seguimiento" maxlength="60" placeholder="KN-SEG-0001">
                        </div>
                    </div>
                <?php endif; ?>
                <label for="nota">Nota interna (opcional)</label>
                <input id="nota" name="nota" maxlength="300">
                <button class="boton" type="submit">Guardar y sincronizar con la tienda</button>
                <p class="nota">El cambio se guarda aquí y n8n lo aplica en la tienda. Si la tienda no responde, queda pendiente y se reintenta.</p>
            </form>
        <?php endif; ?>
    </section>
</div>

<section class="tarjeta">
    <h2>Líneas</h2>
    <table class="tabla">
        <thead><tr><th>SKU</th><th>Producto</th><th class="numero">Uds.</th><th class="numero">Precio</th><th class="numero">Importe</th></tr></thead>
        <tbody>
        <?php foreach ($lineas as $l): ?>
            <tr>
                <td><code><?= $this->e($l['sku']) ?></code></td>
                <td><?= $this->e($l['nombre']) ?></td>
                <td class="numero"><?= (int) $l['cantidad'] ?></td>
                <td class="numero"><?= $this->money((int) $l['precio_cents'], $moneda) ?></td>
                <td class="numero"><?= $this->money((int) $l['precio_cents'] * (int) $l['cantidad'], $moneda) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>

<section class="tarjeta" id="sincronizacion">
    <h2>Sincronización con la tienda</h2>
    <?php if ($sincronizaciones === []): ?>
        <p class="suave">Ningún cambio hecho aquí todavía.</p>
    <?php else: ?>
        <table class="tabla">
            <thead><tr><th>#</th><th>Cambio</th><th>Estado</th><th>Intentos</th><th>Último error</th><th>Actualizada</th></tr></thead>
            <tbody>
            <?php foreach ($sincronizaciones as $s): $carga = json_decode((string) $s['carga'], true) ?: []; ?>
                <tr>
                    <td><?= (int) $s['id'] ?></td>
                    <td><?= $this->e(($carga['estado_anterior'] ?? '') . ' → ' . ($carga['estado_nuevo'] ?? '')) ?></td>
                    <td><?= $this->insignia($s['estado']) ?></td>
                    <td class="numero"><?= (int) $s['intentos'] ?></td>
                    <td class="suave"><?= $this->e($s['ultimo_error'] ?? '') ?></td>
                    <td><?= $this->fecha($s['actualizada_en']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<div class="rejilla-dos">
    <section class="tarjeta">
        <h2>Historial</h2>
        <ol class="linea-tiempo">
            <?php foreach ($historial as $h): ?>
                <li>
                    <strong><?= $this->e($this->etiqueta($h['estado_nuevo'])) ?></strong>
                    <span class="suave"><?= $this->fecha($h['creado_en']) ?> · <?= $this->e($h['origen']) ?> · <?= $this->e($h['actor']) ?></span>
                    <?php if ($h['nota']): ?><br><span><?= $this->e($h['nota']) ?></span><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    </section>

    <section class="tarjeta">
        <h2>Pagos simulados</h2>
        <?php if ($pagos === []): ?>
            <p class="suave">Sin intentos de pago registrados.</p>
        <?php else: ?>
            <ul class="lista-simple">
                <?php foreach ($pagos as $pago): ?>
                    <li><?= $this->insignia($pago['resultado']) ?> <?= $this->money((int) $pago['importe_cents'], (string) $pago['moneda']) ?>
                        · <?= $this->fecha($pago['ocurrido_en']) ?> <?= $pago['motivo_rechazo'] ? '· ' . $this->e($pago['motivo_rechazo']) : '' ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <h2>Incidencias</h2>
        <?php if ($incidencias === []): ?>
            <p class="suave">Ninguna.</p>
        <?php else: ?>
            <ul class="lista-simple">
                <?php foreach ($incidencias as $i): ?>
                    <li><a href="<?= $this->url('/incidencias/' . $i['id']) ?>">#<?= (int) $i['id'] ?> <?= $this->e($i['asunto']) ?></a> <?= $this->insignia($i['estado']) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
