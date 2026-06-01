<?php
namespace Modules\Auth\Controller\Web;

class ResetPasswordController {
    public function showResetForm(): void {
        $token = $_GET['token'] ?? '';
        if (empty($token)) {
            header("Location: index.php?action=forgot_password&error=Missing token");
            exit;
        }
        require_once 'views/auth/ResetPassword.php'; // your HTML page
    }
}