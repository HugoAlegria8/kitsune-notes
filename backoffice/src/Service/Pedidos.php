<?php

declare(strict_types=1);

namespace KitsuneGestion\Service;

use KitsuneGestion\Core\Database;

/**
 * Pedidos y clientes del back-office.
 *
 * Los pedidos nacen en la tienda y llegan por la integración (escenario 1).
 * Desde aquí el personal los prepara y los envía; cada cambio de estado se
 * comunica a la tienda a través de n8n (escenario 2). Las transiciones son
 * las mismas que acepta la tienda, para que nunca se proponga un cambio que
 * la tienda vaya a rechazar.
 */
final class Pedidos
{
    public const TRANSICIONES = [
        'creado'                => ['pagado_simulado', 'cancelado', 'incidencia'],
        'pagado_simulado'       => ['pendiente_preparacion', 'cancelado', 'incidencia'],
        'pendiente_preparacion' => ['enviado', 'cancelado', 'incidencia'],
        'enviado'               => ['entregado', 'incidencia'],
        'entregado'             => ['incidencia'],
        'incidencia'            => ['pendiente_preparacion', 'enviado', 'entregado', 'cancelado'],
        'cancelado'             => [],
    ];

    public const ESTADOS = [
        'creado', 'pagado_simulado', 'pendiente_preparacion', 'enviado', 'entregado', 'incidencia', 'cancelado',
    ];

    public function __construct(private readonly Database $db)
    {
    }

    /** @return array<string, mixed>|null */
    public function buscar(string $referencia): ?array
    {
        return $this->db->one(
            'SELECT p.*, c.nombre AS cliente_nombre, c.email AS cliente_email, c.tienda_cliente_id,
                    c.telegram_chat_id
               FROM pedidos p LEFT JOIN clientes c ON c.id = p.cliente_id
              WHERE p.referencia = ?',
            [$referencia]
        );
    }

    /** @return list<array<string, mixed>> */
    public function lineas(int $pedidoId): array
    {
        return $this->db->all('SELECT * FROM lineas_pedido WHERE pedido_id = ? ORDER BY id', [$pedidoId]);
    }

    /** @return list<array<string, mixed>> */
    public function historial(int $pedidoId): array
    {
        return $this->db->all('SELECT * FROM historial_pedido WHERE pedido_id = ? ORDER BY id DESC', [$pedidoId]);
    }

    /** @return list<array<string, mixed>> */
    public function pagos(int $pedidoId): array
    {
        return $this->db->all('SELECT * FROM pagos WHERE pedido_id = ? ORDER BY id DESC', [$pedidoId]);
    }

    /** @return list<array<string, mixed>> */
    public function listar(?string $estado = null, int $limite = 200): array
    {
        return $this->db->all(
            "SELECT p.*, c.nombre AS cliente_nombre,
                    (SELECT COUNT(*) FROM incidencias i
                      WHERE i.pedido_id = p.id AND i.estado NOT IN ('resuelta', 'cerrada')) AS incidencias_abiertas,
                    (SELECT s.estado FROM sincronizaciones s
                      WHERE s.entidad = p.referencia AND s.tipo = 'pedido.estado_cambiado'
                      ORDER BY s.id DESC LIMIT 1) AS ultima_sincronizacion
               FROM pedidos p LEFT JOIN clientes c ON c.id = p.cliente_id
              WHERE (:estado IS NULL OR p.estado = :estado)
              ORDER BY p.creado_en_tienda DESC, p.id DESC
              LIMIT " . max(1, $limite),
            ['estado' => $estado]
        );
    }

    /**
     * Crea o completa el cliente. Se reconoce por su identificador en la
     * tienda y, si no lo trae (consultas sin pedido), por el correo.
     *
     * @param array<string, mixed> $cliente
     */
    public function guardarCliente(array $cliente): ?int
    {
        $tiendaId = isset($cliente['tienda_id']) && $cliente['tienda_id'] !== null ? (int) $cliente['tienda_id'] : null;
        $email    = strtolower(trim((string) ($cliente['email'] ?? '')));
        $nombre   = trim((string) ($cliente['nombre'] ?? ''));
        $fila     = null;

        if ($tiendaId !== null) {
            $fila = $this->db->one('SELECT * FROM clientes WHERE tienda_cliente_id = ?', [$tiendaId]);
        }

        if ($fila === null && $email !== '') {
            $fila = $this->db->one('SELECT * FROM clientes WHERE email = ? ORDER BY id LIMIT 1', [$email]);
        }

        $ahora = date(DATE_ATOM);

        if ($fila === null) {
            if ($tiendaId === null && $email === '') {
                return null;
            }

            return $this->db->insert('clientes', [
                'tienda_cliente_id' => $tiendaId,
                'nombre'            => $nombre,
                'email'             => $email,
                'creado_en'         => $ahora,
                'actualizado_en'    => $ahora,
            ]);
        }

        $cambios = [];

        if ($nombre !== '' && $nombre !== $fila['nombre']) {
            $cambios['nombre'] = $nombre;
        }

        if ($email !== '' && $email !== $fila['email']) {
            $cambios['email'] = $email;
        }

        if ($tiendaId !== null && $fila['tienda_cliente_id'] === null) {
            $cambios['tienda_cliente_id'] = $tiendaId;
        }

        if ($cambios !== []) {
            $this->db->update('clientes', [...$cambios, 'actualizado_en' => $ahora], 'id = ?', [(int) $fila['id']]);
        }

        return (int) $fila['id'];
    }

    /**
     * Escenario 1: registra un pedido llegado de la tienda. Es idempotente:
     * si el pedido ya existe no se duplica y se responde «duplicado».
     *
     * @param array<string, mixed> $datos datos ya validados por la API
     * @return array{resultado:string, id:int}
     */
    public function registrarDesdeTienda(array $datos): array
    {
        return $this->db->transaction(function () use ($datos): array {
            $existente = $this->buscar((string) $datos['referencia']);

            if ($existente !== null) {
                return ['resultado' => 'duplicado', 'id' => (int) $existente['id']];
            }

            $ahora     = date(DATE_ATOM);
            $clienteId = $this->guardarCliente((array) ($datos['cliente'] ?? []));

            $id = $this->db->insert('pedidos', [
                'referencia'       => $datos['referencia'],
                'cliente_id'       => $clienteId,
                'estado'           => $datos['estado'],
                'estado_pago'      => 'pendiente',
                'moneda'           => $datos['moneda'],
                'total_cents'      => (int) $datos['total_cents'],
                'total_eur_cents'  => (int) $datos['total_eur_cents'],
                'unidades'         => (int) $datos['unidades'],
                'metodo_envio'     => $datos['metodo_envio'] ?? null,
                'provincia_envio'  => $datos['provincia_envio'] ?? null,
                'idioma'           => $datos['idioma'] ?? 'es',
                'cupon'            => $datos['cupon'] ?? null,
                'creado_en_tienda' => $datos['creado_en'],
                'recibido_en'      => $ahora,
                'actualizado_en'   => $ahora,
                'evento_origen'    => $datos['evento_origen'] ?? null,
            ]);

            foreach ((array) $datos['lineas'] as $linea) {
                $this->db->insert('lineas_pedido', [
                    'pedido_id'        => $id,
                    'sku'              => (string) $linea['sku'],
                    'nombre'           => (string) $linea['nombre'],
                    'cantidad'         => (int) $linea['cantidad'],
                    'precio_cents'     => (int) $linea['precio_cents'],
                    'precio_eur_cents' => (int) ($linea['precio_eur_cents'] ?? $linea['precio_cents']),
                ]);
            }

            $this->anotarHistorial($id, null, (string) $datos['estado'], 'tienda', 'integracion', 'Pedido recibido desde la tienda.');

            return ['resultado' => 'creado', 'id' => $id];
        });
    }

    /**
     * Resultado de un cobro simulado. Idempotente por el evento de origen.
     *
     * @param array<string, mixed> $pedido
     * @param array<string, mixed> $datos
     * @return array{resultado:string, estado:string}
     */
    public function registrarPago(array $pedido, array $datos): array
    {
        if ($this->db->value('SELECT id FROM pagos WHERE evento_origen = ?', [$datos['evento_origen']]) !== null) {
            return ['resultado' => 'duplicado', 'estado' => (string) $pedido['estado']];
        }

        return $this->db->transaction(function () use ($pedido, $datos): array {
            $id     = (int) $pedido['id'];
            $estado = (string) $pedido['estado'];

            $this->db->insert('pagos', [
                'pedido_id'       => $id,
                'evento_origen'   => $datos['evento_origen'],
                'resultado'       => $datos['resultado'],
                'importe_cents'   => (int) $datos['importe_cents'],
                'moneda'          => $datos['moneda'],
                'referencia_pago' => $datos['referencia_pago'] ?? null,
                'motivo_rechazo'  => $datos['motivo_rechazo'] ?? null,
                'ocurrido_en'     => $datos['ocurrido_en'],
            ]);

            if ($datos['resultado'] === 'autorizado') {
                $this->db->update('pedidos', ['estado_pago' => 'autorizado', 'actualizado_en' => date(DATE_ATOM)], 'id = ?', [$id]);

                if ($estado === 'creado') {
                    $this->db->update('pedidos', ['estado' => 'pagado_simulado'], 'id = ?', [$id]);
                    $this->anotarHistorial($id, 'creado', 'pagado_simulado', 'tienda', 'integracion', 'Pago simulado autorizado (' . ($datos['referencia_pago'] ?? '') . ').');
                    $estado = 'pagado_simulado';
                }
            } elseif ($pedido['estado_pago'] !== 'autorizado') {
                $this->db->update('pedidos', ['estado_pago' => 'rechazado', 'actualizado_en' => date(DATE_ATOM)], 'id = ?', [$id]);
            }

            return ['resultado' => 'registrado', 'estado' => $estado];
        });
    }

    /**
     * Refleja un cambio de estado hecho en la tienda (por ejemplo, el cliente
     * abre una incidencia). La tienda manda sobre sus propios cambios.
     *
     * @param array<string, mixed> $pedido
     * @return array{resultado:string, estado:string}
     */
    public function reflejarEstadoTienda(array $pedido, string $nuevo, string $origen, string $nota): array
    {
        $actual = (string) $pedido['estado'];

        if ($actual === $nuevo) {
            return ['resultado' => 'sin_cambios', 'estado' => $actual];
        }

        $this->db->update('pedidos', ['estado' => $nuevo, 'actualizado_en' => date(DATE_ATOM)], 'id = ?', [(int) $pedido['id']]);
        $this->anotarHistorial((int) $pedido['id'], $actual, $nuevo, 'tienda', 'integracion', trim($origen . ($nota !== '' ? ': ' . $nota : '')));

        return ['resultado' => 'actualizado', 'estado' => $nuevo];
    }

    /**
     * Cambio de estado hecho por el personal (escenario 2). Devuelve el
     * motivo si no se puede aplicar, o null si se ha aplicado.
     *
     * @param array<string, mixed> $pedido
     */
    public function cambiarEstado(array $pedido, string $nuevo, string $actor, string $nota, string $transportista = '', string $seguimiento = ''): ?string
    {
        $actual = (string) $pedido['estado'];

        if (!in_array($nuevo, self::TRANSICIONES[$actual] ?? [], true)) {
            return sprintf('No se puede pasar de «%s» a «%s».', $actual, $nuevo);
        }

        if ($nuevo === 'enviado' && (trim($transportista) === '' || trim($seguimiento) === '')) {
            return 'Para marcarlo como enviado indica el transportista y el número de seguimiento.';
        }

        $cambios = ['estado' => $nuevo, 'actualizado_en' => date(DATE_ATOM)];

        if ($nuevo === 'enviado') {
            $cambios['transportista'] = mb_substr(trim($transportista), 0, 60);
            $cambios['seguimiento']   = mb_substr(trim($seguimiento), 0, 60);
        }

        $this->db->update('pedidos', $cambios, 'id = ?', [(int) $pedido['id']]);
        $this->anotarHistorial((int) $pedido['id'], $actual, $nuevo, 'backoffice', $actor, $nota);

        return null;
    }

    public function anotarHistorial(int $pedidoId, ?string $anterior, string $nuevo, string $origen, string $actor, ?string $nota): void
    {
        $this->db->insert('historial_pedido', [
            'pedido_id'       => $pedidoId,
            'estado_anterior' => $anterior,
            'estado_nuevo'    => $nuevo,
            'origen'          => $origen,
            'actor'           => $actor,
            'nota'            => $nota !== null ? mb_substr($nota, 0, 500) : null,
            'creado_en'       => date(DATE_ATOM),
        ]);
    }

    /** @return array<string, mixed>|null */
    public function cliente(int $id): ?array
    {
        return $this->db->one('SELECT * FROM clientes WHERE id = ?', [$id]);
    }

    /** @return list<array<string, mixed>> */
    public function deCliente(int $clienteId, int $limite = 10): array
    {
        return $this->db->all(
            'SELECT referencia, estado, estado_pago, total_cents, moneda, total_eur_cents, creado_en_tienda
               FROM pedidos WHERE cliente_id = ? ORDER BY creado_en_tienda DESC LIMIT ' . max(1, $limite),
            [$clienteId]
        );
    }

    /** Vincula el chat de Telegram al cliente del pedido (el cliente lo ha pedido desde la tienda). */
    public function vincularTelegram(array $pedido, string $chatId): void
    {
        if ($pedido['cliente_id'] === null) {
            return;
        }

        $this->db->update('clientes', [
            'telegram_chat_id'      => $chatId,
            'telegram_vinculado_en' => date(DATE_ATOM),
            'actualizado_en'        => date(DATE_ATOM),
        ], 'id = ?', [(int) $pedido['cliente_id']]);
    }
}
