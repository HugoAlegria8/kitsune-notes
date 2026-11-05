<?php

declare(strict_types=1);

namespace KitsuneGestion\Core;

/** Petición HTTP entrante, ya separada de las variables globales. */
final class Request
{
    /**
     * @param array<string, string> $query
     * @param array<string, mixed>  $post
     * @param array<string, string> $headers cabeceras con el nombre en minúsculas
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        private readonly array $query,
        private readonly array $post,
        private readonly array $headers,
        private readonly string $body,
        private readonly bool $secure,
    ) {
    }

    public static function fromGlobals(string $basePath): self
    {
        $path = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

        if ($basePath !== '' && str_starts_with($path, $basePath)) {
            $path = substr($path, strlen($basePath));
        }

        $path = '/' . trim(rawurldecode($path), '/');

        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (str_starts_with((string) $key, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr((string) $key, 5)))] = (string) $value;
            }
        }

        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = (string) $_SERVER['CONTENT_TYPE'];
        }

        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || strtolower($headers['x-forwarded-proto'] ?? '') === 'https';

        return new self(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            $path,
            array_map('strval', array_filter($_GET, 'is_scalar')),
            $_POST,
            $headers,
            (string) file_get_contents('php://input'),
            $secure,
        );
    }

    public function query(string $key, ?string $default = null): ?string
    {
        $value = $this->query[$key] ?? null;

        return $value === null || $value === '' ? $default : $value;
    }

    public function input(string $key, ?string $default = null): ?string
    {
        $value = $this->post[$key] ?? null;

        return is_scalar($value) ? trim((string) $value) : $default;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function body(): string
    {
        return $this->body;
    }

    /** Cuerpo JSON decodificado, o null si no es un objeto JSON válido. */
    public function json(): ?array
    {
        $data = json_decode($this->body, true);

        return is_array($data) ? $data : null;
    }

    public function isSecure(): bool
    {
        return $this->secure;
    }

    public function isApi(): bool
    {
        return str_starts_with($this->path, '/api/');
    }
}
