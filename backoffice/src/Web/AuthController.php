<?php

declare(strict_types=1);

namespace KitsuneGestion\Web;

use KitsuneGestion\Core\Request;
use KitsuneGestion\Core\Response;

/** Acceso del personal al back-office (usuarios propios, distintos de los de la tienda). */
final class AuthController extends WebController
{
    public function formulario(Request $request, array $args): Response
    {
        if ($this->app->session()->user() !== null) {
            return Response::redirect($this->app->url('/'));
        }

        return $this->vista('login', ['titulo' => 'Acceso del equipo', 'email' => '']);
    }

    public function entrar(Request $request, array $args): Response
    {
        $email = strtolower((string) $request->input('email', ''));

        if (!$this->csrfValido($request)) {
            return $this->volver('/login', 'error', 'La sesión ha caducado. Vuelve a intentarlo.');
        }

        $usuario = $this->app->db()->one('SELECT * FROM usuarios WHERE email = ?', [$email]);

        if ($usuario === null || !password_verify((string) $request->input('password', ''), (string) $usuario['password_hash'])) {
            usleep(300_000); // frena los intentos por fuerza bruta

            return $this->vista('login', ['titulo' => 'Acceso del equipo', 'email' => $email, 'error' => 'Correo o contraseña incorrectos.'], 401);
        }

        $this->app->session()->regenerate();
        $this->app->session()->put('_usuario', ['id' => (int) $usuario['id'], 'email' => $usuario['email'], 'nombre' => $usuario['nombre']]);

        return $this->volver('/', 'exito', 'Hola, ' . $usuario['nombre'] . '.');
    }

    public function salir(Request $request, array $args): Response
    {
        if ($this->csrfValido($request)) {
            $this->app->session()->destroy();
        }

        return Response::redirect($this->app->url('/login'));
    }
}
