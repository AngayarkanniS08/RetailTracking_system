<?php

namespace Modules\Auth\Controller\Api;

use Modules\Auth\Service\JWTService;

class AuthController
{
    private JWTService $jwtService;

    public function __construct()
    {
        $this->jwtService = new JWTService();
    }

    public function refresh(): void
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $token = $input['token'] ?? '';

        if (empty($token)) {
            http_response_code(400);
            echo json_encode(['error' => 'Token required']);
            return;
        }

        $newToken = $this->jwtService->refreshToken($token);

        if ($newToken === null) {
            http_response_code(401);
            echo json_encode(['error' => 'Refresh failed']);
            return;
        }

        $decoded = $this->jwtService->verifyToken($newToken);
        $user = (array)$decoded->data;

        echo json_encode([
            'token' => $newToken,
            'user' => $user
        ]);
    }
}
