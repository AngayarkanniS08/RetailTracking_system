<?php
namespace Core\Middleware;

use Modules\Auth\Service\JWTService;

class AuthMiddleware {
    public static function authenticate(): ?object {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';
        if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            http_response_code(401);
            echo json_encode(['error' => 'No token provided']);
            exit;
        }

        $jwtService = new JWTService();
        $decoded = $jwtService->verifyToken($matches[1]);
        if (!$decoded) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid or expired token']);
            exit;
        }
        return $decoded->data;
    }
}