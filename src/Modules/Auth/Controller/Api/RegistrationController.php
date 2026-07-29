<?php
namespace Modules\Auth\Controller\Api;

use Modules\Auth\DTO\RegisterDTO;
use Modules\Auth\Service\RegistrationService;
use Modules\Auth\Repository\UserRepository;
use Modules\Auth\Validation\ValidationException;
use Exception;

class RegistrationController
{
    private RegistrationService $service;

    public function __construct()
    {
        $userRepo      = new UserRepository();
        $this->service = new RegistrationService($userRepo);
    }

    public function register(): void
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        // Accept JSON body (from the static frontend fetch call)
        $input    = json_decode(file_get_contents('php://input'), true) ?? [];
        $username = trim($input['username'] ?? '');
        $email    = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';

        if (empty($username) || empty($email) || empty($password)) {
            http_response_code(400);
            echo json_encode(['error' => 'Username, email, and password are required']);
            return;
        }

        try {
            $dto  = new RegisterDTO($username, $email, $password);
            $user = $this->service->register($dto);

            http_response_code(201);
            echo json_encode([
                'success' => true,
                'user'    => [
                    'id'       => $user['id'],
                    'username' => $user['username'],
                    'email'    => $user['email'] ?? $email,
                ],
            ]);
        } catch (ValidationException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        } catch (Exception $e) {
            error_log('Registration error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'An internal error occurred. Please try again later.']);
        }
    }
}