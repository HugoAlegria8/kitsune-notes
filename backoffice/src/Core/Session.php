<?php

declare(strict_types=1);

namespace KitsuneGestion\Core;

/** Sesión del personal: cookie HttpOnly y SameSite=Lax, token CSRF y avisos flash. */
final class Session
{
    private bool $started = false;

    public function __construct(private readonly bool $secure)
    {
    }

    public function start(): void
    {
        if ($this->started || PHP_SAPI === 'cli') {
            $this->started = true;

            return;
        }

        session_name('kitsune_gestion');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $this->secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
        $this->started = true;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->start();

        return $_SESSION[$key] ?? $default;
    }

    public function put(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    public function forget(string $key): void
    {
        $this->start();
        unset($_SESSION[$key]);
    }

    public function regenerate(): void
    {
        $this->start();

        if (PHP_SAPI !== 'cli') {
            session_regenerate_id(true);
        }
    }

    public function destroy(): void
    {
        $this->start();
        $_SESSION = [];

        if (PHP_SAPI !== 'cli') {
            session_destroy();
        }
    }

    public function flash(string $type, string $message): void
    {
        $messages   = $this->get('_flash', []);
        $messages[] = ['tipo' => $type, 'texto' => $message];
        $this->put('_flash', $messages);
    }

    /** @return list<array{tipo:string, texto:string}> */
    public function pullFlash(): array
    {
        $messages = $this->get('_flash', []);
        $this->forget('_flash');

        return $messages;
    }

    public function csrfToken(): string
    {
        $token = $this->get('_csrf');

        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $this->put('_csrf', $token);
        }

        return $token;
    }

    public function csrfValid(?string $token): bool
    {
        return is_string($token) && $token !== '' && hash_equals($this->csrfToken(), $token);
    }

    /** @return array<string, mixed>|null */
    public function user(): ?array
    {
        $user = $this->get('_usuario');

        return is_array($user) ? $user : null;
    }
}
