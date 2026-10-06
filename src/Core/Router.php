<?php

declare(strict_types=1);

namespace KitsuneNotes\Core;

/**
 * Enrutador mínimo basado en expresiones regulares.
 *
 * Las rutas se declaran con marcadores del tipo {slug}; los valores
 * capturados se pasan al controlador como argumentos con nombre.
 */
final class Router
{
    /** @var list<array{method:string, regex:string, params:list<string>, handler:callable|array}> */
    private array $routes = [];

    /** @var callable|null */
    private $notFoundHandler = null;

    public function get(string $pattern, array|callable $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, array|callable $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function notFound(callable $handler): void
    {
        $this->notFoundHandler = $handler;
    }

    private function add(string $method, string $pattern, array|callable $handler): void
    {
        $params = [];

        $regex = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            static function (array $m) use (&$params): string {
                $params[] = $m[1];

                return '([^/]+)';
            },
            $pattern
        );

        $this->routes[] = [
            'method'  => $method,
            'regex'   => '#^' . $regex . '$#u',
            'params'  => $params,
            'handler' => $handler,
        ];
    }

    public function dispatch(Request $request, App $app): Response
    {
        $path          = $request->path();
        $methodMatched = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }

            if ($route['method'] !== $request->method()) {
                $methodMatched = true;
                continue;
            }

            array_shift($matches);
            $args = [];

            foreach ($route['params'] as $index => $name) {
                $args[$name] = urldecode($matches[$index] ?? '');
            }

            return $this->invoke($route['handler'], $request, $app, $args);
        }

        if ($methodMatched) {
            return Response::html('<h1>405 · Método no permitido</h1>', 405);
        }

        if ($this->notFoundHandler !== null) {
            return ($this->notFoundHandler)($request, $app);
        }

        return Response::html('<h1>404 · Página no encontrada</h1>', 404);
    }

    /** @param array<string, string> $args */
    private function invoke(array|callable $handler, Request $request, App $app, array $args): Response
    {
        if (is_array($handler)) {
            [$class, $method] = $handler;
            $controller = new $class($app);

            return $controller->{$method}($request, $args);
        }

        return $handler($request, $app, $args);
    }
}
