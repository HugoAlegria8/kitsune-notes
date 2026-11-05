<?php

declare(strict_types=1);

namespace KitsuneGestion\Web;

use KitsuneGestion\Core\App;
use KitsuneGestion\Core\Request;
use KitsuneGestion\Core\Response;

/** Base de las pantallas del personal: sesión obligatoria, CSRF y avisos. */
abstract class WebController
{
    public function __construct(protected readonly App $app)
    {
    }

    protected function exigirSesion(): ?Response
    {
        return $this->app->session()->user() === null ? Response::redirect($this->app->url('/login')) : null;
    }

    protected function usuario(): string
    {
        return (string) ($this->app->session()->user()['email'] ?? 'desconocido');
    }

    protected function csrfValido(Request $request): bool
    {
        return $this->app->session()->csrfValid($request->input('_csrf'));
    }

    /** @param array<string, mixed> $datos */
    protected function vista(string $plantilla, array $datos = [], int $codigo = 200): Response
    {
        $pendientes = $this->app->session()->user() !== null
            ? (int) $this->app->db()->value("SELECT COUNT(*) FROM propuestas WHERE estado = 'pendiente_revision'")
            : 0;

        return Response::html($this->app->view()->render($plantilla, [
            ...$datos,
            'app'                  => $this->app,
            'usuario'              => $this->app->session()->user(),
            'avisos'               => $this->app->session()->pullFlash(),
            'propuestasPendientes' => $pendientes,
        ]), $codigo);
    }

    protected function volver(string $ruta, string $tipo, string $mensaje): Response
    {
        $this->app->session()->flash($tipo, $mensaje);

        return Response::redirect($this->app->url($ruta));
    }

    /**
     * Texto para el aviso tras intentar sincronizar con n8n.
     *
     * @param array<string, mixed> $fila
     */
    protected function resumenSincronizacion(array $fila, string $queSincroniza): array
    {
        return match ($fila['estado'] ?? '') {
            'confirmada' => ['exito', $queSincroniza . ': confirmado por n8n.'],
            'conflicto'  => ['error', $queSincroniza . ': rechazado por el destino (' . ($fila['ultimo_error'] ?? '') . '). Revísalo en Integración.'],
            default      => ['aviso', $queSincroniza . ': queda pendiente de sincronización y se reintentará solo (' . ($fila['ultimo_error'] ?? 'sin respuesta') . ').'],
        };
    }
}
