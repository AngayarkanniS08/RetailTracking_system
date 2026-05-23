<?php
namespace Auth\Controller\Api;

use Auth\Repository\UserRepository;
use Exception;

class LoginController {
    private UserRepository $userRepo;
    
    public function __construct() {
        $this->userRepo = new UserRepository();
    }
    
    public function login(): void {
        // Only POST allowed
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirectWithError("Invalid request method");
            return;
        }
        
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            $this->redirectWithError("All fields are required");
            return;
        }
        
        try {
            $user = $this->userRepo->findByUsername($username);
            
            // Fallback: search by email if username not found
            if (!$user && filter_var($username, FILTER_VALIDATE_EMAIL)) {
                $user = $this->userRepo->findByEmail($username);
            }
            
            if ($user && password_verify($password, $user['password_hash'])) {
                // Login successful
                session_start();
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                
                header("Location: index.php");
                exit;
            } else {
                $this->redirectWithError("Invalid username or password");
            }
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            $this->redirectWithError("An internal error occurred. Please try again later.");
        }
    }
    
    private function redirectWithError(string $error): void {
        header("Location: index.php?action=login&error=" . urlencode($error));
        exit;
    }
}
