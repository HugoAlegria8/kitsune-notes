<?php
/**
 * Cuadro de mando operativo.
 *
 * @var \KitsuneGestion\Core\View $this
 * @var array<string, mixed> $metricas
 */
use KitsuneGestion\Support\Graficos;

$m   = $metricas;
$pct = static fn (?float $valor): string => $valor === null ? '—' : number_format($valor, 1, ',', '.') . ' %';

$porEstado = [];
foreach ($m['pedidos_estado'] as $estado => $total) {
    $porEstado[$this->etiqueta($estado)] = $total;
}

$top = [];
foreach ($m['productos_top'] as $producto) {
    $top[$producto['nombre']] = $producto['unidades'];
}

$agente = [];
foreach ($m['agente']['por_estado'] as $estado => $total) {
    $agente[$this->etiqueta($estado)] = $total;
}

$integracion = ['Correctos' => $m['integracion']['correctos'], 'Fallidos' => $m['integracion']['fallidos']];
?>
<div class="titular">
    <h1>Cuadro de mando</h1>
    <p class="suave">Calculado ahora mismo a partir de los registros del sistema · <?= $this->fecha($m['generado_en']) ?></p>
</div>

<section class="indicadores" aria-label="Indicadores principales">
    <article class="indicador">
        <h2>Pedidos recibidos</h2>
        <p class="indicador__valor"><?= (int) $m['pedidos']['total'] ?></p>
        <p class="indicador__pie"><?= (int) $m['pedidos']['pagados'] ?> con pago autorizado</p>
    </article>
    <article class="indicador">
        <h2>Importe simulado</h2>
        <p class="indicador__valor"><?= $this->money((int) $m['pedidos']['importe_eur_cents']) ?></p>
        <p class="indicador__pie">Ticket medio <?= $this->money((int) $m['pedidos']['ticket_medio_eur_cents']) ?> (contravalor en euros)</p>
    </article>
    <article class="indicador">
        <h2>Incidencias abiertas</h2>
        <p class="indicador__valor"><?= (int) $m['incidencias']['abiertas'] ?></p>
        <p class="indicador__pie">
            Resolución media:
            <?= $m['incidencias']['horas_medias_resolucion'] === null ? '—' : $this->e(number_format((float) $m['incidencias']['horas_medias_resolucion'], 1, ',', '.') . ' h') ?>
        </p>
    </article>
    <article class="indicador">
        <h2>Éxito de la integración</h2>
        <p class="indicador__valor"><?= $pct($m['integracion']['tasa_exito']) ?></p>
        <p class="indicador__pie"><?= (int) $m['integracion']['correctos'] ?> correctos · <?= (int) $m['integracion']['fallidos'] ?> fallidos</p>
    </article>
    <article class="indicador">
        <h2>Conversión del checkout</h2>
        <p class="indicador__valor"><?= $pct($m['embudo']['conversion_checkout_pct']) ?></p>
        <p class="indicador__pie">De empezar el pago a pago autorizado</p>
    </article>
    <article class="indicador">
        <h2>Propuestas aprobadas</h2>
        <p class="indicador__valor"><?= $pct($m['agente']['tasa_aprobacion']) ?></p>
        <p class="indicador__pie"><?= (int) $m['agente']['aprobadas'] ?> aprobadas · <?= (int) $m['agente']['rechazadas'] ?> rechazadas · <?= (int) $m['agente']['pendientes'] ?> pendientes</p>
    </article>
</section>

<div class="rejilla-graficos">
    <section class="tarjeta tarjeta--ancha">
        <h2>Pedidos recibidos por día (últimos 14 días)</h2>
        <?= Graficos::barras($m['pedidos_por_dia'], 'Pedidos recibidos por día', 'Número de pedidos que han llegado al back-office cada día de los últimos 14.') ?>
        <?= $this->partial('partials/datos', ['datos' => $m['pedidos_por_dia'], 'columna' => 'Pedidos']) ?>
    </section>

    <section class="tarjeta">
        <h2>Pedidos por estado</h2>
        <?= Graficos::horizontal($porEstado, 'Pedidos por estado', 'Número de pedidos en cada estado del proceso.') ?>
    </section>

    <section class="tarjeta">
        <h2>Productos más pedidos (unidades)</h2>
        <?= Graficos::horizontal($top, 'Productos más pedidos', 'Unidades pedidas de los cinco productos con más demanda, sin contar pedidos cancelados.') ?>
    </section>

    <section class="tarjeta">
        <h2>Embudo de compra (eventos de la tienda)</h2>
        <?= Graficos::horizontal($m['embudo']['etapas'], 'Embudo de compra', 'Sesiones que añaden al carrito y empiezan el pago, pedidos creados y pagos autorizados.') ?>
    </section>

    <section class="tarjeta">
        <h2>Eventos de integración</h2>
        <?= Graficos::horizontal($integracion, 'Eventos de integración correctos y fallidos', 'Ejecuciones de los flujos de n8n anotadas como correctas o fallidas.') ?>
        <h3>Errores por tipo</h3>
        <?= Graficos::horizontal($m['integracion']['errores_por_tipo'], 'Errores por tipo', 'Número de errores registrados de cada tipo en la integración.') ?>
    </section>

    <section class="tarjeta">
        <h2>Propuestas del agente de IA</h2>
        <?= Graficos::horizontal($agente, 'Propuestas del agente por estado', 'Propuestas pendientes, aprobadas, rechazadas y ejecutadas.') ?>
        <p class="nota"><?= (int) $m['agente']['editadas_al_aprobar'] ?> se aprobaron corrigiendo el texto · <?= (int) $m['agente']['errores_agente'] ?> errores del agente</p>
    </section>

    <section class="tarjeta">
        <h2>Sincronización con la tienda</h2>
        <?php $sinc = $m['sincronizaciones']; ?>
        <dl class="datos-lista">
            <div><dt>Confirmadas</dt><dd><?= (int) ($sinc['confirmada'] ?? 0) ?></dd></div>
            <div><dt>Pendientes</dt><dd><?= (int) ($sinc['pendiente'] ?? 0) ?></dd></div>
            <div><dt>En conflicto</dt><dd><?= (int) ($sinc['conflicto'] ?? 0) ?></dd></div>
            <div><dt>Fallidas</dt><dd><?= (int) ($sinc['fallida'] ?? 0) ?></dd></div>
            <div><dt>Latencia media de la API</dt><dd><?= $m['integracion']['latencia_api_ms'] === null ? '—' : (int) $m['integracion']['latencia_api_ms'] . ' ms' ?></dd></div>
        </dl>
    </section>
</div>

<section class="tarjeta">
    <h2>De dónde sale cada dato</h2>
    <table class="tabla">
        <thead><tr><th>Indicador</th><th>Origen</th></tr></thead>
        <tbody>
            <tr><td>Pedidos, importe, estados, productos</td><td>Tablas <code>pedidos</code>, <code>pagos</code> y <code>lineas_pedido</code>, que llenan los flujos de n8n con los eventos <code>order.created</code> y <code>payment.simulated</code> de la tienda.</td></tr>
            <tr><td>Incidencias y tiempo de resolución</td><td>Tabla <code>incidencias</code> (fechas de alta y de resolución).</td></tr>
            <tr><td>Éxito de la integración y errores por tipo</td><td>Tabla <code>registro_integracion</code>: cada ejecución de n8n anota su resultado y la API anota cada llamada.</td></tr>
            <tr><td>Embudo y conversión</td><td>Tabla <code>eventos_recibidos</code>: copia de los eventos de la tienda que pasan por la integración.</td></tr>
            <tr><td>Propuestas del agente</td><td>Tabla <code>propuestas</code>: salida estructurada del agente y decisión humana.</td></tr>
        </tbody>
    </table>
    <p class="nota">Los mismos datos, en JSON: <code>GET /api/v1/metricas</code> (con la clave de integración).</p>
</section>
