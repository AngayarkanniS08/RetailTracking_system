<?php
declare(strict_types=1);

namespace Core\Middleware;

use Core\Request;
use Core\Response;
use Modules\Auth\Service\JWTService;

class AuthMiddleware
{
    public static function handle(Request $request): void
    {
        $path = $request->getUri();
        $config = require __DIR__ . '/../../../config/auth.php';
        $guestRoutes = $config['guest_routes'] ?? [];

        // 1. Extract token from cookie or Authorization header
        $token = $_COOKIE['auth_token'] ?? '';
        if (empty($token)) {
            $headers = getallheaders();
            foreach ($headers as $key => $val) {
                if (strtolower($key) === 'authorization' && preg_match('/Bearer\s(\S+)/', $val, $m)) {
                    $token = $m[1];
                    break;
                }
            }
        }

        $isLoggedIn = false;

        // 2. Validate token using JWTService
        if (!empty($token)) {
            try {
                $jwtService = new JWTService();
                $decoded = $jwtService->verifyToken($token);
                if (isset($decoded->data->user_id)) {
                    $isLoggedIn = true;
                }
            } catch (\Exception $e) {
                // Token invalid or expired — purge auth cookies
                self::purgeAuthCookies();
                $isLoggedIn = false;
            }
        } else {
            // Token absent — purge any lingering auth_uid cookie
            if (isset($_COOKIE['auth_uid'])) {
                self::purgeAuthCookies();
            }
        }

        // 3. Deterministic Route Enforcement
        if (!$isLoggedIn && !in_array($path, $guestRoutes, true)) {
            Response::redirect('/login');
        }

        if ($isLoggedIn && in_array($path, $guestRoutes, true)) {
            Response::redirect('/dashboard');
        }
    }

    private static function purgeAuthCookies(): void
    {
        $expired = [
            'expires'  => time() - 3600,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        setcookie('auth_token', '', $expired);
        setcookie('auth_uid', '', $expired);
        unset($_COOKIE['auth_token'], $_COOKIE['auth_uid']);
    }
}
