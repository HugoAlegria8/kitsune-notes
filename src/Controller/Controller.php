<?php

declare(strict_types=1);

namespace KitsuneNotes\Controller;

use KitsuneNotes\Core\App;
use KitsuneNotes\Core\Request;
use KitsuneNotes\Core\Response;
use KitsuneNotes\Core\Validator;

/**
 * Comportamiento común a todos los controladores.
 */
abstract class Controller
{
    public function __construct(protected readonly App $app)
    {
    }

    /** @param array<string, mixed> $data */
    protected function view(string $template, array $data = [], ?string $layout = 'layout/main', int $status = 200): Response
    {
        return Response::html($this->app->view()->render($template, $data, $layout), $status);
    }

    protected function redirect(string $path): Response
    {
        return Response::redirect($this->app->view()->url($path));
    }

    protected function back(Request $request, string $fallback = '/'): Response
    {
        $referer = $request->referer();

        if ($referer !== '') {
            return Response::redirect($referer);
        }

        return $this->redirect($fallback);
    }

    /**
     * Página 404. El mensaje llega ya traducido (los controladores lo pasan
     * con $this->t()); sin mensaje se usa uno genérico.
     */
    protected function notFound(?string $message = null): Response
    {
        return $this->view('page/error', [
            'title'   => $this->t('Página no encontrada'),
            'code'    => '404',
            'message' => $message ?? $this->t('La página que buscas no existe.'),
        ], 'layout/main', 404);
    }

    /**
     * Traduce al idioma activo un texto de interfaz escrito en español
     * (títulos, mensajes flash…). Devuelve texto sin escapar: las plantillas
     * lo escapan al imprimirlo.
     *
     * @param array<string, scalar|null> $params valores de los marcadores {clave}
     */
    protected function t(string $text, array $params = []): string
    {
        return $this->app->translator()->get($text, $params);
    }

    /**
     * Valida un formulario de la tienda con los mensajes en el idioma activo.
     *
     * @param array<string, mixed>  $data
     * @param array<string, string> $rules
     * @param array<string, string> $labels nombres de los campos, ya traducidos con $this->t()
     */
    protected function validate(array $data, array $rules, array $labels = []): Validator
    {
        return new Validator($data, $rules, $labels, $this->app->translator());
    }

    /** Comprueba el token CSRF de un formulario. */
    protected function csrfValid(Request $request): bool
    {
        return $this->app->session()->verifyCsrf($request->input('_token'));
    }

    protected function flash(string $type, string $message): void
    {
        $this->app->session()->flash($type, $message);
    }

    /** Redirige al acceso del back-office si no hay sesión de personal. */
    protected function guardStaff(): ?Response
    {
        if ($this->app->auth()->check()) {
            return null;
        }

        // Se recuerda la página pedida (solo GET) para volver a ella tras
        // iniciar sesión: por ejemplo, al abrir un correo desde un enlace.
        $request = $this->app->request();

        if ($request->method() === 'GET' && $request->path() !== '/admin/login') {
            $query = $request->queryAll() !== [] ? '?' . http_build_query($request->queryAll()) : '';
            $this->app->session()->put('admin_next', $request->path() . $query);
        }

        $this->flash('aviso', 'Inicia sesión para acceder al back-office.');

        return $this->redirect('/admin/login');
    }

    /** Correo del operador conectado, para la trazabilidad de los cambios. */
    protected function operator(): string
    {
        return (string) ($this->app->auth()->user()['email'] ?? 'back-office');
    }
}
