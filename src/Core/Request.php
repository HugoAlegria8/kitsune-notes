<?php

declare(strict_types=1);

namespace KitsuneNotes\Core;

/**
 * Encapsula la petición HTTP entrante.
 *
 * Evita que los controladores toquen directamente las superglobales,
 * lo que facilita validar y probar el flujo de datos de entrada.
 */
final class Request
{
    /**
     * @param array<string, mixed>  $query
     * @param array<string, mixed>  $body
     * @param array<string, mixed>  $server
     * @param array<string, mixed>  $cookies
     */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $query,
        private readonly array $body,
        private readonly array $server,
        private readonly string $basePath,
        private readonly array $cookies = [],
    ) {
    }

    public static function capture(): self
    {
        $server = $_SERVER;
        $method = strtoupper((string) ($server['REQUEST_METHOD'] ?? 'GET'));

        // Base path: permite desplegar la aplicación en un subdirectorio
        // del hosting (por ejemplo /tienda) sin tocar el código.
        $scriptName = (string) ($server['SCRIPT_NAME'] ?? '');
        $basePath   = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
        $basePath   = $basePath === '.' ? '' : $basePath;

        $uri  = (string) ($server['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        if ($basePath !== '' && str_starts_with($path, $basePath)) {
            $path = substr($path, strlen($basePath));
        }

        $path = '/' . trim($path, '/');

        return new self($method, $path, $_GET, $_POST, $server, $basePath, $_COOKIE);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    /**
     * URL base absoluta de la petición (esquema + host + subdirectorio).
     * El host se valida para que una cabecera Host manipulada no pueda
     * colar texto arbitrario en los enlaces de los correos.
     */
    public function baseUrl(): string
    {
        $scheme = $this->isSecure() ? 'https' : 'http';

        $host = (string) ($this->server['HTTP_HOST'] ?? $this->server['SERVER_NAME'] ?? 'localhost');

        if (preg_match('/^[A-Za-z0-9.\-]+(:\d{1,5})?$/', $host) !== 1) {
            $host = 'localhost';
        }

        return $scheme . '://' . $host . $this->basePath;
    }

    /** ¿La petición llegó por HTTPS (directamente o a través de un proxy)? */
    public function isSecure(): bool
    {
        $https     = strtolower((string) ($this->server['HTTPS'] ?? ''));
        $forwarded = strtolower((string) ($this->server['HTTP_X_FORWARDED_PROTO'] ?? ''));

        return ($https !== '' && $https !== 'off') || $forwarded === 'https';
    }

    /** Valor de una cookie enviada por el navegador, o $default si no existe. */
    public function cookie(string $name, ?string $default = null): ?string
    {
        $value = $this->cookies[$name] ?? null;

        return is_string($value) ? $value : $default;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function query(string $key, ?string $default = null): ?string
    {
        $value = $this->query[$key] ?? null;

        return is_string($value) ? trim($value) : $default;
    }

    /** @return array<string, mixed> */
    public function queryAll(): array
    {
        return $this->query;
    }

    public function input(string $key, ?string $default = null): ?string
    {
        $value = $this->body[$key] ?? null;

        if (is_string($value)) {
            return trim($value);
        }

        return $default;
    }

    public function intInput(string $key, int $default = 0): int
    {
        $value = $this->body[$key] ?? $this->query[$key] ?? null;

        return is_numeric($value) ? (int) $value : $default;
    }

    public function has(string $key): bool
    {
        return isset($this->body[$key]) || isset($this->query[$key]);
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->body;
    }

    /**
     * Fichero subido en un campo del formulario, o null si no se envió.
     *
     * @return array{name:string, type:string, tmp_name:string, error:int, size:int}|null
     */
    public function file(string $key): ?array
    {
        $file = $_FILES[$key] ?? null;

        if (!is_array($file) || !isset($file['error']) || is_array($file['error'])) {
            return null;
        }

        if ((int) $file['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        return [
            'name'     => (string) ($file['name'] ?? ''),
            'type'     => (string) ($file['type'] ?? ''),
            'tmp_name' => (string) ($file['tmp_name'] ?? ''),
            'error'    => (int) $file['error'],
            'size'     => (int) ($file['size'] ?? 0),
        ];
    }

    public function header(string $name, string $default = ''): string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        return (string) ($this->server[$key] ?? $default);
    }

    public function userAgent(): string
    {
        return substr($this->header('User-Agent'), 0, 255);
    }

    public function ip(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function referer(): string
    {
        return $this->header('Referer');
    }
}
