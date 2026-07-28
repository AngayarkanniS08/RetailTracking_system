<?php
declare(strict_types=1);

namespace Core;

class Router
{
    private array $routes = [];

    public function get(string $path, array $handler, array $middlewares = []): void
    {
        $this->addRoute('GET', $path, $handler, $middlewares);
    }

    public function post(string $path, array $handler, array $middlewares = []): void
    {
        $this->addRoute('POST', $path, $handler, $middlewares);
    }

    private function addRoute(string $method, string $path, array $handler, array $middlewares): void
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

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) continue;

            if ($route['path'] === $uri) {
                // Execute Middlewares
                foreach ($route['middlewares'] as $middleware) {
                    call_user_func([$middleware, 'handle'], $request);
                }

                [$controllerClass, $action] = $route['handler'];

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
