<?php

declare(strict_types=1);

namespace KitsuneNotes\Service;

use KitsuneNotes\Core\Session;
use KitsuneNotes\Repository\StaffRepository;

/**
 * Autenticación del personal interno (back-office).
 *
 * Las contraseñas se almacenan con password_hash() (bcrypt) y se
 * comprueban con password_verify(): en ningún momento se guardan ni se
 * comparan en claro. Los clientes de la tienda no necesitan cuenta: la
 * compra es como invitado y la consulta de pedidos se hace con la
 * referencia más el correo electrónico usado en la compra.
 */
final class AuthService
{
    private const SESSION_KEY = 'staff_user_id';

    public function __construct(
        private readonly StaffRepository $staff,
        private readonly Session $session,
    ) {
    }

    public function attempt(string $email, string $password): bool
    {
        $user = $this->staff->findByEmail($email);

        if ($user === null) {
            // Se ejecuta igualmente una verificación ficticia para que el
            // tiempo de respuesta no revele si el usuario existe.
            password_verify($password, '$2y$12$usuarioinexistenteusuarioinexistenteusuarioinexistente');

            return false;
        }

        if (!password_verify($password, (string) $user['password_hash'])) {
            return false;
        }

        // Renovar el identificador de sesión al iniciar sesión evita
        // ataques de fijación de sesión.
        $this->session->start();
        session_regenerate_id(true);
        $this->session->put(self::SESSION_KEY, (int) $user['id']);

        return true;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed>|null */
    public function user(): ?array
    {
        $id = $this->session->get(self::SESSION_KEY);

        if (!is_numeric($id)) {
            return null;
        }

        return $this->staff->findById((int) $id);
    }

    public function logout(): void
    {
        $this->session->forget(self::SESSION_KEY);
    }
}
