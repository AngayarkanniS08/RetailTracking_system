<?php
namespace Modules\Auth\Controller\Api;

use Modules\Auth\DTO\registerDTO;
use Modules\Auth\Service\RegistrationService;
use Modules\Auth\Repository\UserRepository;
use Validation\ValidationException;
use Exception;

class RegistrationController {
    private RegistrationService $service;
    
    public function __construct() {
        $userRepo = new UserRepository();
        $this->service = new RegistrationService($userRepo);
    }
    
    public function register(): void {
        // Only POST allowed
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirectWithError("Invalid request method");
            return;
        }
        
        // Extract data
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        try {
            $dto = new RegisterDTO($username, $email, $password);
            $user = $this->service->register($dto);
            
            // Auto-login
            session_start();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            
            header("Location: /index.php");
            exit;
        } catch (ValidationException $e) {
            // Known validation error – show to user
            $this->redirectWithError($e->getMessage());
        } catch (Exception $e) {
            // Unexpected error – log internally, show generic message
            error_log("Registration error: " . $e->getMessage());
            $this->redirectWithError("An internal error occurred. Please try again later.");
        }
    }
    
    private function redirectWithError(string $error): void {
        header("Location: /index.php?action=register&error=" . urlencode($error));
        exit;
    }
}