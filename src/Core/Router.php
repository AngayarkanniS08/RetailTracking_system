<?php
declare(strict_types=1);

namespace Core;

class Router
{
    private array $routes = [];

    public function get(string $path, array|callable $handler, array $middlewares = []): void
    {
        $this->addRoute('GET', $path, $handler, $middlewares);
    }

    public function post(string $path, array|callable $handler, array $middlewares = []): void
    {
        $this->addRoute('POST', $path, $handler, $middlewares);
    }

    private function addRoute(string $method, string $path, array|callable $handler, array $middlewares): void
    {
        $this->routes[] = [
            'method'      => $method,
            'path'        => rtrim($path, '/') ?: '/',
            'handler'     => $handler,
            'middlewares' => $middlewares,
        ];
    }

    public function dispatch(Request $request): void
    {
        $method = $request->getMethod();
        $uri = $request->getUri();

        // Handle favicon requests gracefully
        if ($uri === '/favicon.ico') {
            http_response_code(204);
            exit;
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) continue;

            if ($route['path'] === $uri) {
                // Execute Middlewares
                foreach ($route['middlewares'] as $middleware) {
                    call_user_func([$middleware, 'handle'], $request);
                }

                $handler = $route['handler'];

                if (is_callable($handler)) {
                    call_user_func($handler, $request);
                    return;
                }

                [$controllerClass, $action] = $handler;

                // Dependency Injection resolution via Container
                $container = Container::getInstance();
                $controller = $container->make($controllerClass);

                call_user_func([$controller, $action], $request);
                return;
            }
        }

        // 404 Route Not Found
        throw new \Exception("Route [{$uri}] not found", 404);
    }
}
