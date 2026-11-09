<?php

declare(strict_types=1);

namespace KitsuneGestion\Support;

use DateTimeImmutable;
use Throwable;

/**
 * Validación de las cargas que llegan a la API. n8n ya valida y normaliza,
 * pero el back-office vuelve a comprobarlo todo: no se fía de nadie
 * (defensa en profundidad). Admite campos anidados con punto: «cliente.email».
 */
final class Validador
{
    /** @var array<string, string> */
    private array $errores = [];

    /** @param array<string, mixed> $datos */
    public function __construct(private readonly array $datos)
    {
    }

    public function valor(string $campo): mixed
    {
        $actual = $this->datos;

        foreach (explode('.', $campo) as $parte) {
            if (!is_array($actual) || !array_key_exists($parte, $actual)) {
                return null;
            }

            $actual = $actual[$parte];
        }

        return $actual;
    }

    private function vacio(mixed $valor): bool
    {
        return $valor === null || (is_string($valor) && trim($valor) === '') || $valor === [];
    }

    private function falta(string $campo, bool $requerido): bool
    {
        if ($this->vacio($this->valor($campo))) {
            if ($requerido) {
                $this->errores[$campo] ??= 'Es obligatorio.';
            }

            return true;
        }

        return false;
    }

    public function texto(string $campo, int $maximo, bool $requerido = true): self
    {
        if (!$this->falta($campo, $requerido)) {
            $valor = $this->valor($campo);

            if (!is_string($valor)) {
                $this->errores[$campo] ??= 'Debe ser un texto.';
            } elseif (mb_strlen($valor) > $maximo) {
                $this->errores[$campo] ??= 'Admite como máximo ' . $maximo . ' caracteres.';
            }
        }

        return $this;
    }

    public function entero(string $campo, int $minimo = 0, ?int $maximo = null, bool $requerido = true): self
    {
        if (!$this->falta($campo, $requerido)) {
            $valor = $this->valor($campo);

            if (!is_int($valor)) {
                $this->errores[$campo] ??= 'Debe ser un número entero (los importes van en céntimos).';
            } elseif ($valor < $minimo || ($maximo !== null && $valor > $maximo)) {
                $this->errores[$campo] ??= 'Fuera de rango (' . $minimo . ' a ' . ($maximo ?? '∞') . ').';
            }
        }

        return $this;
    }

    public function decimal(string $campo, float $minimo, float $maximo, bool $requerido = true): self
    {
        if (!$this->falta($campo, $requerido)) {
            $valor = $this->valor($campo);

            if (!is_int($valor) && !is_float($valor)) {
                $this->errores[$campo] ??= 'Debe ser un número.';
            } elseif ($valor < $minimo || $valor > $maximo) {
                $this->errores[$campo] ??= 'Debe estar entre ' . $minimo . ' y ' . $maximo . '.';
            }
        }

        return $this;
    }

    /** @param list<string> $permitidos */
    public function en(string $campo, array $permitidos, bool $requerido = true): self
    {
        if (!$this->falta($campo, $requerido) && !in_array($this->valor($campo), $permitidos, true)) {
            $this->errores[$campo] ??= 'Valor no admitido. Valores posibles: ' . implode(', ', $permitidos) . '.';
        }

        return $this;
    }

    public function patron(string $campo, string $regex, string $mensaje, bool $requerido = true): self
    {
        if (!$this->falta($campo, $requerido)) {
            $valor = $this->valor($campo);

            if (!is_string($valor) || preg_match($regex, $valor) !== 1) {
                $this->errores[$campo] ??= $mensaje;
            }
        }

        return $this;
    }

    public function email(string $campo, bool $requerido = true): self
    {
        if (!$this->falta($campo, $requerido) && filter_var($this->valor($campo), FILTER_VALIDATE_EMAIL) === false) {
            $this->errores[$campo] ??= 'No es un correo válido.';
        }

        return $this;
    }

    public function fecha(string $campo, bool $requerido = true): self
    {
        if (!$this->falta($campo, $requerido)) {
            $valor = $this->valor($campo);

            try {
                if (!is_string($valor) || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $valor) !== 1) {
                    throw new \InvalidArgumentException();
                }

                new DateTimeImmutable($valor);
            } catch (Throwable) {
                $this->errores[$campo] ??= 'Debe ser una fecha ISO-8601 con hora (2026-11-04T18:30:00+01:00).';
            }
        }

        return $this;
    }

    public function lista(string $campo, int $minimo = 1, int $maximo = 100): self
    {
        $valor = $this->valor($campo);

        if (!is_array($valor) || !array_is_list($valor)) {
            $this->errores[$campo] ??= 'Debe ser una lista.';
        } elseif (count($valor) < $minimo || count($valor) > $maximo) {
            $this->errores[$campo] ??= 'Debe tener entre ' . $minimo . ' y ' . $maximo . ' elementos.';
        }

        return $this;
    }

    /** @param array<string, string> $errores */
    public function anadir(array $errores): self
    {
        $this->errores = [...$this->errores, ...$errores];

        return $this;
    }

    public function falla(): bool
    {
        return $this->errores !== [];
    }

    /** @return array<string, string> */
    public function errores(): array
    {
        return $this->errores;
    }
}
