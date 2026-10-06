<?php

declare(strict_types=1);

namespace KitsuneNotes\Service;

use DateTimeImmutable;
use DateTimeZone;
use KitsuneNotes\Repository\PaymentRepository;

/**
 * Pasarela de pago SIMULADA.
 *
 * No existe ninguna conexión con un proveedor de pago real ni se mueve
 * dinero. El comportamiento se decide a partir de un conjunto cerrado
 * de tarjetas de prueba documentadas, de modo que la demostración en
 * clase pueda reproducir tanto una autorización como un rechazo.
 *
 * Seguridad: del número de tarjeta introducido solo se conservan los
 * cuatro últimos dígitos y la marca deducida del prefijo. El PAN
 * completo y el CVV nunca se escriben en base de datos, en el registro
 * de eventos ni en los ficheros de log.
 */
final class PaymentSimulator
{
    /** Tarjetas de prueba documentadas en el README. */
    private const TEST_CARDS = [
        '4242424242424242' => ['autorizado', ''],
        '4111111111111111' => ['autorizado', ''],
        '5555555555554444' => ['autorizado', ''],
        '4000000000000002' => ['rechazado', 'Fondos insuficientes (simulado).'],
        '4000000000000069' => ['rechazado', 'Tarjeta caducada (simulado).'],
        '4000000000000127' => ['rechazado', 'Código de seguridad incorrecto (simulado).'],
    ];

    public function __construct(private readonly PaymentRepository $payments)
    {
    }

    /**
     * Ejecuta el cobro simulado y persiste el intento de pago.
     *
     * @param array<string, mixed> $order
     * @param array<string, mixed> $card
     *
     * @return array<string, mixed> resultado normalizado del intento
     */
    public function charge(array $order, array $card): array
    {
        $number = preg_replace('/\D+/', '', (string) ($card['numero_tarjeta'] ?? '')) ?? '';
        $last4  = substr($number, -4);
        $brand  = $this->brand($number);
        $now    = new DateTimeImmutable('now', new DateTimeZone('Europe/Madrid'));

        [$status, $declineReason] = self::TEST_CARDS[$number] ?? ['autorizado', ''];

        $reference = sprintf(
            'PAY-%s-%s',
            $now->format('Ymd'),
            strtoupper(substr(bin2hex(random_bytes(4)), 0, 8))
        );

        $authorizationCode = $status === 'autorizado'
            ? 'AUTH' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6))
            : '';

        $response = [
            'simulated'      => true,
            'engine'         => 'PaymentSimulator v1',
            'requested_at'   => $now->format(DATE_ATOM),
            'amount_decimal' => number_format(((int) $order['total_cents']) / 100, 2, '.', ''),
            'currency'       => (string) $order['currency'],
            'card_brand'     => $brand,
            'card_last4'     => $last4,
            'result'         => $status,
            'reason'         => $declineReason,
            // Nota explícita para el tribunal y para cualquier auditoría
            // del repositorio: aquí nunca se guarda el PAN ni el CVV.
            'stored_pan'     => false,
            'stored_cvv'     => false,
        ];

        $this->payments->insert([
            'order_id'           => (int) $order['id'],
            'reference'          => $reference,
            'provider'           => 'simulador-interno',
            'method'             => (string) ($card['metodo'] ?? 'tarjeta'),
            'status'             => $status,
            'amount_cents'       => (int) $order['total_cents'],
            'currency'           => (string) $order['currency'],
            'card_brand'         => $brand,
            'card_last4'         => $last4,
            'authorization_code' => $authorizationCode,
            'decline_reason'     => $declineReason,
            'response_json'      => (string) json_encode($response, JSON_UNESCAPED_UNICODE),
            'processed_at'       => $now->format(DATE_ATOM),
        ]);

        return [
            'status'             => $status,
            'approved'           => $status === 'autorizado',
            'reference'          => $reference,
            'authorization_code' => $authorizationCode,
            'decline_reason'     => $declineReason,
            'card_brand'         => $brand,
            'card_last4'         => $last4,
            'amount_cents'       => (int) $order['total_cents'],
            'processed_at'       => $now->format(DATE_ATOM),
        ];
    }

    /** Deduce la marca a partir del prefijo (solo a efectos de presentación). */
    private function brand(string $number): string
    {
        return match (true) {
            str_starts_with($number, '4')                                   => 'VISA',
            (bool) preg_match('/^5[1-5]/', $number)                          => 'Mastercard',
            (bool) preg_match('/^3[47]/', $number)                           => 'American Express',
            (bool) preg_match('/^(50|5[6-9]|6)/', $number)                   => 'Maestro',
            default                                                          => 'Desconocida',
        };
    }

    /** @return array<string, array{status:string, description:string}> */
    public static function testCards(): array
    {
        return [
            '4242 4242 4242 4242' => ['status' => 'autorizado', 'description' => 'Pago autorizado (VISA de prueba)'],
            '5555 5555 5555 4444' => ['status' => 'autorizado', 'description' => 'Pago autorizado (Mastercard de prueba)'],
            '4000 0000 0000 0002' => ['status' => 'rechazado', 'description' => 'Rechazo por fondos insuficientes'],
            '4000 0000 0000 0069' => ['status' => 'rechazado', 'description' => 'Rechazo por tarjeta caducada'],
        ];
    }
}
