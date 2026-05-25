<?php

namespace Modules\Auth\Controller\Api;

use Modules\Auth\DTO\LoginDTO;
use Modules\Auth\Service\LoginService;
use Modules\Auth\Service\JWTService;
use Modules\Auth\Repository\UserRepository;
use Modules\Auth\Validation\ValidationException;
use Exception;

class LoginController
{
    private LoginService $service;
    private JWTService $jwtService;

    public function __construct()
    {
        $userRepo = new UserRepository();
        $this->service = new LoginService($userRepo);
        $this->jwtService = new JWTService();
    }

    public function login(): void
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $username = trim($input['username'] ?? '');
        $password = $input['password'] ?? '';

        if (empty($username) || empty($password)) {
            http_response_code(400);
            echo json_encode(['error' => 'Username and password required']);
            return;
        }

        try {
            $dto = new LoginDTO($username, $password);
            $user = $this->service->login($dto);
            $jwt = $this->jwtService->generateToken($user);

            echo json_encode([
                'success' => true,
                'token' => $jwt,
                'user' => $user
            ]);
        } catch (ValidationException $e) {
            http_response_code(401);
            echo json_encode(['error' => $e->getMessage()]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }
}
