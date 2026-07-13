<?php
namespace Core\Middlewares;

use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Modules\Auth\Service\JWTService;

class AuthMiddleware
{
    // Maximum allowed token age in seconds (e.g., 86400 = 24 hours)
    private const DEFAULT_MAX_TOKEN_AGE = 86400;
    public static function authenticate(?int $maxAge = null): ?object
    {
        // ── Extract Authorization header from multiple sources ──
        // Source 1: getallheaders() with case-insensitive key lookup
        // (Apache may return 'authorization' instead of 'Authorization')
        $headers = getallheaders();
        $authHeader = '';
        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'authorization') {
                $authHeader = $value;
                break;
            }
        }

        // Source 2: $_SERVER['HTTP_AUTHORIZATION'] (more reliable in Apache)
        if (empty($authHeader) && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
        }

        // Source 3: REDIRECT_HTTP_AUTHORIZATION (passed through mod_rewrite)
        if (empty($authHeader) && !empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }

        // Fallback: accept token via query param (for window.open etc.)
        if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $queryToken = $_GET['token'] ?? '';
            if ($queryToken !== '') {
                $authHeader = 'Bearer ' . $queryToken;
                $matches = [1 => $queryToken];
            }
        }

        if (!isset($matches[1]) || empty($matches[1])) {
            self::sendError('no_token', 'No token provided');
        }

        $token = $matches[1];
        $jwtService = new JWTService();
        $decoded = null;

        try {
            $decoded = $jwtService->verifyToken($token);
        } catch (SignatureInvalidException $e) {
            self::sendError('invalid_signature', 'Invalid token signature');
        } catch (ExpiredException $e) {
            self::sendError('expired', 'Token has expired');
        } catch (BeforeValidException $e) {
            self::sendError('not_yet_valid', 'Token is not yet valid');
        } catch (\Exception $e) {
            self::sendError('invalid_token', 'Invalid token');
        }

        // Check if token has 'iat' claim
        if (!isset($decoded->iat)) {
            self::sendError('missing_iat', 'Token missing issued-at time');
        }

        $tokenAge = time() - $decoded->iat;
        $allowedAge = $maxAge ?? self::DEFAULT_MAX_TOKEN_AGE;
        if ($tokenAge > $allowedAge) {
            self::sendError('expired', 'Token exceeded maximum age');
        }

        // Optionally, you could also check that token is not from the future (allow small clock skew)
        if ($decoded->iat > time() + 10) {
            self::sendError('future_iat', 'Token issued in the future');
        }

        // Extract user data
        if (!isset($decoded->data)) {
            self::sendError('invalid_payload', 'Token payload missing user data');
        }
        $userData = (array) $decoded->data;
        $userId = $userData['user_id'] ?? null;
        if (!$userId) {
            self::sendError('invalid_payload', 'Token payload missing user_id');
        }

        // Set RLS session variable so every DB query in this request
        // is automatically scoped to this user's rows
        \Config\Database::setCurrentUser($userId);

        return $decoded;
    }

    private static function sendError(string $code, string $message): void
    {
        http_response_code(401);
        echo json_encode(['error' => $message, 'code' => $code]);
        exit;
    }

    public static function getUserId(): string
    {
        return $_SESSION['user_id'] ?? ''; // or fetch from JWT
    }

}