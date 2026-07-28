<?php
declare(strict_types=1);

namespace Core\Middleware;

use Core\Request;
use Core\Response;

class AuthMiddleware
{
    public static function handle(Request $request): void
    {
        $isLoggedIn = isset($_COOKIE['auth_uid']);
        $path = $request->getUri();
        $config = require __DIR__ . '/../../../config/auth.php';
        $guestRoutes = $config['guest_routes'] ?? [];

        if (!$isLoggedIn && !in_array($path, $guestRoutes, true)) {
            Response::redirect('/login');
        }

        if ($isLoggedIn && in_array($path, $guestRoutes, true)) {
            Response::redirect('/dashboard');
        }
    }
}
