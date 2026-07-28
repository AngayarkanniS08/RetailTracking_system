<?php
declare(strict_types=1);

namespace Core;

class Router
{
    private array $routes = [];

    public function add(string $method, string $path, array|callable $handler, array $middlewares = []): void
    {
        $this->addRoute(strtoupper($method), $path, $handler, $middlewares);
    }

    public function get(string $path, array|callable $handler, array $middlewares = []): void
    {
        $this->addRoute('GET', $path, $handler, $middlewares);
    }

    public function post(string $path, array|callable $handler, array $middlewares = []): void
    {
        $this->addRoute('POST', $path, $handler, $middlewares);
    }

    public function put(string $path, array|callable $handler, array $middlewares = []): void
    {
        $this->addRoute('PUT', $path, $handler, $middlewares);
    }

    public function delete(string $path, array|callable $handler, array $middlewares = []): void
    {
        $this->addRoute('DELETE', $path, $handler, $middlewares);
    }

    public function patch(string $path, array|callable $handler, array $middlewares = []): void
    {
        $this->addRoute('PATCH', $path, $handler, $middlewares);
    }

    private function addRoute(string $method, string $path, array|callable $handler, array $middlewares): void
    {
        // Convert parameterized routes like /api/categories/{id} to regex
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<\1>[^/]+)', $path);
        $regex = '#^' . rtrim($pattern, '/') . '/?$#';

        $this->routes[] = [
            'method'      => $method,
            'path'        => rtrim($path, '/') ?: '/',
            'regex'       => $regex,
            'handler'     => $handler,
            'middlewares' => $middlewares,
        ];
    }

    public function dispatch($methodOrRequest = null, ?string $pathOverride = null): void
    {
        if ($methodOrRequest instanceof Request) {
            $method = $methodOrRequest->getMethod();
            $uri    = $methodOrRequest->getUri();
            $requestObj = $methodOrRequest;
        } else {
            $method = is_string($methodOrRequest) ? strtoupper($methodOrRequest) : strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
            $path   = $pathOverride ?? parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
            $uri    = rtrim($path, '/') ?: '/';
            $requestObj = new Request();
        }

        // Handle favicon requests gracefully
        if ($uri === '/favicon.ico') {
            http_response_code(204);
            exit;
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) continue;

            $params = [];
            if ($route['path'] === $uri || preg_match($route['regex'], $uri, $matches)) {
                if (isset($matches)) {
                    foreach ($matches as $key => $value) {
                        if (is_string($key)) {
                            $params[$key] = $value;
                        }
                    }
                }

                // Execute Middlewares
                foreach ($route['middlewares'] as $middleware) {
                    if (is_callable($middleware)) {
                        call_user_func($middleware, $requestObj);
                    } else {
                        call_user_func([$middleware, 'handle'], $requestObj);
                    }
                }

                $handler = $route['handler'];

                if (is_callable($handler)) {
                    call_user_func($handler, $params);
                    return;
                }

                [$controllerClass, $action] = $handler;

                // Dependency Injection resolution via Container
                $container = Container::getInstance();
                $controller = $container->make($controllerClass);

                call_user_func([$controller, $action], $requestObj, $params);
                return;
            }
        }

        // 404 Route Not Found
        http_response_code(404);
        if (str_starts_with($uri, '/api/')) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => "API Route [{$uri}] not found"]);
            exit;
        }

        throw new \Exception("Route [{$uri}] not found", 404);
    }
}
