<?php

declare(strict_types=1);

namespace KitsuneNotes\Core;

/**
 * Validador de entradas de formulario.
 *
 * Las reglas se declaran como cadenas separadas por '|', por ejemplo
 * 'requerido|email|max:120'. Los mensajes están escritos en castellano
 * y se muestran directamente en la interfaz; si se le pasa el traductor,
 * salen en el idioma activo. Los nombres de los campos ($labels) deben
 * llegar ya traducidos: en la tienda se pasan con $this->t('…').
 */
final class Validator
{
    /**
     * Mensaje de error de cada regla («*» es el genérico). El texto en español
     * es también la clave de traducción: si se cambia uno, hay que actualizar
     * su entrada en lang/en/comun.php. Marcadores: {campo}, {n}, {min}, {max}.
     */
    public const MESSAGES = [
        'requerido' => 'El campo «{campo}» es obligatorio.',
        'email'     => 'Introduce una dirección de correo válida en «{campo}».',
        'min'       => '«{campo}» debe tener al menos {n} caracteres.',
        'max'       => '«{campo}» no puede superar los {n} caracteres.',
        'digitos'   => '«{campo}» solo admite dígitos.',
        'longitud'  => '«{campo}» debe tener exactamente {n} caracteres.',
        'cp'        => 'El código postal debe tener 5 dígitos.',
        'telefono'  => 'El teléfono no tiene un formato válido.',
        'en'        => 'El valor seleccionado en «{campo}» no es válido.',
        'entero'    => '«{campo}» debe ser un número entero.',
        'tarjeta'   => 'El número de tarjeta no es válido (no supera la comprobación de Luhn).',
        'caducidad' => 'La fecha de caducidad debe tener el formato MM/AA y no estar vencida.',
        'aceptado'  => 'Debes aceptar «{campo}» para continuar.',
        'importe'   => '«{campo}» debe ser un importe en euros, por ejemplo 12,90.',
        'entre'     => '«{campo}» debe estar entre {min} y {max}.',
        'sku'       => '«{campo}» solo admite letras, números y guiones (por ejemplo KN-CUA-004).',
        'slug'      => '«{campo}» solo admite minúsculas sin tildes, números y guiones.',
        '*'         => 'El campo «{campo}» no es válido.',
    ];

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
        private readonly ?Translator $translator = null,
    ) {
        $this->run();
    }

    /** @param array<string, scalar|null> $params */
    private function tr(string $text, array $params = []): string
    {
        return $this->translator !== null
            ? $this->translator->get($text, $params)
            : Translator::interpolate($text, $params);
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
        $params = ['campo' => $label, 'n' => $parameter];

        if ($rule === 'entre') {
            [$min, $max] = array_pad(explode(',', (string) $parameter), 2, '');
            $params += ['min' => $min, 'max' => $max];
        }

        return $this->tr(self::MESSAGES[$rule] ?? self::MESSAGES['*'], $params);
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
