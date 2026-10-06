<?php

declare(strict_types=1);

namespace KitsuneNotes\Core;

/**
 * Respuesta HTTP. Los controladores devuelven siempre un objeto de este
 * tipo; el front controller es el único que envía cabeceras y contenido.
 */
final class Response
{
    /**
     * @param array<string, string>                                                    $headers
     * @param list<array{name:string, value:string, options:array<string, mixed>}>     $cookies
     */
    private function __construct(
        private readonly string $body,
        private readonly int $status = 200,
        private readonly array $headers = [],
        private readonly array $cookies = [],
    ) {
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /** @param array<string, string> $headers */
    public static function text(string $body, int $status = 200, array $headers = []): self
    {
        return new self($body, $status, $headers + ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /** @param mixed $data */
    public static function json($data, int $status = 200): self
    {
        return new self(
            (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            $status,
            ['Content-Type' => 'application/json; charset=UTF-8']
        );
    }

    public static function csv(string $body, string $filename): self
    {
        return new self($body, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /** Mensaje de correo completo (RFC 822) para descargar como fichero .eml. */
    public static function eml(string $body, string $filename): self
    {
        return new self($body, 200, [
            'Content-Type'        => 'message/rfc822',
            'Content-Disposition' => 'attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public static function redirect(string $location, int $status = 302): self
    {
        return new self('', $status, ['Location' => $location]);
    }

    /**
     * Copia de la respuesta con una cookie más. Se envía junto con las
     * cabeceras en send(), de modo que los controladores no tocan setcookie().
     *
     * @param array{expires?:int, path?:string, domain?:string, secure?:bool, httponly?:bool, samesite?:string} $options
     */
    public function withCookie(string $name, string $value, array $options = []): self
    {
        return new self(
            $this->body,
            $this->status,
            $this->headers,
            [...$this->cookies, ['name' => $name, 'value' => $value, 'options' => $options]]
        );
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        foreach ($this->cookies as $cookie) {
            setcookie($cookie['name'], $cookie['value'], $cookie['options']);
        }

        echo $this->body;
    }

    /** @return list<array{name:string, value:string, options:array<string, mixed>}> */
    public function cookies(): array
    {
        return $this->cookies;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }
}
