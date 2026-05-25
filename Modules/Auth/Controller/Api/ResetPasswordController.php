<?php
namespace Modules\Auth\Controller\Api;

use Modules\Auth\DTO\ResetPasswordDTO;
use Modules\Auth\Service\ResetPasswordService;
use Modules\Auth\Repository\UserRepository;
use Modules\Auth\Repository\PasswordResetRepository;
use Modules\Auth\validation\ValidationException;
use Exception;

class ResetPasswordController {
    private ResetPasswordService $service;

    public function __construct() {
        $userRepo = new UserRepository();
        $resetRepo = new PasswordResetRepository();
        $this->service = new ResetPasswordService($userRepo, $resetRepo);
    }

    public function reset(): void {
        // Only POST allowed
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        // Read JSON input
        $input = json_decode(file_get_contents('php://input'), true);
        $token = trim($input['token'] ?? '');
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';
        $passwordConfirmation = $input['password_confirmation'] ?? '';

        // Basic validation
        if (empty($token) || empty($email) || empty($password)) {
            http_response_code(400);
            echo json_encode(['error' => 'Token, email, and password are required']);
            return;
        }

        try {
            $dto = new ResetPasswordDTO($token, $email, $password, $passwordConfirmation);
            $this->service->resetPassword($dto);

            echo json_encode([
                'success' => true,
                'message' => 'Password has been reset successfully. Please login.'
            ]);
        } catch (ValidationException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }
}