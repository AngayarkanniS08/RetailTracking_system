<?php
declare(strict_types=1);

namespace Controllers;

use Core\Request;
use Core\Response;
use Core\View;

class AuthController
{
    public function showLogin(Request $request): void
    {
        View::render('auth/login', [
            'pageTitle'    => 'Login',
            'currentRoute' => '/login',
        ], 'layouts/auth_layout');
    }

    public function showRegister(Request $request): void
    {
        View::render('auth/register', [
            'pageTitle'    => 'Register',
            'currentRoute' => '/register',
        ], 'layouts/auth_layout');
    }

    public function showForgotPassword(Request $request): void
    {
        View::render('auth/ForgotPassword', [
            'pageTitle'    => 'Forgot Password',
            'currentRoute' => '/forgot-password',
        ], 'layouts/auth_layout');
    }

    public function showResetPassword(Request $request): void
    {
        View::render('auth/ResetPassword', [
            'pageTitle'    => 'Reset Password',
            'currentRoute' => '/reset-password',
        ], 'layouts/auth_layout');
    }

    public function logout(Request $request): void
    {
        // Expire HttpOnly cookies server-side.
        // Must match the same path/httponly flags used when they were set.
        $expired = [
            'expires'  => time() - 3600,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        setcookie('auth_token', '', $expired);
        setcookie('auth_uid',   '', $expired);

        // Respond 200 for fetch() calls from JS, then redirect if browser navigates directly
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            return;
        }

        Response::redirect('/login');
    }
}
