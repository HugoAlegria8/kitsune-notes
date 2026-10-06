<?php

declare(strict_types=1);

namespace KitsuneNotes\Core;

/**
 * Gestión de sesión, mensajes flash y protección CSRF.
 *
 * El carrito vive en la sesión (solo identificadores y cantidades: los
 * precios se releen siempre de la base de datos) y el identificador de
 * sesión se usa como clave de correlación en los eventos de negocio.
 */
final class Session
{
    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_name('kitsune_session');
        session_start();

        if (!isset($_SESSION['_created_at'])) {
            $_SESSION['_created_at'] = time();
        }
    }

    public function id(): string
    {
        $this->start();

        return session_id() ?: 'sin-sesion';
    }

    /** @return mixed */
    public function get(string $key, $default = null)
    {
        $this->start();

        return $_SESSION[$key] ?? $default;
    }

    /** @param mixed $value */
    public function put(string $key, $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    public function forget(string $key): void
    {
        $this->start();
        unset($_SESSION[$key]);
    }

    public function flash(string $type, string $message): void
    {
        $this->start();
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    /** @return list<array{type:string, message:string}> */
    public function pullFlash(): array
    {
        $this->start();
        $messages = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);

        return $messages;
    }

    /**
     * Guarda los datos enviados en un formulario para poder repintarlo
     * con los valores introducidos cuando la validación falla.
     *
     * @param array<string, mixed> $data
     */
    public function flashInput(array $data): void
    {
        $this->start();
        unset($data['_token'], $data['numero_tarjeta'], $data['cvv']);
        $_SESSION['_old'] = $data;
    }

    /** @return array<string, mixed> */
    public function pullOldInput(): array
    {
        $this->start();
        $old = $_SESSION['_old'] ?? [];
        unset($_SESSION['_old']);

        return $old;
    }

    public function csrfToken(): string
    {
        $this->start();

        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION['_csrf'];
    }

    public function verifyCsrf(?string $token): bool
    {
        $this->start();

        return is_string($token)
            && isset($_SESSION['_csrf'])
            && hash_equals((string) $_SESSION['_csrf'], $token);
    }
}
