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
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';

        if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            http_response_code(401);
            echo json_encode(['error' => 'No token provided']);
            exit;
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

        // Set RLS session variable so every DB query in this request
        // is automatically scoped to this user's rows
        // Set the PostgreSQL session variable for RLS
        $pdo = \Config\Database::getConnection();
        $pdo->prepare("SET app.current_user_id = ?")->execute([$decoded->user_id]);

        return $decoded;
    }

    private static function sendError(string $code, string $message): void
    {
        http_response_code(401);
        echo json_encode(['error' => $message, 'code' => $code]);
        exit;
    }

}