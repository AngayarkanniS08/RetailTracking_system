<?php
declare(strict_types=1);

namespace Core;

class Request
{
    private string $uri;
    private string $method;
    private array $query;
    private array $body;
    private array $headers;

    public function __construct()
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $this->uri = rtrim($path, '/') ?: '/';
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->query = $_GET;
        $this->body = $_POST;
        $this->headers = getallheaders() ?: [];
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function get(string $key, $default = null)
    {
        return $this->query[$key] ?? $default;
    }

    public function post(string $key, $default = null)
    {
        return $this->body[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    public function isJson(): bool
    {
        $contentType = $this->headers['Content-Type'] ?? '';
        return str_contains($contentType, 'application/json');
    }

    public function getJson(): array
    {
        $raw = file_get_contents('php://input');
        return json_decode($raw, true) ?? [];
    }
}
