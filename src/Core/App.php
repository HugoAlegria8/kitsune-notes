<?php

declare(strict_types=1);

namespace KitsuneNotes\Core;

use KitsuneNotes\Repository\CategoryRepository;
use KitsuneNotes\Repository\CouponRepository;
use KitsuneNotes\Repository\CustomerRepository;
use KitsuneNotes\Repository\DesignLineRepository;
use KitsuneNotes\Repository\EventRepository;
use KitsuneNotes\Repository\InvoiceRepository;
use KitsuneNotes\Repository\MailRepository;
use KitsuneNotes\Repository\OrderRepository;
use KitsuneNotes\Repository\PaymentRepository;
use KitsuneNotes\Repository\ProductRepository;
use KitsuneNotes\Repository\StaffRepository;
use KitsuneNotes\Repository\SupportRepository;
use KitsuneNotes\Service\AuthService;
use KitsuneNotes\Service\CartService;
use KitsuneNotes\Service\CatalogAdminService;
use KitsuneNotes\Service\CatalogLocalizer;
use KitsuneNotes\Service\CurrencyService;
use KitsuneNotes\Service\EventRecorder;
use KitsuneNotes\Service\InvoiceService;
use KitsuneNotes\Service\Mailer;
use KitsuneNotes\Service\Notifier;
use KitsuneNotes\Service\OrderService;
use KitsuneNotes\Service\PaymentSimulator;
use KitsuneNotes\Service\PricingService;
use PDO;

/**
 * Contenedor de servicios muy sencillo.
 *
 * Centraliza la construcción de repositorios y servicios y garantiza que
 * cada uno se instancia una sola vez por petición. Sustituye a un
 * contenedor de inyección de dependencias completo, innecesario para el
 * tamaño de este prototipo.
 */
final class App
{
    /** @var array<string, object> */
    private array $instances = [];

    private ?Request $request = null;

    /** @param array<string, mixed> $config */
    public function __construct(
        private readonly array $config,
        private readonly string $basePathDir,
    ) {
    }

    /** Acceso a la configuración con notación de puntos: config('app.name'). */
    public function config(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value    = $this->config;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    public function rootDir(): string
    {
        return $this->basePathDir;
    }

    public function setRequest(Request $request): void
    {
        $this->request = $request;
    }

    public function request(): Request
    {
        return $this->request ??= Request::capture();
    }

    /** Prefijo de URL cuando la aplicación vive en un subdirectorio. */
    public function basePath(): string
    {
        return $this->request()->basePath();
    }

    /**
     * URL absoluta de una ruta de la tienda. Prioridad: KN_APP_URL, la
     * petición en curso y, en línea de comandos (instalador), localhost:8000.
     */
    public function absoluteUrl(string $path = '/'): string
    {
        $path       = '/' . ltrim($path, '/');
        $configured = rtrim((string) $this->config('app.url', ''), '/');

        if ($configured !== '') {
            return $configured . $path;
        }

        if (PHP_SAPI === 'cli') {
            return 'http://localhost:8000' . $path;
        }

        return $this->request()->baseUrl() . $path;
    }

    // -----------------------------------------------------------------
    // Infraestructura
    // -----------------------------------------------------------------

    public function database(): Database
    {
        return $this->instances[Database::class] ??= new Database(
            (array) $this->config('database')
        );
    }

    public function pdo(): PDO
    {
        return $this->database()->pdo();
    }

    public function session(): Session
    {
        return $this->instances[Session::class] ??= new Session();
    }

    public function view(): View
    {
        return $this->instances[View::class] ??= new View($this, $this->basePathDir . '/views');
    }

    // -----------------------------------------------------------------
    // Idioma y moneda
    // -----------------------------------------------------------------

    public function translator(): Translator
    {
        return $this->instances[Translator::class] ??= new Translator(
            $this->basePathDir . '/lang',
            (array) $this->config('i18n.locales', ['es' => []]),
            (string) $this->config('i18n.default', 'es')
        );
    }

    public function currency(): CurrencyService
    {
        return $this->instances[CurrencyService::class] ??= new CurrencyService(
            (array) $this->config('commerce'),
            $this->translator()
        );
    }

    /** Adapta las filas del catálogo al idioma y a la moneda activos. */
    public function localizer(): CatalogLocalizer
    {
        return $this->instances[CatalogLocalizer::class] ??= new CatalogLocalizer(
            $this->translator(),
            $this->currency()
        );
    }

    /**
     * Fija el idioma de la petición. Se llama una vez desde el front controller.
     *
     *  - Los botones ES/EN de la cabecera enlazan a la misma página con
     *    «?idioma=en»: se guarda la elección en una cookie técnica y se
     *    redirige a la dirección sin el parámetro (devuelve esa redirección).
     *  - Sin parámetro, vale el idioma de la cookie; sin cookie, el español.
     *  - El back-office y el API solo existen en español: ahí la cookie no
     *    se tiene en cuenta ni se modifica.
     */
    public function bootLocale(Request $request): ?Response
    {
        $path = $request->path();

        if ($path === '/admin' || str_starts_with($path, '/admin/') || str_starts_with($path, '/api/')) {
            return null;
        }

        $translator = $this->translator();
        $cookieName = (string) $this->config('i18n.cookie_name', 'kitsune_idioma');
        $requested  = (string) ($request->query('idioma') ?? '');

        if ($requested !== '' && $request->method() === 'GET' && $translator->supports($requested)) {
            $query = $request->queryAll();
            unset($query['idioma']);

            $days = max(1, (int) $this->config('i18n.cookie_days', 180));

            return Response::redirect(
                $this->view()->url($path) . ($query !== [] ? '?' . http_build_query($query) : '')
            )->withCookie($cookieName, $requested, [
                'expires'  => time() + $days * 86400,
                'path'     => '/',
                'secure'   => $request->isSecure(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        $translator->setLocale((string) ($request->cookie($cookieName) ?? ''));

        return null;
    }

    // -----------------------------------------------------------------
    // Repositorios (acceso a datos)
    // -----------------------------------------------------------------

    public function products(): ProductRepository
    {
        return $this->instances[ProductRepository::class] ??= new ProductRepository($this->pdo(), $this->localizer());
    }

    public function categories(): CategoryRepository
    {
        return $this->instances[CategoryRepository::class] ??= new CategoryRepository($this->pdo(), $this->localizer());
    }

    public function designLines(): DesignLineRepository
    {
        return $this->instances[DesignLineRepository::class] ??= new DesignLineRepository($this->pdo(), $this->localizer());
    }

    public function customers(): CustomerRepository
    {
        return $this->instances[CustomerRepository::class] ??= new CustomerRepository($this->pdo());
    }

    public function orders(): OrderRepository
    {
        return $this->instances[OrderRepository::class] ??= new OrderRepository($this->pdo());
    }

    public function payments(): PaymentRepository
    {
        return $this->instances[PaymentRepository::class] ??= new PaymentRepository($this->pdo());
    }

    public function eventsRepository(): EventRepository
    {
        return $this->instances[EventRepository::class] ??= new EventRepository($this->pdo());
    }

    public function coupons(): CouponRepository
    {
        return $this->instances[CouponRepository::class] ??= new CouponRepository($this->pdo());
    }

    public function support(): SupportRepository
    {
        return $this->instances[SupportRepository::class] ??= new SupportRepository($this->pdo());
    }

    public function staff(): StaffRepository
    {
        return $this->instances[StaffRepository::class] ??= new StaffRepository($this->pdo());
    }

    public function invoicesRepository(): InvoiceRepository
    {
        return $this->instances[InvoiceRepository::class] ??= new InvoiceRepository($this->pdo());
    }

    public function mailRepository(): MailRepository
    {
        return $this->instances[MailRepository::class] ??= new MailRepository($this->pdo());
    }

    // -----------------------------------------------------------------
    // Servicios (lógica de negocio)
    // -----------------------------------------------------------------

    public function cart(): CartService
    {
        return $this->instances[CartService::class] ??= new CartService(
            $this->session(),
            $this->products(),
            (int) $this->config('commerce.max_units_per_line', 10)
        );
    }

    public function pricing(): PricingService
    {
        return $this->instances[PricingService::class] ??= new PricingService(
            (array) $this->config('commerce'),
            $this->coupons(),
            $this->currency(),
            $this->translator()
        );
    }

    public function orderService(): OrderService
    {
        return $this->instances[OrderService::class] ??= new OrderService(
            $this->pdo(),
            $this->orders(),
            $this->customers(),
            $this->products()
        );
    }

    public function paymentSimulator(): PaymentSimulator
    {
        return $this->instances[PaymentSimulator::class] ??= new PaymentSimulator($this->payments());
    }

    public function events(): EventRecorder
    {
        return $this->instances[EventRecorder::class] ??= new EventRecorder(
            $this->eventsRepository(),
            $this->session(),
            (array) $this->config('events'),
            (string) $this->config('security.event_salt')
        );
    }

    public function auth(): AuthService
    {
        return $this->instances[AuthService::class] ??= new AuthService(
            $this->staff(),
            $this->session()
        );
    }

    public function invoices(): InvoiceService
    {
        return $this->instances[InvoiceService::class] ??= new InvoiceService(
            $this->pdo(),
            $this->invoicesRepository(),
            $this->orders(),
            $this->payments(),
            $this->events(),
            (array) $this->config('company'),
            (array) $this->config('invoicing'),
            (array) $this->config('commerce'),
            $this->translator(),
            $this->currency()
        );
    }

    public function mailer(): Mailer
    {
        return $this->instances[Mailer::class] ??= new Mailer(
            $this->mailRepository(),
            $this->events(),
            (array) $this->config('mail')
        );
    }

    public function notifier(): Notifier
    {
        return $this->instances[Notifier::class] ??= new Notifier(
            $this->mailer(),
            $this->view(),
            $this->invoices(),
            (array) $this->config('company'),
            $this->translator()
        );
    }

    public function catalogAdmin(): CatalogAdminService
    {
        return $this->instances[CatalogAdminService::class] ??= new CatalogAdminService(
            $this->products(),
            $this->categories(),
            $this->designLines(),
            $this->events(),
            $this->basePathDir . '/public'
        );
    }
}
