<?php

declare(strict_types=1);

// CORS headers for multi-container browser access
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (!empty($origin)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    header("Access-Control-Allow-Origin: http://localhost:8080");
}
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    require_once __DIR__ . '/src/vendor/autoload.php';
}

use Core\Bootstrap;
use Core\Router;
use Core\ApiRoutes;

Bootstrap::init();

$router = new Router();
ApiRoutes::register($router);

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (str_starts_with($path, '/api/')) {
    $router->dispatch($method, $path);
    exit;
}

// All other requests direct to the API server return a 404
http_response_code(404);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['error' => 'API endpoint not found']);
