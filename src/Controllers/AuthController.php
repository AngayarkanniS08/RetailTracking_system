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
        setcookie('auth_uid', '', time() - 3600, '/');
        Response::redirect('/login');
    }
}
