<?php

declare(strict_types=1);

namespace KitsuneNotes\Core;

use RuntimeException;

/**
 * Motor de plantillas mínimo basado en PHP plano.
 *
 * Las plantillas se incluyen dentro de un método de esta clase, de modo
 * que dentro de ellas `$this` es la propia vista y se pueden usar los
 * ayudantes de escape y formato sin funciones globales.
 */
final class View
{
    /** @var array<string, mixed> */
    private array $shared = [];

    public function __construct(
        private readonly App $app,
        private readonly string $viewPath,
    ) {
    }

    /** @param array<string, mixed> $data */
    public function share(array $data): void
    {
        $this->shared = array_merge($this->shared, $data);
    }

    /** @param array<string, mixed> $data */
    public function render(string $template, array $data = [], ?string $layout = 'layout/main'): string
    {
        $content = $this->partial($template, $data);

        if ($layout === null) {
            return $content;
        }

        return $this->partial($layout, array_merge($data, ['content' => $content]));
    }

    /** @param array<string, mixed> $data */
    public function partial(string $template, array $data = []): string
    {
        $file = $this->viewPath . '/' . str_replace('.', '/', $template) . '.php';

        if (!is_file($file)) {
            throw new RuntimeException("No se encuentra la plantilla: {$template}");
        }

        extract(array_merge($this->shared, $data), EXTR_SKIP);

        ob_start();
        include $file;

        return (string) ob_get_clean();
    }

    // -----------------------------------------------------------------
    // Ayudantes disponibles en las plantillas
    // -----------------------------------------------------------------

    /** Escapa cualquier valor antes de imprimirlo (prevención de XSS). */
    public function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Formatea un importe en céntimos como precio en euros. */
    public function money(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '.') . ' €';
    }

    /** Construye una URL interna respetando el subdirectorio de despliegue. */
    public function url(string $path = '/'): string
    {
        $base = $this->app->basePath();
        $path = '/' . ltrim($path, '/');

        return ($base === '' ? '' : $base) . ($path === '/' ? '/' : rtrim($path, '/'));
    }

    /** URL absoluta (con esquema y host), necesaria en los enlaces de los correos. */
    public function absoluteUrl(string $path = '/'): string
    {
        return $this->app->absoluteUrl($path);
    }

    public function asset(string $path): string
    {
        $base = $this->app->basePath();

        return ($base === '' ? '' : $base) . '/' . ltrim($path, '/');
    }

    public function csrf(): string
    {
        return '<input type="hidden" name="_token" value="'
            . $this->e($this->app->session()->csrfToken()) . '">';
    }

    /** Fecha legible a partir de una marca ISO-8601. */
    public function date(?string $iso, bool $withTime = true): string
    {
        if ($iso === null || $iso === '') {
            return '—';
        }

        try {
            $date = new \DateTimeImmutable($iso);
        } catch (\Exception) {
            return $this->e($iso);
        }

        return $date->format($withTime ? 'd/m/Y H:i' : 'd/m/Y');
    }

    /** Etiqueta legible y color asociados a un estado de pedido. */
    /** @return array{label:string, tone:string} */
    public function statusBadge(string $status): array
    {
        return match ($status) {
            'creado'                 => ['label' => 'Creado', 'tone' => 'neutral'],
            'pagado_simulado'        => ['label' => 'Pagado (simulado)', 'tone' => 'success'],
            'pendiente_preparacion'  => ['label' => 'Pendiente de preparación', 'tone' => 'info'],
            'enviado'                => ['label' => 'Enviado', 'tone' => 'info'],
            'entregado'              => ['label' => 'Entregado', 'tone' => 'success'],
            'cancelado'              => ['label' => 'Cancelado', 'tone' => 'muted'],
            'incidencia'             => ['label' => 'Con incidencia', 'tone' => 'warning'],
            default                  => ['label' => ucfirst(str_replace('_', ' ', $status)), 'tone' => 'neutral'],
        };
    }

    /**
     * Etiqueta y tono de la insignia con el resultado de la entrega de un
     * correo (véase Mailer::DELIVERY_*).
     *
     * @return array{label:string, tone:string}
     */
    public function deliveryBadge(string $status): array
    {
        return match ($status) {
            'enviado'    => ['label' => 'Enviado por SMTP', 'tone' => 'success'],
            'fallido'    => ['label' => 'Fallo de envío', 'tone' => 'error'],
            default      => ['label' => 'Solo buzón', 'tone' => 'muted'],
        };
    }

    /** Nombre legible del método de envío guardado en el pedido. */
    public function shippingLabel(string $method): string
    {
        return match ($method) {
            'estandar' => 'estándar',
            'express'  => 'exprés',
            default    => $method,
        };
    }

    public function app(): App
    {
        return $this->app;
    }
}
