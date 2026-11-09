<?php

declare(strict_types=1);

namespace KitsuneGestion\Core;

use KitsuneGestion\Service\Incidencias;
use KitsuneGestion\Service\Metricas;
use KitsuneGestion\Service\Pedidos;
use KitsuneGestion\Service\Propuestas;
use KitsuneGestion\Service\RegistroIntegracion;
use KitsuneGestion\Service\Sincronizador;

/**
 * Contenedor de la aplicación: configuración, base de datos y servicios.
 * Cada servicio se crea la primera vez que se pide y se reutiliza.
 */
final class App
{
    /** @var array<string, object> */
    private array $instances = [];

    public function __construct(
        public readonly string $root,
        public readonly string $basePath = '',
        private readonly bool $secure = false,
    ) {
    }

    /** Lee una variable KG_* del entorno o del .env. */
    public function config(string $key, ?string $default = null): ?string
    {
        return Env::get('KG_' . $key, $default);
    }

    public function debug(): bool
    {
        return $this->config('APP_DEBUG', '0') === '1';
    }

    public function db(): Database
    {
        if (!isset($this->instances[Database::class])) {
            $path = $this->config('DB_PATH', $this->root . '/storage/gestion.sqlite');
            $db   = new Database((string) $path);

            if (!$db->hasTable('registro_integracion')) {
                $db->migrate($this->root . '/database/schema.sql');
            }

            $this->instances[Database::class] = $db;
            $this->ensureAdmin($db);
        }

        return $this->instances[Database::class];
    }

    public function session(): Session
    {
        return $this->instances[Session::class] ??= new Session($this->secure);
    }

    public function view(): View
    {
        return $this->instances[View::class] ??= new View($this);
    }

    public function pedidos(): Pedidos
    {
        return $this->instances[Pedidos::class] ??= new Pedidos($this->db());
    }

    public function incidencias(): Incidencias
    {
        return $this->instances[Incidencias::class] ??= new Incidencias($this->db(), $this->pedidos());
    }

    public function propuestas(): Propuestas
    {
        return $this->instances[Propuestas::class] ??= new Propuestas($this->db(), $this->incidencias());
    }

    public function registro(): RegistroIntegracion
    {
        return $this->instances[RegistroIntegracion::class] ??= new RegistroIntegracion($this->db());
    }

    public function sincronizador(): Sincronizador
    {
        return $this->instances[Sincronizador::class] ??= new Sincronizador(
            $this->db(),
            $this->registro(),
            $this->propuestas(),
            $this->pedidos(),
            (string) $this->config('N8N_WEBHOOK_URL', ''),
            (string) $this->config('N8N_SECRETO', ''),
            (int) $this->config('N8N_TIMEOUT', '20'),
        );
    }

    public function metricas(): Metricas
    {
        return $this->instances[Metricas::class] ??= new Metricas($this->db());
    }

    /** Dirección relativa dentro del back-office. */
    public function url(string $path = '/'): string
    {
        return $this->basePath . '/' . ltrim($path, '/');
    }

    /** Dirección absoluta (para enlaces en Telegram o en las respuestas de la API). */
    public function absoluteUrl(string $path = '/'): string
    {
        return rtrim((string) $this->config('APP_URL', ''), '/') . '/' . ltrim($path, '/');
    }

    /**
     * Crea el usuario del personal la primera vez. La contraseña sale del .env
     * (KG_ADMIN_PASSWORD); el valor por defecto es solo de demostración.
     */
    private function ensureAdmin(Database $db): void
    {
        if ((int) $db->value('SELECT COUNT(*) FROM usuarios') > 0) {
            return;
        }

        $db->insert('usuarios', [
            'email'         => (string) $this->config('ADMIN_EMAIL', 'gestion@kitsunenotes.test'),
            'nombre'        => 'Equipo de operaciones',
            'password_hash' => password_hash((string) $this->config('ADMIN_PASSWORD', 'gestion-demo-2026'), PASSWORD_DEFAULT),
            'rol'           => 'operador',
            'creado_en'     => date(DATE_ATOM),
        ]);
    }
}
