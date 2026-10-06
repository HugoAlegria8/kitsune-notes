<?php

declare(strict_types=1);

namespace KitsuneNotes\Core;

/**
 * Validador de entradas de formulario.
 *
 * Las reglas se declaran como cadenas separadas por '|', por ejemplo
 * 'requerido|email|max:120'. Todos los mensajes están en castellano
 * porque se muestran directamente en la interfaz.
 */
final class Validator
{
    /** @var array<string, list<string>> */
    private array $errors = [];

    /** @var array<string, mixed> */
    private array $valid = [];

    /**
     * @param array<string, mixed>  $data
     * @param array<string, string> $rules
     * @param array<string, string> $labels
     */
    public function __construct(
        private readonly array $data,
        private readonly array $rules,
        private readonly array $labels = [],
    ) {
        $this->run();
    }

    private function run(): void
    {
        foreach ($this->rules as $field => $ruleString) {
            // Un campo que no llega en la petición se trata como vacío, de
            // modo que las reglas de formato solo se aplican si hay valor.
            $value = $this->data[$field] ?? '';
            $value = is_string($value) ? trim($value) : $value;
            $label = $this->labels[$field] ?? $field;

            foreach (explode('|', $ruleString) as $rule) {
                [$name, $parameter] = array_pad(explode(':', $rule, 2), 2, null);

                $failed = match ($name) {
                    'requerido'  => $value === null || $value === '',
                    'email'      => $value !== '' && !filter_var((string) $value, FILTER_VALIDATE_EMAIL),
                    'min'        => $value !== '' && mb_strlen((string) $value) < (int) $parameter,
                    'max'        => $value !== '' && mb_strlen((string) $value) > (int) $parameter,
                    'digitos'    => $value !== '' && !preg_match('/^[0-9]+$/', (string) $value),
                    'longitud'   => $value !== '' && mb_strlen((string) $value) !== (int) $parameter,
                    'cp'         => $value !== '' && !preg_match('/^[0-9]{5}$/', (string) $value),
                    'telefono'   => $value !== '' && !preg_match('/^[+0-9 ()-]{9,20}$/', (string) $value),
                    'en'         => $value !== '' && !in_array((string) $value, explode(',', (string) $parameter), true),
                    'entero'     => $value !== '' && !preg_match('/^-?[0-9]+$/', (string) $value),
                    'tarjeta'    => $value !== '' && !self::luhn(preg_replace('/\s+/', '', (string) $value) ?? ''),
                    'caducidad'  => $value !== '' && !self::expiry((string) $value),
                    'aceptado'   => !in_array($value, ['1', 'on', 'true', 'si'], true),
                    'importe'    => $value !== '' && self::cents((string) $value) === null,
                    'entre'      => $value !== '' && !self::between((string) $value, (string) $parameter),
                    'sku'        => $value !== '' && !preg_match('/^[A-Za-z0-9]+(-[A-Za-z0-9]+){1,3}$/', (string) $value),
                    'slug'       => $value !== '' && !preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', (string) $value),
                    default      => false,
                };

                if ($failed) {
                    $this->errors[$field][] = $this->message($name, $label, $parameter);
                    break; // un solo mensaje por campo
                }
            }

            if (!isset($this->errors[$field])) {
                $this->valid[$field] = $value;
            }
        }
    }

    private function message(string $rule, string $label, ?string $parameter): string
    {
        return match ($rule) {
            'requerido' => "El campo «{$label}» es obligatorio.",
            'email'     => "Introduce una dirección de correo válida en «{$label}».",
            'min'       => "«{$label}» debe tener al menos {$parameter} caracteres.",
            'max'       => "«{$label}» no puede superar los {$parameter} caracteres.",
            'digitos'   => "«{$label}» solo admite dígitos.",
            'longitud'  => "«{$label}» debe tener exactamente {$parameter} caracteres.",
            'cp'        => 'El código postal debe tener 5 dígitos.',
            'telefono'  => 'El teléfono no tiene un formato válido.',
            'en'        => "El valor seleccionado en «{$label}» no es válido.",
            'entero'    => "«{$label}» debe ser un número entero.",
            'tarjeta'   => 'El número de tarjeta no es válido (no supera la comprobación de Luhn).',
            'caducidad' => 'La fecha de caducidad debe tener el formato MM/AA y no estar vencida.',
            'aceptado'  => "Debes aceptar «{$label}» para continuar.",
            'importe'   => "«{$label}» debe ser un importe en euros, por ejemplo 12,90.",
            'entre'     => "«{$label}» debe estar entre " . str_replace(',', ' y ', (string) $parameter) . '.',
            'sku'       => "«{$label}» solo admite letras, números y guiones (por ejemplo KN-CUA-004).",
            'slug'      => "«{$label}» solo admite minúsculas sin tildes, números y guiones.",
            default     => "El campo «{$label}» no es válido.",
        };
    }

    /**
     * Algoritmo de Luhn: valida la estructura del número de tarjeta.
     * No comprueba que la tarjeta exista: en este prototipo todos los
     * números son ficticios y el cobro es simulado.
     */
    public static function luhn(string $number): bool
    {
        if (!preg_match('/^[0-9]{12,19}$/', $number)) {
            return false;
        }

        $sum    = 0;
        $double = false;

        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $digit = (int) $number[$i];

            if ($double) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
            $double = !$double;
        }

        return $sum % 10 === 0;
    }

    /**
     * Convierte un importe escrito por una persona («12,90», «12.9», «12»)
     * a céntimos. Devuelve null si el formato no es válido.
     */
    public static function cents(string $value): ?int
    {
        $value = trim(str_replace(['€', ' '], '', $value));

        if (!preg_match('/^\d{1,5}([.,]\d{1,2})?$/', $value)) {
            return null;
        }

        [$euros, $decimals] = array_pad(preg_split('/[.,]/', $value) ?: [], 2, '0');

        return (int) $euros * 100 + (int) str_pad($decimals, 2, '0');
    }

    /** Comprueba que un entero está en el rango «min,max». */
    private static function between(string $value, string $range): bool
    {
        if (!preg_match('/^-?\d+$/', $value)) {
            return false;
        }

        [$min, $max] = array_map('intval', explode(',', $range) + [0, PHP_INT_MAX]);

        return (int) $value >= $min && (int) $value <= $max;
    }

    private static function expiry(string $value): bool
    {
        if (!preg_match('#^(0[1-9]|1[0-2])/([0-9]{2})$#', $value, $m)) {
            return false;
        }

        $month = (int) $m[1];
        $year  = 2000 + (int) $m[2];
        $limit = (new \DateTimeImmutable("{$year}-{$month}-01"))->modify('last day of this month');

        return $limit >= new \DateTimeImmutable('today');
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /** @return array<string, list<string>> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return list<string> */
    public function flatErrors(): array
    {
        return array_merge(...array_values($this->errors)) ?: [];
    }

    /** @return array<string, mixed> */
    public function validated(): array
    {
        return $this->valid;
    }
}
