<?php

declare(strict_types=1);

namespace KitsuneGestion\Core;

use DateTimeImmutable;
use Throwable;

/** Plantillas PHP sin motor externo y las ayudas que usan. */
final class View
{
    public const ETIQUETAS = [
        // pedidos
        'creado'                => 'Creado',
        'pagado_simulado'       => 'Pagado (simulado)',
        'pendiente_preparacion' => 'En preparación',
        'enviado'               => 'Enviado',
        'entregado'             => 'Entregado',
        'incidencia'            => 'Con incidencia',
        'cancelado'             => 'Cancelado',
        // pagos
        'pendiente'             => 'Pendiente',
        'autorizado'            => 'Autorizado',
        'rechazado'             => 'Rechazado',
        // incidencias
        'abierta'               => 'Abierta',
        'en_curso'              => 'En curso',
        'pendiente_cliente'     => 'Esperando al cliente',
        'resuelta'              => 'Resuelta',
        'cerrada'               => 'Cerrada',
        // propuestas
        'pendiente_revision'    => 'Pendiente de revisión',
        'aprobada'              => 'Aprobada, enviándose',
        'rechazada'             => 'Rechazada',
        'ejecutada'             => 'Enviada al cliente',
        'ejecutada_simulada'    => 'Ejecutada en modo simulado',
        'error_agente'          => 'Error del agente',
        'fallo_ejecucion'       => 'Fallo al ejecutar',
        // sincronizaciones y registro
        'confirmada'            => 'Confirmada',
        'conflicto'             => 'Conflicto',
        'fallida'               => 'Fallida',
        'ok'                    => 'Correcto',
        'duplicado'             => 'Duplicado',
        'ignorado'              => 'Ignorado',
        'error'                 => 'Error',
        // prioridades
        'alta'                  => 'Alta',
        'media'                 => 'Media',
        'baja'                  => 'Baja',
    ];

    public function __construct(private readonly App $app)
    {
    }

    /** @param array<string, mixed> $data */
    public function render(string $template, array $data = [], ?string $layout = 'layout'): string
    {
        $content = $this->capture($template, $data);

        if ($layout === null) {
            return $content;
        }

        return $this->capture($layout, [...$data, 'content' => $content]);
    }

    /** @param array<string, mixed> $data */
    public function partial(string $template, array $data = []): string
    {
        return $this->capture($template, $data);
    }

    /** @param array<string, mixed> $__data */
    private function capture(string $__template, array $__data): string
    {
        extract($__data, EXTR_SKIP);
        ob_start();

        try {
            require $this->app->root . '/views/' . $__template . '.php';
        } catch (Throwable $e) {
            ob_end_clean();

            throw $e;
        }

        return (string) ob_get_clean();
    }

    public function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function url(string $path = '/'): string
    {
        return $this->e($this->app->url($path));
    }

    public function asset(string $path): string
    {
        $file = $this->app->root . '/public/' . ltrim($path, '/');
        $version = is_file($file) ? '?v=' . substr((string) hash_file('crc32b', $file), 0, 8) : '';

        return $this->e($this->app->url($path) . $version);
    }

    public function csrf(): string
    {
        return '<input type="hidden" name="_csrf" value="' . $this->e($this->app->session()->csrfToken()) . '">';
    }

    public function money(int $cents, string $currency = 'EUR'): string
    {
        $amount = number_format($cents / 100, 2, ',', '.');

        return $currency === 'GBP' ? '£' . $amount : $amount . ' ' . ($currency === 'EUR' ? '€' : $currency);
    }

    public function fecha(?string $iso, bool $conHora = true): string
    {
        if ($iso === null || $iso === '') {
            return '—';
        }

        try {
            return (new DateTimeImmutable($iso))->format($conHora ? 'd/m/Y H:i' : 'd/m/Y');
        } catch (Throwable) {
            return $this->e($iso);
        }
    }

    public function etiqueta(?string $valor): string
    {
        return self::ETIQUETAS[(string) $valor] ?? str_replace('_', ' ', (string) $valor);
    }

    /** Insignia de estado con una clase de color según el valor. */
    public function insignia(?string $valor): string
    {
        $valor = (string) $valor;

        return '<span class="insignia insignia--' . $this->e(preg_replace('/[^a-z_]/', '', $valor)) . '">'
            . $this->e($this->etiqueta($valor)) . '</span>';
    }
}
