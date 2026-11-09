<?php
/**
 * @var \KitsuneGestion\Core\View $this
 * @var array<string, mixed> $incidencia
 * @var list<array<string, mixed>> $mensajes
 * @var list<array<string, mixed>> $propuestas
 */
use KitsuneGestion\Service\Incidencias;

$autores = ['cliente' => 'Cliente', 'equipo' => 'Equipo', 'agente' => 'Agente de IA', 'sistema' => 'Sistema'];
?>
<p class="miga"><a href="<?= $this->url('/incidencias') ?>">← Incidencias</a></p>
<div class="titular">
    <h1>Incidencia #<?= (int) $incidencia['id'] ?> <?= $this->insignia($incidencia['estado']) ?></h1>
    <p class="suave"><?= $this->e($incidencia['asunto']) ?> · <?= $this->e(Incidencias::TIPOS[$incidencia['tipo']] ?? $incidencia['tipo']) ?> · abierta el <?= $this->fecha($incidencia['creada_en']) ?></p>
</div>

<div class="rejilla-dos">
    <section class="tarjeta">
        <h2>Datos</h2>
        <dl class="datos-lista">
            <div><dt>Ticket de la tienda</dt><dd><?= $this->e($incidencia['referencia_ticket'] ?? '—') ?></dd></div>
            <div><dt>Pedido</dt><dd><?= $incidencia['pedido_referencia'] ? '<a href="' . $this->url('/pedidos/' . $incidencia['pedido_referencia']) . '">' . $this->e($incidencia['pedido_referencia']) . '</a> ' . $this->insignia($incidencia['pedido_estado']) : 'Sin pedido' ?></dd></div>
            <div><dt>Cliente</dt><dd><?= $this->e($incidencia['cliente_nombre'] ?? '—') ?></dd></div>
            <div><dt>Telegram</dt><dd><?= !empty($incidencia['telegram_chat_id']) ? 'Vinculado: las respuestas aprobadas le llegan por Telegram' : 'Sin vincular: el envío se simula' ?></dd></div>
            <div><dt>Canal e idioma</dt><dd><?= $this->e($incidencia['canal']) ?> · <?= $this->e($incidencia['idioma']) ?></dd></div>
            <div><dt>Categoría</dt><dd><?= $this->e(Incidencias::CATEGORIAS[$incidencia['categoria'] ?? ''] ?? 'Sin clasificar') ?></dd></div>
            <div><dt>Prioridad</dt><dd><?= $incidencia['prioridad'] ? $this->insignia($incidencia['prioridad']) : '—' ?></dd></div>
            <?php if ($incidencia['resuelta_en']): ?>
                <div><dt>Resuelta</dt><dd><?= $this->fecha($incidencia['resuelta_en']) ?></dd></div>
            <?php endif; ?>
        </dl>
    </section>

    <section class="tarjeta">
        <h2>Gestión</h2>
        <form method="post" action="<?= $this->url('/incidencias/' . $incidencia['id'] . '/estado') ?>" class="formulario formulario--fila">
            <?= $this->csrf() ?>
            <label for="estado">Estado</label>
            <select id="estado" name="estado">
                <?php foreach (Incidencias::ESTADOS as $opcion): ?>
                    <option value="<?= $opcion ?>"<?= $opcion === $incidencia['estado'] ? ' selected' : '' ?>><?= $this->e($this->etiqueta($opcion)) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="boton boton--suave" type="submit">Cambiar</button>
        </form>
        <form method="post" action="<?= $this->url('/incidencias/' . $incidencia['id'] . '/clasificacion') ?>" class="formulario">
            <?= $this->csrf() ?>
            <div class="campos-fila">
                <div>
                    <label for="categoria">Categoría</label>
                    <select id="categoria" name="categoria">
                        <?php foreach (Incidencias::CATEGORIAS as $clave => $texto): ?>
                            <option value="<?= $clave ?>"<?= $clave === $incidencia['categoria'] ? ' selected' : '' ?>><?= $this->e($texto) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="prioridad">Prioridad</label>
                    <select id="prioridad" name="prioridad">
                        <?php foreach (Incidencias::PRIORIDADES as $opcion): ?>
                            <option value="<?= $opcion ?>"<?= $opcion === $incidencia['prioridad'] ? ' selected' : '' ?>><?= $this->e($this->etiqueta($opcion)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <button class="boton boton--suave" type="submit">Guardar clasificación</button>
            <p class="nota">La clasificación del agente es reversible: aquí se corrige.</p>
        </form>
    </section>
</div>

<section class="tarjeta">
    <h2>Conversación y actividad</h2>
    <ol class="conversacion">
        <?php foreach ($mensajes as $m): ?>
            <li class="mensaje mensaje--<?= $this->e($m['autor']) ?>">
                <p class="mensaje__cabecera"><strong><?= $this->e($autores[$m['autor']] ?? $m['autor']) ?></strong>
                    <span class="suave"><?= $this->e($m['canal']) ?> · <?= $this->fecha($m['creado_en']) ?></span></p>
                <p class="mensaje__texto"><?= nl2br($this->e($m['texto'])) ?></p>
            </li>
        <?php endforeach; ?>
    </ol>
</section>

<section id="propuestas">
    <h2>Propuestas del agente de IA</h2>
    <?php if ($propuestas === []): ?>
        <p class="vacio">El agente todavía no ha propuesto nada para esta incidencia.</p>
    <?php endif; ?>
    <?php foreach ($propuestas as $propuesta): ?>
        <?= $this->partial('partials/propuesta', ['p' => $propuesta, 'volver' => '/incidencias/' . $incidencia['id']]) ?>
    <?php endforeach; ?>
</section>
