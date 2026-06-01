<?php

namespace Core;

class Router
{
    private array $routes = [];

    public function add(string $method, string $path, callable $handler): void
    {
        $method = strtoupper($method);
        $path = rtrim($path, '/') ?: '/';
        $this->routes[$method][] = ['path' => $path, 'handler' => $handler];
    }

    public function dispatch(string $method, string $uri): void
    {
        $method = strtoupper($method);
        $uri = parse_url($uri, PHP_URL_PATH) ?: '/';
        $uri = rtrim($uri, '/') ?: '/';

        foreach ($this->routes[$method] ?? [] as $route) {
            $params = $this->match($route['path'], $uri);
            if ($params !== false) {
                if ($params === []) {
                    call_user_func($route['handler']);
                } else {
                    call_user_func($route['handler'], $params);
                }
                return;
            }
        }

        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'API endpoint not found']);
    }

    private function match(string $route, string $uri): false|array
    {
        if ($route === $uri) {
            return [];
        }

        $parameterNames = [];
        $regex = preg_replace_callback('#\{([^/]+)\}#', function ($matches) use (&$parameterNames) {
            $parameterNames[] = $matches[1];
            return '([^/]+)';
        }, $route);

        $regex = '#^' . $regex . '$#';
        if (!preg_match($regex, $uri, $matches)) {
            return false;
        }

        array_shift($matches);
        return array_combine($parameterNames, $matches) ?: [];
    }
}
