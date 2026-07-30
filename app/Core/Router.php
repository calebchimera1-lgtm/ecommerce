<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Minimal, dependency-free HTTP router with named parameters and
 * per-route middleware stacks.
 */
final class Router
{
    /** @var array<string, array<int, array{pattern:string,keys:array,handler:mixed,middleware:array}>> */
    private array $routes = [
        'GET' => [],
        'POST' => [],
        'PUT' => [],
        'PATCH' => [],
        'DELETE' => [],
    ];

    public function get(string $path, mixed $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, mixed $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    public function put(string $path, mixed $handler, array $middleware = []): void
    {
        $this->add('PUT', $path, $handler, $middleware);
    }

    public function patch(string $path, mixed $handler, array $middleware = []): void
    {
        $this->add('PATCH', $path, $handler, $middleware);
    }

    public function delete(string $path, mixed $handler, array $middleware = []): void
    {
        $this->add('DELETE', $path, $handler, $middleware);
    }

    private function add(string $method, string $path, mixed $handler, array $middleware): void
    {
        $keys = [];
        $pattern = preg_replace_callback('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', function (array $m) use (&$keys): string {
            $keys[] = $m[1];
            return '([^/]+)';
        }, rtrim($path, '/') ?: '/');

        $this->routes[$method][] = [
            'pattern' => '#^' . ($pattern === '' ? '/' : $pattern) . '$#',
            'keys' => $keys,
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(Request $request): void
    {
        $method = $request->method();
        $uri = rtrim($request->uri(), '/') ?: '/';

        foreach ($this->routes[$method] ?? [] as $route) {
            if (preg_match($route['pattern'], $uri, $matches)) {
                array_shift($matches);
                $params = array_combine($route['keys'], $matches) ?: [];
                $request = new Request($params);

                foreach ($route['middleware'] as $middlewareClass) {
                    /** @var MiddlewareInterface $middleware */
                    $middleware = new $middlewareClass();
                    if (!$middleware->handle($request)) {
                        return;
                    }
                }

                $this->callHandler($route['handler'], $request);
                return;
            }
        }

        Response::abort(404, 'Page not found');
    }

    private function callHandler(mixed $handler, Request $request): void
    {
        if (is_array($handler) && count($handler) === 2) {
            [$controllerClass, $action] = $handler;
            $controller = new $controllerClass();
            $controller->{$action}($request);
            return;
        }

        if (is_callable($handler)) {
            $handler($request);
            return;
        }

        Response::abort(500, 'Invalid route handler');
    }
}
