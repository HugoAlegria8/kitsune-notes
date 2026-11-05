<?php

declare(strict_types=1);

namespace KitsuneGestion\Service;

use KitsuneGestion\Core\Database;

/**
 * Métricas del cuadro de mando. Todas se calculan en el momento a partir de
 * los registros del propio sistema (pedidos, incidencias, propuestas,
 * registro de integración y eventos recibidos); no hay ninguna cifra escrita
 * a mano ni importada de un fichero estático.
 */
final class Metricas
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return array<string, mixed> */
    public function todas(int $dias = 14): array
    {
        return [
            'generado_en'      => date(DATE_ATOM),
            'pedidos'          => $this->pedidos(),
            'pedidos_por_dia'  => $this->pedidosPorDia($dias),
            'pedidos_estado'   => $this->pedidosPorEstado(),
            'productos_top'    => $this->productosTop(),
            'incidencias'      => $this->incidencias(),
            'integracion'      => $this->integracion(),
            'embudo'           => $this->embudo(),
            'agente'           => $this->agente(),
            'sincronizaciones' => array_map('intval', array_column(
                $this->db->all('SELECT estado, COUNT(*) AS total FROM sincronizaciones GROUP BY estado'),
                'total',
                'estado'
            )),
        ];
    }

    /** @return array<string, int|float> */
    private function pedidos(): array
    {
        $fila = $this->db->one(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN estado_pago = 'autorizado' THEN 1 ELSE 0 END) AS pagados,
                    COALESCE(SUM(CASE WHEN estado_pago = 'autorizado' THEN total_eur_cents END), 0) AS importe_eur_cents
               FROM pedidos"
        ) ?? [];
        $pagados = (int) ($fila['pagados'] ?? 0);

        return [
            'total'                => (int) ($fila['total'] ?? 0),
            'pagados'              => $pagados,
            'importe_eur_cents'    => (int) ($fila['importe_eur_cents'] ?? 0),
            'ticket_medio_eur_cents' => $pagados > 0 ? (int) round((int) $fila['importe_eur_cents'] / $pagados) : 0,
        ];
    }

    /** @return array<string, int> fecha (AAAA-MM-DD) => pedidos recibidos, con ceros en los días sin pedidos */
    private function pedidosPorDia(int $dias): array
    {
        $serie = [];

        for ($i = $dias - 1; $i >= 0; $i--) {
            $serie[date('Y-m-d', strtotime('-' . $i . ' days'))] = 0;
        }

        $filas = $this->db->all(
            'SELECT substr(creado_en_tienda, 1, 10) AS dia, COUNT(*) AS total FROM pedidos GROUP BY dia'
        );

        foreach ($filas as $fila) {
            if (array_key_exists((string) $fila['dia'], $serie)) {
                $serie[(string) $fila['dia']] = (int) $fila['total'];
            }
        }

        return $serie;
    }

    /** @return array<string, int> */
    private function pedidosPorEstado(): array
    {
        $resultado = array_fill_keys(Pedidos::ESTADOS, 0);

        foreach ($this->db->all('SELECT estado, COUNT(*) AS total FROM pedidos GROUP BY estado') as $fila) {
            $resultado[(string) $fila['estado']] = (int) $fila['total'];
        }

        return $resultado;
    }

    /** @return list<array{sku:string, nombre:string, unidades:int}> */
    private function productosTop(int $limite = 5): array
    {
        return array_map(
            static fn (array $fila): array => ['sku' => (string) $fila['sku'], 'nombre' => (string) $fila['nombre'], 'unidades' => (int) $fila['unidades']],
            $this->db->all(
                "SELECT l.sku, (SELECT l2.nombre FROM lineas_pedido l2 WHERE l2.sku = l.sku ORDER BY l2.id LIMIT 1) AS nombre,
                        SUM(l.cantidad) AS unidades
                   FROM lineas_pedido l JOIN pedidos p ON p.id = l.pedido_id
                  WHERE p.estado <> 'cancelado'
                  GROUP BY l.sku ORDER BY unidades DESC, l.sku LIMIT " . $limite
            )
        );
    }

    /** @return array<string, mixed> */
    private function incidencias(): array
    {
        $fila = $this->db->one(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN estado NOT IN ('resuelta', 'cerrada') THEN 1 ELSE 0 END) AS abiertas,
                    AVG(CASE WHEN resuelta_en IS NOT NULL
                             THEN (julianday(resuelta_en) - julianday(creada_en)) * 24 END) AS horas_resolucion,
                    SUM(CASE WHEN resuelta_en IS NOT NULL THEN 1 ELSE 0 END) AS resueltas
               FROM incidencias"
        ) ?? [];

        return [
            'total'                    => (int) ($fila['total'] ?? 0),
            'abiertas'                 => (int) ($fila['abiertas'] ?? 0),
            'resueltas'                => (int) ($fila['resueltas'] ?? 0),
            'horas_medias_resolucion'  => $fila['horas_resolucion'] !== null ? round((float) $fila['horas_resolucion'], 1) : null,
            'por_categoria'            => array_map('intval', array_column(
                $this->db->all("SELECT COALESCE(categoria, 'sin_clasificar') AS categoria, COUNT(*) AS total FROM incidencias GROUP BY 1 ORDER BY 2 DESC"),
                'total',
                'categoria'
            )),
        ];
    }

    /** @return array<string, mixed> */
    private function integracion(): array
    {
        // Resultado de cada evento procesado por los flujos de n8n.
        $porResultado = array_map('intval', array_column(
            $this->db->all("SELECT resultado, COUNT(*) AS total FROM registro_integracion WHERE origen = 'n8n' GROUP BY resultado"),
            'total',
            'resultado'
        ));
        $errores = array_map('intval', array_column(
            $this->db->all(
                "SELECT COALESCE(tipo_error, 'sin_tipo') AS tipo, COUNT(*) AS total
                   FROM registro_integracion WHERE resultado = 'error' GROUP BY 1 ORDER BY 2 DESC"
            ),
            'total',
            'tipo'
        ));
        $latencia = $this->db->value("SELECT AVG(duracion_ms) FROM registro_integracion WHERE origen = 'api' AND duracion_ms IS NOT NULL");
        $correctos = ($porResultado['ok'] ?? 0) + ($porResultado['duplicado'] ?? 0) + ($porResultado['ignorado'] ?? 0);
        $fallidos  = $porResultado['error'] ?? 0;

        return [
            'correctos'          => $correctos,
            'fallidos'           => $fallidos,
            'por_resultado'      => $porResultado,
            'tasa_exito'         => $correctos + $fallidos > 0 ? round(100 * $correctos / ($correctos + $fallidos), 1) : null,
            'errores_por_tipo'   => $errores,
            'latencia_api_ms'    => $latencia !== null ? (int) round((float) $latencia) : null,
            'llamadas_api'       => (int) $this->db->value("SELECT COUNT(*) FROM registro_integracion WHERE origen = 'api'"),
        ];
    }

    /**
     * Embudo de compra a partir de los eventos de la tienda recibidos por la
     * integración: sesiones que añaden al carrito, que empiezan el checkout,
     * pedidos creados y pagos autorizados.
     *
     * @return array<string, mixed>
     */
    private function embudo(): array
    {
        $carrito  = (int) $this->db->value("SELECT COUNT(DISTINCT session_id) FROM eventos_recibidos WHERE event_name = 'cart.item_added'");
        $checkout = (int) $this->db->value("SELECT COUNT(DISTINCT session_id) FROM eventos_recibidos WHERE event_name = 'checkout.started'");
        $pedidos  = (int) $this->db->value("SELECT COUNT(DISTINCT pedido_ref) FROM eventos_recibidos WHERE event_name = 'order.created'");
        $pagados  = (int) $this->db->value("SELECT COUNT(DISTINCT pedido_ref) FROM eventos_recibidos WHERE event_name = 'payment.simulated' AND resultado = 'autorizado'");

        return [
            'etapas' => [
                'Añaden al carrito' => $carrito,
                'Empiezan el pago'  => $checkout,
                'Pedido creado'     => $pedidos,
                'Pago autorizado'   => $pagados,
            ],
            'conversion_checkout_pct' => $checkout > 0 ? round(100 * $pagados / $checkout, 1) : null,
            'conversion_carrito_pct'  => $carrito > 0 ? round(100 * $pagados / $carrito, 1) : null,
        ];
    }

    /** @return array<string, mixed> */
    private function agente(): array
    {
        $porEstado = array_map('intval', array_column(
            $this->db->all('SELECT estado, COUNT(*) AS total FROM propuestas GROUP BY estado'),
            'total',
            'estado'
        ));
        $aprobadas  = ($porEstado['aprobada'] ?? 0) + ($porEstado['ejecutada'] ?? 0) + ($porEstado['ejecutada_simulada'] ?? 0) + ($porEstado['fallo_ejecucion'] ?? 0);
        $rechazadas = $porEstado['rechazada'] ?? 0;
        $editadas   = (int) $this->db->value(
            "SELECT COUNT(*) FROM propuestas WHERE mensaje_final IS NOT NULL AND TRIM(mensaje_final) <> TRIM(mensaje_cliente)"
        );

        return [
            'propuestas'        => array_sum($porEstado),
            'por_estado'        => $porEstado,
            'aprobadas'         => $aprobadas,
            'rechazadas'        => $rechazadas,
            'pendientes'        => $porEstado['pendiente_revision'] ?? 0,
            'errores_agente'    => $porEstado['error_agente'] ?? 0,
            'editadas_al_aprobar' => $editadas,
            'tasa_aprobacion'   => $aprobadas + $rechazadas > 0 ? round(100 * $aprobadas / ($aprobadas + $rechazadas), 1) : null,
        ];
    }
}
