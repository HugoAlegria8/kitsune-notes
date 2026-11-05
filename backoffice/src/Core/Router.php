<?php

declare(strict_types=1);

namespace KitsuneGestion\Core;

/**
 * Enrutador por expresiones regulares. Las rutas están todas en src/routes.php.
 * Un parámetro {nombre} admite cualquier valor sin barras.
 */
final class Router
{
    /** @var list<array{0:string, 1:string, 2:array{0:class-string, 1:string}}> */
    private array $routes = [];

    public function __construct(private readonly App $app)
    {
    }

    /** @param array{0:class-string, 1:string} $handler */
    public function get(string $pattern, array $handler): void
    {
        $this->routes[] = ['GET', $pattern, $handler];
    }

    /** @param array{0:class-string, 1:string} $handler */
    public function post(string $pattern, array $handler): void
    {
        $this->routes[] = ['POST', $pattern, $handler];
    }

    /** @param array{0:class-string, 1:string} $handler */
    public function put(string $pattern, array $handler): void
    {
        $this->routes[] = ['PUT', $pattern, $handler];
    }

    /** Devuelve null si ninguna ruta coincide con la dirección. */
    public function dispatch(Request $request): ?Response
    {
        $methodMismatch = false;

        foreach ($this->routes as [$method, $pattern, $handler]) {
            $regex = '#^' . preg_replace('#\{([a-z_]+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';

            if (preg_match($regex, $request->path, $matches) !== 1) {
                continue;
            }

            $requestMethod = $request->method === 'HEAD' ? 'GET' : $request->method;

            if ($method !== $requestMethod) {
                $methodMismatch = true;
                continue;
            }

            $args = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            [$class, $action] = $handler;

            return (new $class($this->app))->$action($request, $args);
        }

        if ($methodMismatch) {
            return $request->isApi()
                ? Response::json(['ok' => false, 'error' => 'metodo_no_permitido'], 405)
                : Response::html('Método no permitido', 405);
        }

        return null;
    }
}
