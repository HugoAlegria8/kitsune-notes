<?php

declare(strict_types=1);

namespace KitsuneGestion\Service;

use KitsuneGestion\Core\Database;

/**
 * Bandeja de salida hacia n8n (patrón «transactional outbox»).
 *
 * Cada cambio que el back-office tiene que comunicar (un pedido que pasa a
 * «enviado», una propuesta del agente aprobada por una persona) se guarda
 * primero aquí y después se envía a n8n firmado con HMAC-SHA256. Si n8n o la
 * tienda no responden, el cambio queda «pendiente de sincronización» y se
 * reintenta con esperas crecientes; si la tienda lo rechaza por una regla de
 * negocio, queda en «conflicto» para que lo revise una persona. Nunca se
 * pierde un cambio ni se aplica dos veces (n8n recibe el identificador).
 */
final class Sincronizador
{
    /** Minutos de espera antes de cada reintento; al agotarlos, «fallida». */
    private const ESPERAS_MIN = [1, 2, 5, 10, 30, 60, 120, 240];

    public function __construct(
        private readonly Database $db,
        private readonly RegistroIntegracion $registro,
        private readonly Propuestas $propuestas,
        private readonly Pedidos $pedidos,
        private readonly string $url,
        private readonly string $secreto,
        private readonly int $timeout,
    ) {
    }

    public function configurado(): bool
    {
        return $this->url !== '' && $this->secreto !== '';
    }

    /**
     * Guarda el cambio y lo intenta entregar en el momento.
     *
     * @param array<string, mixed> $datos
     * @return array<string, mixed> la fila tras el intento
     */
    public function emitir(string $tipo, string $entidad, array $datos): array
    {
        $ahora = date(DATE_ATOM);
        $id    = $this->db->insert('sincronizaciones', [
            'tipo'              => $tipo,
            'entidad'           => $entidad,
            'carga'             => json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'estado'            => 'pendiente',
            'intentos'          => 0,
            'siguiente_intento' => gmdate('Y-m-d\TH:i:s\Z'),
            'creada_en'         => $ahora,
            'actualizada_en'    => $ahora,
        ]);

        return $this->entregar((array) $this->buscar($id));
    }

    /** @return array<string, mixed>|null */
    public function buscar(int $id): ?array
    {
        return $this->db->one('SELECT * FROM sincronizaciones WHERE id = ?', [$id]);
    }

    /** @return list<array<string, mixed>> */
    public function listar(?string $estado = null, int $limite = 100): array
    {
        return $this->db->all(
            'SELECT * FROM sincronizaciones WHERE (:estado IS NULL OR estado = :estado) ORDER BY id DESC LIMIT ' . max(1, $limite),
            ['estado' => $estado]
        );
    }

    /** @return list<array<string, mixed>> */
    public function deEntidad(string $entidad): array
    {
        return $this->db->all('SELECT * FROM sincronizaciones WHERE entidad = ? ORDER BY id DESC', [$entidad]);
    }

    /**
     * Reintenta las pendientes cuyo turno ha llegado (o todas, si se fuerza
     * desde el botón del panel). Devuelve cuántas se han intentado.
     */
    public function reintentarPendientes(int $maximo = 3, bool $forzar = false): int
    {
        $filas = $this->db->all(
            "SELECT * FROM sincronizaciones
              WHERE estado = 'pendiente' AND (siguiente_intento IS NULL OR siguiente_intento <= ?)
              ORDER BY id LIMIT " . max(1, $maximo),
            [$forzar ? '9999-12-31T23:59:59Z' : gmdate('Y-m-d\TH:i:s\Z')]
        );

        foreach ($filas as $fila) {
            $this->entregar($fila);
        }

        return count($filas);
    }

    /** Reintento manual desde el panel (también para «conflicto» y «fallida»). */
    public function reintentarAhora(int $id): ?array
    {
        $fila = $this->buscar($id);

        if ($fila === null || $fila['estado'] === 'confirmada') {
            return null;
        }

        return $this->entregar($fila);
    }

    /**
     * @param array<string, mixed> $fila
     * @return array<string, mixed>
     */
    public function entregar(array $fila): array
    {
        $id       = (int) $fila['id'];
        $intentos = (int) $fila['intentos'] + 1;

        if (!$this->configurado()) {
            $this->db->update('sincronizaciones', [
                'estado'            => 'pendiente',
                'intentos'          => $intentos,
                'ultimo_error'      => 'Falta configurar KG_N8N_WEBHOOK_URL y KG_N8N_SECRETO en el .env.',
                'siguiente_intento' => gmdate('Y-m-d\TH:i:s\Z', time() + 600),
                'actualizada_en'    => date(DATE_ATOM),
            ], 'id = ?', [$id]);
            $this->anotar($fila, 'error', 'configuracion', null, 'n8n no está configurado en el back-office', 0);

            return (array) $this->buscar($id);
        }

        $cuerpo = (string) json_encode([
            'tipo'              => $fila['tipo'],
            'sincronizacion_id' => $id,
            'entidad'           => $fila['entidad'],
            'emitido_en'        => date(DATE_ATOM),
            'intento'           => $intentos,
            'datos'             => json_decode((string) $fila['carga'], true),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $marca = (string) time();
        [$codigo, $respuesta, $error, $ms] = $this->post($cuerpo, [
            'Content-Type: application/json',
            'User-Agent: KitsuneGestion/1.0',
            'X-Kitsune-Timestamp: ' . $marca,
            'X-Kitsune-Firma: sha256=' . hash_hmac('sha256', $marca . '.' . $cuerpo, $this->secreto),
            'X-Kitsune-Id: gestion-' . $id,
        ]);
        $json    = json_decode($respuesta, true);
        $json    = is_array($json) ? $json : [];
        $detalle = (string) ($json['mensaje'] ?? $json['detalle'] ?? $json['error'] ?? '');

        if ($codigo >= 200 && $codigo < 300) {
            $this->db->update('sincronizaciones', [
                'estado'            => 'confirmada',
                'intentos'          => $intentos,
                'ultimo_error'      => null,
                'respuesta'         => mb_substr($respuesta, 0, 2000),
                'siguiente_intento' => null,
                'actualizada_en'    => date(DATE_ATOM),
                'confirmada_en'     => date(DATE_ATOM),
            ], 'id = ?', [$id]);
            $this->anotar($fila, 'ok', null, $codigo, $detalle !== '' ? $detalle : 'Entregado a n8n y confirmado', $ms);
            $this->aplicarResultado($fila, $json);
        } elseif (in_array($codigo, [409, 422], true)) {
            // Rechazo por una regla de negocio o por datos: reintentar no lo arregla.
            $this->db->update('sincronizaciones', [
                'estado'            => 'conflicto',
                'intentos'          => $intentos,
                'ultimo_error'      => mb_substr('HTTP ' . $codigo . ($detalle !== '' ? ': ' . $detalle : ''), 0, 500),
                'respuesta'         => mb_substr($respuesta, 0, 2000),
                'siguiente_intento' => null,
                'actualizada_en'    => date(DATE_ATOM),
            ], 'id = ?', [$id]);
            $this->anotar($fila, 'error', $codigo === 409 ? 'conflicto_negocio' : 'validacion', $codigo, $detalle, $ms);
            $this->marcarFalloPropuesta($fila, 'Rechazado por el destino: ' . $detalle);
        } else {
            $agotado = $intentos >= count(self::ESPERAS_MIN);
            $espera  = self::ESPERAS_MIN[min($intentos, count(self::ESPERAS_MIN)) - 1];
            $motivo  = $codigo === 0 ? 'n8n no responde: ' . $error : 'HTTP ' . $codigo . ($detalle !== '' ? ': ' . $detalle : '');

            $this->db->update('sincronizaciones', [
                'estado'            => $agotado ? 'fallida' : 'pendiente',
                'intentos'          => $intentos,
                'ultimo_error'      => mb_substr($motivo, 0, 500),
                'respuesta'         => $respuesta !== '' ? mb_substr($respuesta, 0, 2000) : null,
                'siguiente_intento' => $agotado ? null : gmdate('Y-m-d\TH:i:s\Z', time() + $espera * 60),
                'actualizada_en'    => date(DATE_ATOM),
            ], 'id = ?', [$id]);
            $this->anotar($fila, 'error', $codigo === 0 ? 'n8n_no_disponible' : ($codigo >= 500 ? 'destino_no_disponible' : 'http_' . $codigo), $codigo ?: null, $motivo, $ms);

            if ($agotado) {
                $this->marcarFalloPropuesta($fila, $motivo);
            }
        }

        return (array) $this->buscar($id);
    }

    /** @return array<string, int> */
    public function contarPorEstado(): array
    {
        return array_column($this->db->all('SELECT estado, COUNT(*) AS total FROM sincronizaciones GROUP BY estado'), 'total', 'estado');
    }

    /**
     * Efectos de una entrega confirmada.
     *
     * @param array<string, mixed> $fila
     * @param array<string, mixed> $respuesta
     */
    private function aplicarResultado(array $fila, array $respuesta): void
    {
        if ($fila['tipo'] !== 'propuesta.aprobada') {
            return;
        }

        $datos  = json_decode((string) $fila['carga'], true);
        $estado = in_array($respuesta['estado'] ?? '', ['ejecutada', 'ejecutada_simulada'], true)
            ? (string) $respuesta['estado']
            : 'ejecutada_simulada';
        $texto  = (string) ($respuesta['detalle'] ?? ($estado === 'ejecutada'
            ? 'Mensaje enviado por Telegram.'
            : 'El cliente no tiene Telegram vinculado: envío simulado.'));

        $this->propuestas->marcarResultado((int) ($datos['propuesta_id'] ?? 0), $estado, $texto);
    }

    /** @param array<string, mixed> $fila */
    private function marcarFalloPropuesta(array $fila, string $motivo): void
    {
        if ($fila['tipo'] === 'propuesta.aprobada') {
            $datos = json_decode((string) $fila['carga'], true);
            $this->propuestas->marcarResultado((int) ($datos['propuesta_id'] ?? 0), 'fallo_ejecucion', $motivo);
        }
    }

    /** @param array<string, mixed> $fila */
    private function anotar(array $fila, string $resultado, ?string $tipoError, ?int $codigo, string $mensaje, int $ms): void
    {
        $this->registro->anotar([
            'origen'      => 'backoffice',
            'flujo'       => 'bandeja de salida',
            'operacion'   => 'enviar ' . $fila['tipo'] . ' a n8n',
            'event_id'    => 'gestion-' . $fila['id'],
            'entidad'     => $fila['entidad'],
            'resultado'   => $resultado,
            'tipo_error'  => $tipoError,
            'codigo_http' => $codigo,
            'mensaje'     => $mensaje,
            'duracion_ms' => $ms,
        ]);
    }

    /**
     * @param list<string> $cabeceras
     * @return array{0:int, 1:string, 2:string, 3:int} código HTTP (0 si no hubo respuesta), cuerpo, error y milisegundos
     */
    private function post(string $cuerpo, array $cabeceras): array
    {
        $inicio = microtime(true);

        if (function_exists('curl_init')) {
            $curl = curl_init($this->url);
            curl_setopt_array($curl, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $cuerpo,
                CURLOPT_HTTPHEADER     => $cabeceras,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => max(3, $this->timeout),
                CURLOPT_CONNECTTIMEOUT => min(10, max(3, $this->timeout)),
            ]);
            $respuesta = curl_exec($curl);
            $codigo    = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            $error     = $respuesta === false ? curl_error($curl) : '';

            return [$respuesta === false ? 0 : $codigo, $respuesta === false ? '' : (string) $respuesta, $error, (int) round((microtime(true) - $inicio) * 1000)];
        }

        $contexto = stream_context_create(['http' => [
            'method'        => 'POST',
            'header'        => implode("\r\n", $cabeceras),
            'content'       => $cuerpo,
            'timeout'       => max(3, $this->timeout),
            'ignore_errors' => true,
        ]]);
        $respuesta = @file_get_contents($this->url, false, $contexto);
        $codigo    = 0;

        foreach ($http_response_header ?? [] as $linea) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $linea, $m) === 1) {
                $codigo = (int) $m[1];
            }
        }

        return [$codigo, $respuesta === false ? '' : $respuesta, $respuesta === false ? 'sin respuesta' : '', (int) round((microtime(true) - $inicio) * 1000)];
    }
}
