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

    // --- Idioma -------------------------------------------------------

    /**
     * Texto de interfaz traducido al idioma activo y ya escapado. Se escribe
     * en español (es la clave de traducción) y sirve para texto normal y
     * para atributos: <?= $this->t('Añadir al carrito') ?>
     *
     * Los valores variables van aparte, con marcadores:
     * $this->t('Pedido {referencia}', ['referencia' => $order['reference']])
     *
     * @param array<string, scalar|null> $params
     */
    public function t(string $text, array $params = []): string
    {
        return $this->e($this->app->translator()->get($text, $params));
    }

    /**
     * Como t(), pero para frases que llevan su propio HTML (negritas,
     * enlaces): el texto se imprime sin escapar porque lo ha escrito el
     * equipo, y solo se escapan los valores de $params.
     *
     * @param array<string, scalar|null> $params
     */
    public function th(string $html, array $params = []): string
    {
        return $this->app->translator()->get(
            $html,
            array_map(fn (mixed $value): string => $this->e($value), $params)
        );
    }

    /**
     * Singular o plural según $count, traducido y escapado. El marcador {n}
     * es el propio número: $this->tn('{n} artículo', '{n} artículos', $total)
     *
     * @param array<string, scalar|null> $params
     */
    public function tn(string $singular, string $plural, int $count, array $params = []): string
    {
        return $this->e($this->app->translator()->choice($singular, $plural, $count, $params));
    }

    /** Código del idioma activo: «es» o «en». */
    public function locale(): string
    {
        return $this->app->translator()->locale();
    }

    /**
     * Pinta algo en otro idioma y vuelve al activo. La factura lo usa para
     * salir siempre en el idioma en que se expidió.
     *
     * @param callable():string $callback
     */
    public function inLocale(string $locale, callable $callback): string
    {
        return (string) $this->app->translator()->runIn($locale, $callback);
    }

    /** Dirección de la página actual que cambia el idioma (botones ES/EN). */
    public function localeUrl(string $locale): string
    {
        $request = $this->app->request();
        $query   = $request->queryAll();

        unset($query['idioma']);
        $query['idioma'] = $locale;

        return $this->url($request->path()) . '?' . http_build_query($query);
    }

    // --- Importes y fechas --------------------------------------------

    /**
     * Formatea un importe en céntimos. Sin moneda se usa la de la tienda en
     * el idioma activo (euros en español, libras en inglés); para un pedido
     * o una factura se pasa siempre SU moneda: $this->money($c, $order['currency']).
     * El euro se escribe detrás del importe y la libra delante.
     */
    public function money(int $cents, ?string $currency = null): string
    {
        return $this->app->currency()->format($cents, $currency);
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
        $path = ltrim($path, '/');
        $url  = ($base === '' ? '' : $base) . '/' . $path;

        // Las hojas de estilo y los scripts llevan una huella de su contenido:
        // cuando el fichero cambia, cambia su dirección, y el navegador no puede
        // seguir usando la copia antigua que tenía guardada junto a un HTML nuevo.
        if (preg_match('/\.(?:css|js)$/', $path) === 1) {
            $file = $this->app->rootDir() . '/public/' . $path;

            if (is_file($file)) {
                $url .= '?v=' . hash_file('crc32b', $file);
            }
        }

        return $url;
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

        // En inglés el mes va con letras («6 Oct 2026») para que la fecha se
        // lea igual en el Reino Unido que en Estados Unidos.
        if ($this->locale() === 'en') {
            return $date->format($withTime ? 'j M Y, H:i' : 'j M Y');
        }

        return $date->format($withTime ? 'd/m/Y H:i' : 'd/m/Y');
    }

    /** Etiqueta legible y color asociados a un estado de pedido. */
    /** @return array{label:string, tone:string} */
    public function statusBadge(string $status): array
    {
        $badge = match ($status) {
            'creado'                 => ['label' => 'Creado', 'tone' => 'neutral'],
            'pagado_simulado'        => ['label' => 'Pagado (simulado)', 'tone' => 'success'],
            'pendiente_preparacion'  => ['label' => 'Pendiente de preparación', 'tone' => 'info'],
            'enviado'                => ['label' => 'Enviado', 'tone' => 'info'],
            'entregado'              => ['label' => 'Entregado', 'tone' => 'success'],
            'cancelado'              => ['label' => 'Cancelado', 'tone' => 'muted'],
            'incidencia'             => ['label' => 'Con incidencia', 'tone' => 'warning'],
            default                  => ['label' => ucfirst(str_replace('_', ' ', $status)), 'tone' => 'neutral'],
        };

        $badge['label'] = $this->app->translator()->get($badge['label']);

        return $badge;
    }

    /**
     * Nota de la cronología del pedido tal como debe verla el cliente. Las
     * notas se guardan en español (son también el registro del back-office);
     * las que escribe el sistema se traducen aquí al idioma activo. Una nota
     * redactada a mano por el personal se muestra como se escribió.
     */
    public function historyNote(string $note): string
    {
        $translator = $this->app->translator();

        if ($translator->isDefault() || $note === '') {
            return $note;
        }

        $patterns = [
            '/^Pago simulado autorizado con código (\S+)\.$/u'      => ['Pago simulado autorizado con código {codigo}.', 'codigo'],
            '/^Incidencia (\S+) comunicada por el cliente\.$/u'     => ['Incidencia {referencia} comunicada por el cliente.', 'referencia'],
        ];

        foreach ($patterns as $pattern => [$text, $param]) {
            if (preg_match($pattern, $note, $matches) === 1) {
                return $translator->get($text, [$param => $matches[1]]);
            }
        }

        return $translator->has($note) ? $translator->get($note) : $note;
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
            'estandar' => $this->app->translator()->get('estándar'),
            'express'  => $this->app->translator()->get('exprés'),
            default    => $method,
        };
    }

    public function app(): App
    {
        return $this->app;
    }
}
