<?php

declare(strict_types=1);

namespace KitsuneNotes\Service;

use KitsuneNotes\Repository\CustomerRepository;
use KitsuneNotes\Repository\SupportRepository;
use PDO;
use Throwable;

/**
 * Publicación de eventos hacia la plataforma de integración (n8n) · Tarea 2.
 *
 * Patrón «transactional outbox»: cuando se registra un evento al que está
 * suscrita la integración, se guarda en la tabla `integration_outbox` en la
 * misma petición. Después de responder al cliente se envía a n8n con una
 * firma HMAC-SHA256 (cabecera X-Kitsune-Firma) y el identificador del evento
 * (X-Kitsune-Evento), que n8n usa para no procesarlo dos veces.
 *
 * Si n8n no responde, está arrancando (404) o el destino falla (5xx), el evento
 * queda «pendiente» y se reintenta con esperas crecientes. Si n8n lo rechaza
 * por la firma o por datos no válidos (401, 422…), queda «rechazado» y no se
 * reintenta solo: reintentar no lo arreglaría.
 * La compra nunca se bloquea por la integración.
 */
final class WebhookPublisher
{
    /** Minutos de espera antes de cada reintento; al agotarlos, «fallido». */
    private const BACKOFF_MIN = [1, 2, 5, 15, 30, 60, 180, 360];

    public const CONTRACT = 'kitsune.evento/1';

    /** @param array<string, mixed> $config bloque «integration» de config.php */
    public function __construct(
        private readonly PDO $pdo,
        private readonly array $config,
        private readonly CustomerRepository $customers,
        private readonly SupportRepository $support,
    ) {
    }

    public function enabled(): bool
    {
        return ($this->config['webhook_url'] ?? '') !== '' && ($this->config['webhook_secret'] ?? '') !== '';
    }

    public function subscribed(string $eventName): bool
    {
        return in_array($eventName, (array) ($this->config['webhook_events'] ?? []), true);
    }

    /**
     * Encola un evento recién registrado (lo llama EventRecorder).
     *
     * @param array<string, mixed> $envelope
     * @param array<string, mixed> $payload
     */
    public function enqueue(array $envelope, array $payload): void
    {
        if (!$this->enabled() || !$this->subscribed((string) $envelope['event_name'])) {
            return;
        }

        $message = [
            'contrato'       => self::CONTRACT,
            'event_id'       => $envelope['event_id'],
            'event_name'     => $envelope['event_name'],
            'schema_version' => $envelope['schema_version'],
            'source'         => $envelope['source'],
            'occurred_at'    => $envelope['occurred_at'],
            'session_id'     => $envelope['session_id'],
            'actor_type'     => $envelope['actor_type'],
            'customer_id'    => $envelope['customer_id'],
            'product_id'     => $envelope['product_id'],
            'order_id'       => $envelope['order_id'],
            'data'           => $payload,
            'contexto'       => $this->context((string) $envelope['event_name'], $envelope, $payload),
        ];

        $statement = $this->pdo->prepare(
            'INSERT INTO integration_outbox (event_id, event_name, payload_json, status, attempts, next_attempt_at, created_at)
             VALUES (:id, :name, :payload, :status, 0, :next, :created)'
        );
        $statement->execute([
            'id'      => $envelope['event_id'],
            'name'    => $envelope['event_name'],
            'payload' => (string) json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status'  => 'pendiente',
            'next'    => gmdate('Y-m-d\TH:i:s\Z'),
            'created' => date(DATE_ATOM),
        ]);
    }

    /**
     * Datos que el evento no lleva (por privacidad, los eventos no guardan
     * nombres ni correos) pero que el back-office necesita para dar de alta
     * el pedido o la incidencia. Viajan solo por este canal firmado.
     *
     * @param array<string, mixed> $envelope
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function context(string $eventName, array $envelope, array $payload): array
    {
        try {
            if ($eventName === EventRecorder::ORDER_CREATED && $envelope['customer_id'] !== null) {
                $customer = $this->customers->findById((int) $envelope['customer_id']);

                return $customer === null ? [] : ['cliente' => [
                    'id'     => (int) $customer['id'],
                    'nombre' => (string) $customer['full_name'],
                    'email'  => (string) $customer['email'],
                ]];
            }

            if (in_array($eventName, [EventRecorder::INCIDENT_CREATED, EventRecorder::SUPPORT_REQUESTED], true)) {
                $ticket = $this->support->findByReference((string) ($payload['referencia_ticket'] ?? ''));

                return $ticket === null ? [] : ['ticket' => [
                    'referencia'        => (string) $ticket['reference'],
                    'nombre'            => (string) $ticket['customer_name'],
                    'email'             => (string) $ticket['customer_email'],
                    'tipo'              => (string) $ticket['type'],
                    'asunto'            => (string) $ticket['subject'],
                    'mensaje'           => (string) $ticket['message'],
                    'pedido_referencia' => (string) ($ticket['order_reference'] ?? '') ?: null,
                ]];
            }
        } catch (Throwable $e) {
            error_log('[kitsune-notes] Contexto de integración no disponible: ' . $e->getMessage());
        }

        return [];
    }

    /**
     * Envía los eventos pendientes cuyo turno ha llegado, en orden. Si n8n no
     * responde, se para: los demás tampoco llegarían y así no se hace esperar.
     */
    public function dispatchDue(int $max = 5, float $budgetSeconds = 6.0, bool $ignoreSchedule = false): int
    {
        if (!$this->enabled()) {
            return 0;
        }

        $start     = microtime(true);
        $statement = $this->pdo->prepare(
            "SELECT * FROM integration_outbox WHERE status = 'pendiente' AND next_attempt_at <= :now ORDER BY id LIMIT " . max(1, $max)
        );
        $statement->execute(['now' => $ignoreSchedule ? '9999-12-31T23:59:59Z' : gmdate('Y-m-d\TH:i:s\Z')]);
        $sent = 0;

        foreach ($statement->fetchAll() as $row) {
            if (microtime(true) - $start > $budgetSeconds) {
                break;
            }

            $result = $this->deliver($row);
            $sent++;

            if ($result['code'] === 0) {
                break;
            }
        }

        return $sent;
    }

    /** Reintento manual desde el back-office de la tienda. */
    public function retryNow(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM integration_outbox WHERE id = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row === false || $row['status'] === 'entregado' ? null : $this->deliver($row);
    }

    /**
     * @param array<string, mixed> $row
     * @return array{code:int, status:string}
     */
    public function deliver(array $row): array
    {
        $attempts  = (int) $row['attempts'] + 1;
        $body      = (string) $row['payload_json'];
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $body, (string) $this->config['webhook_secret']);

        [$code, $response, $error] = $this->post($body, [
            'Content-Type: application/json',
            'User-Agent: KitsuneNotes/1.1',
            'X-Kitsune-Timestamp: ' . $timestamp,
            'X-Kitsune-Firma: sha256=' . $signature,
            'X-Kitsune-Evento: ' . $row['event_id'],
            'X-Kitsune-Intento: ' . $attempts,
        ]);

        if ($code >= 200 && $code < 300) {
            $status = 'entregado';
            $next   = (string) $row['next_attempt_at'];
        } elseif (in_array($code, [400, 401, 403, 410, 413, 415, 422], true)) {
            // Rechazo definitivo (datos o firma): reintentar no lo arreglaría. Se puede
            // reenviar a mano desde el panel cuando se haya corregido la causa.
            // Un 404 sí se reintenta: es lo que contesta n8n mientras arranca.
            $status = 'rechazado';
            $next   = (string) $row['next_attempt_at'];
        } else {
            $exhausted = $attempts >= count(self::BACKOFF_MIN);
            $status    = $exhausted ? 'fallido' : 'pendiente';
            $wait      = self::BACKOFF_MIN[min($attempts, count(self::BACKOFF_MIN)) - 1];
            $next      = gmdate('Y-m-d\TH:i:s\Z', time() + $wait * 60);
        }

        $update = $this->pdo->prepare(
            'UPDATE integration_outbox
                SET status = :status, attempts = :attempts, next_attempt_at = :next, last_status_code = :code,
                    last_error = :error, response_body = :response, delivered_at = :delivered
              WHERE id = :id'
        );
        $update->execute([
            'status'    => $status,
            'attempts'  => $attempts,
            'next'      => $next,
            'code'      => $code ?: null,
            'error'     => $status === 'entregado' ? null : mb_substr($error !== '' ? $error : 'HTTP ' . $code, 0, 500),
            'response'  => $response !== '' ? mb_substr($response, 0, 2000) : null,
            'delivered' => $status === 'entregado' ? date(DATE_ATOM) : null,
            'id'        => (int) $row['id'],
        ]);

        return ['code' => $code, 'status' => $status];
    }

    /** @return list<array<string, mixed>> */
    public function recent(int $limit = 60): array
    {
        return $this->pdo->query(
            'SELECT id, event_id, event_name, status, attempts, next_attempt_at, last_status_code, last_error, response_body, created_at, delivered_at
               FROM integration_outbox ORDER BY id DESC LIMIT ' . max(1, $limit)
        )->fetchAll();
    }

    /** @return array<string, int> */
    public function countByStatus(): array
    {
        $rows = $this->pdo->query('SELECT status, COUNT(*) AS total FROM integration_outbox GROUP BY status')->fetchAll();

        return array_map('intval', array_column($rows, 'total', 'status'));
    }

    /**
     * @param list<string> $headers
     * @return array{0:int, 1:string, 2:string} código (0 = sin respuesta), cuerpo y error
     */
    private function post(string $body, array $headers): array
    {
        $timeout = max(2, (int) ($this->config['webhook_timeout'] ?? 8));
        $url     = (string) $this->config['webhook_url'];

        if (function_exists('curl_init')) {
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $body,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
            ]);
            $response = curl_exec($curl);

            return $response === false
                ? [0, '', 'Sin respuesta de n8n: ' . curl_error($curl)]
                : [(int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE), (string) $response, ''];
        }

        $context  = stream_context_create(['http' => [
            'method'        => 'POST',
            'header'        => implode("\r\n", $headers),
            'content'       => $body,
            'timeout'       => $timeout,
            'ignore_errors' => true,
        ]]);
        $response = @file_get_contents($url, false, $context);
        $code     = 0;

        foreach ($http_response_header ?? [] as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $match) === 1) {
                $code = (int) $match[1];
            }
        }

        return [$code, $response === false ? '' : $response, $response === false ? 'Sin respuesta de n8n' : ''];
    }
}
