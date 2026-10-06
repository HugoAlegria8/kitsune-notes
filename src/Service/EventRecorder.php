<?php

declare(strict_types=1);

namespace KitsuneNotes\Service;

use DateTimeImmutable;
use DateTimeZone;
use KitsuneNotes\Core\Session;
use KitsuneNotes\Repository\EventRepository;
use KitsuneNotes\Support\Uuid;
use Throwable;

/**
 * Instrumentación de eventos de negocio.
 *
 * Cada acción relevante del proceso de compra emite un evento con un
 * sobre común (identificador, nombre, versión de esquema, origen,
 * instante, sesión y actor) y una carga útil específica.
 *
 * Los eventos se escriben simultáneamente en dos destinos:
 *   1. La tabla `events` (consulta desde el back-office y desde el API).
 *   2. Un fichero JSON Lines diario en storage/events/, pensado para que
 *      un sistema externo lo ingiera sin tocar la base de datos.
 *
 * La emisión de eventos nunca debe romper la experiencia de compra: si
 * el registro falla, se traza el error y el flujo continúa.
 */
final class EventRecorder
{
    public const PRODUCT_VIEWED    = 'product.viewed';
    public const CART_ITEM_ADDED   = 'cart.item_added';
    public const CART_ITEM_REMOVED = 'cart.item_removed';
    public const CHECKOUT_STARTED  = 'checkout.started';
    public const ORDER_CREATED     = 'order.created';
    public const PAYMENT_SIMULATED = 'payment.simulated';
    public const SUPPORT_REQUESTED = 'support.requested';
    public const INCIDENT_CREATED  = 'incident.created';
    public const ORDER_STATUS_CHANGED = 'order.status_changed';

    // Mantenimiento del catálogo (datos maestros) desde el back-office
    public const PRODUCT_CREATED  = 'product.created';
    public const PRODUCT_UPDATED  = 'product.updated';
    public const PRODUCT_ARCHIVED = 'product.archived';
    public const PRODUCT_RESTORED = 'product.restored';
    public const PRODUCT_DELETED  = 'product.deleted';

    // Postventa automática: factura y correos transaccionales
    public const INVOICE_ISSUED = 'invoice.issued';
    public const INVOICE_VIEWED = 'invoice.viewed';
    public const EMAIL_SENT     = 'email.sent';

    /** Catálogo de eventos publicado (contrato con la Tarea 2). */
    public const CATALOG = [
        self::PRODUCT_VIEWED       => 'Visualización de la ficha de un producto.',
        self::CART_ITEM_ADDED      => 'Se añade un producto al carrito.',
        self::CART_ITEM_REMOVED    => 'Se elimina un producto del carrito.',
        self::CHECKOUT_STARTED     => 'El cliente inicia el proceso de checkout.',
        self::ORDER_CREATED        => 'Se genera un pedido con referencia única.',
        self::PAYMENT_SIMULATED    => 'Resultado del cobro simulado (autorizado o rechazado).',
        self::SUPPORT_REQUESTED    => 'Solicitud de soporte o contacto postventa.',
        self::INCIDENT_CREATED     => 'Incidencia registrada sobre un pedido existente.',
        self::ORDER_STATUS_CHANGED => 'Cambio de estado de un pedido desde el back-office.',
        self::PRODUCT_CREATED      => 'Alta de un producto nuevo en el catálogo.',
        self::PRODUCT_UPDATED      => 'Modificación de un producto, con los campos cambiados.',
        self::PRODUCT_ARCHIVED     => 'Un producto se retira del catálogo sin borrarse.',
        self::PRODUCT_RESTORED     => 'Un producto retirado vuelve a publicarse.',
        self::PRODUCT_DELETED      => 'Borrado definitivo de un producto sin ventas.',
        self::INVOICE_ISSUED       => 'Se expide la factura de un pedido pagado (numeración correlativa).',
        self::INVOICE_VIEWED       => 'El cliente consulta su factura, desde su pedido o desde el enlace del correo.',
        self::EMAIL_SENT           => 'Correo transaccional emitido al cliente: siempre queda en el buzón de pruebas y, si el envío '
            . 'real está activado y la dirección autorizada, también se entrega por SMTP (campo «entrega»).',
    ];

    private ?string $ipHash = null;

    /** Con true, record() no persiste nada: se usa al cargar datos históricos de prueba. */
    private bool $muted = false;

    private string $userAgent = '';

    /** @param array<string, mixed> $config */
    public function __construct(
        private readonly EventRepository $repository,
        private readonly Session $session,
        private readonly array $config,
        private readonly string $salt,
    ) {
    }

    /**
     * Silencia la emisión de eventos. El instalador lo activa al generar las
     * facturas y los correos de los pedidos históricos de demostración, que
     * no son actividad real y no deben contaminar las métricas.
     */
    public function mute(bool $muted = true): void
    {
        $this->muted = $muted;
    }

    /** Contexto de la petición: se seudonimiza la IP, nunca se guarda en claro. */
    public function withRequestContext(string $ip, string $userAgent): void
    {
        $this->ipHash    = substr(hash('sha256', $this->salt . '|' . $ip), 0, 32);
        $this->userAgent = $userAgent;
    }

    /**
     * Registra un evento.
     *
     * @param array<string, mixed> $payload datos específicos del evento
     * @param array{customer_id?:int|null, product_id?:int|null, order_id?:int|null, actor_type?:string} $refs
     *
     * @return array<string, mixed> el sobre del evento emitido
     */
    public function record(string $name, array $payload = [], array $refs = []): array
    {
        if ($this->muted) {
            return [];
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Madrid'));

        $envelope = [
            'event_id'       => Uuid::v4(),
            'event_name'     => $name,
            'schema_version' => (string) ($this->config['schema_version'] ?? '1.0'),
            'source'         => (string) ($this->config['source'] ?? 'kitsune-notes.web'),
            'occurred_at'    => $now->format(DATE_ATOM),
            'session_id'     => $this->session->id(),
            'actor_type'     => (string) ($refs['actor_type'] ?? 'invitado'),
            'customer_id'    => isset($refs['customer_id']) ? (int) $refs['customer_id'] : null,
            'product_id'     => isset($refs['product_id']) ? (int) $refs['product_id'] : null,
            'order_id'       => isset($refs['order_id']) ? (int) $refs['order_id'] : null,
            'payload_json'   => (string) json_encode($payload, JSON_UNESCAPED_UNICODE),
            'ip_hash'        => $this->ipHash ?? '',
            'user_agent'     => $this->userAgent,
            'created_at'     => $now->format(DATE_ATOM),
        ];

        try {
            $this->repository->insert($envelope);
        } catch (Throwable $e) {
            $this->logFailure('bd', $name, $e);
        }

        $this->appendToLog($envelope, $payload);

        return $envelope;
    }

    /**
     * Vuelca el evento en el fichero JSON Lines del día.
     *
     * @param array<string, mixed> $envelope
     * @param array<string, mixed> $payload
     */
    private function appendToLog(array $envelope, array $payload): void
    {
        if (($this->config['log_enabled'] ?? true) !== true) {
            return;
        }

        $directory = (string) ($this->config['log_path'] ?? '');

        if ($directory === '') {
            return;
        }

        try {
            if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                return;
            }

            $record = $envelope;
            unset($record['payload_json'], $record['created_at']);
            $record['data'] = $payload;

            $file = $directory . '/events-' . date('Y-m-d') . '.jsonl';
            file_put_contents(
                $file,
                json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
                FILE_APPEND | LOCK_EX
            );
        } catch (Throwable $e) {
            $this->logFailure('fichero', $envelope['event_name'], $e);
        }
    }

    private function logFailure(string $destination, string $eventName, Throwable $e): void
    {
        error_log(sprintf(
            '[kitsune-notes] No se pudo registrar el evento %s en %s: %s',
            $eventName,
            $destination,
            $e->getMessage()
        ));
    }
}
