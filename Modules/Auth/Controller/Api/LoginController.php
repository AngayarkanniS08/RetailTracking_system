<?php
namespace Modules\Auth\Controller\Api;

use Modules\Auth\DTO\LoginDTO;
use Modules\Auth\Service\LoginService;
use Modules\Auth\Repository\UserRepository;
use Modules\Auth\validation\ValidationException;
use Exception;

class LoginController {
    private LoginService $service;

    public function __construct() {
        $userRepo = new UserRepository();
        $this->service = new LoginService($userRepo);
    }

    public function login(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirectWithError("Invalid request method");
            return;
        }

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $this->redirectWithError("Username and password are required");
            return;
        }

        try {
            $dto = new LoginDTO($username, $password);
            $user = $this->service->login($dto);

            session_start();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];

            header("Location: /index.php");
            exit;
        } catch (ValidationException $e) {
            $this->redirectWithError($e->getMessage());
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            $this->redirectWithError("An internal error occurred");
        }
    }

    private function redirectWithError(string $error): void {
        header("Location: /index.php?action=login&error=" . urlencode($error));
        exit;
    }
}