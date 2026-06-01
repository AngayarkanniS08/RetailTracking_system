<?php
namespace Modules\Auth\Controller\Web;

use Modules\Auth\DTO\LoginDTO;
use Modules\Auth\Service\LoginService;
use Modules\Auth\Repository\UserRepository;
use Modules\Auth\Validation\ValidationException;

class LoginController {
    private LoginService $service;

    public function __construct() {
        $userRepo = new UserRepository();
        $this->service = new LoginService($userRepo);
    }

    public function login(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirectWithError("Invalid request");
            return;
        }

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $this->redirectWithError("Username and password required");
            return;
        }

        try {
            $dto = new LoginDTO($username, $password);
            $user = $this->service->login($dto);

            session_start(); // already started, but ensure
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];

            header("Location: /index.php");
            exit;
        } catch (ValidationException $e) {
            $this->redirectWithError($e->getMessage());
        } catch (\Exception $e) {
            error_log("Web login error: " . $e->getMessage());
            $this->redirectWithError("Internal error");
        }
    }

    private function redirectWithError(string $error): void {
        header("Location: /index.php?action=login&error=" . urlencode($error));
        exit;
    }
}