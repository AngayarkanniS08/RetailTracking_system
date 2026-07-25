<?php

namespace Modules\Auth\Service;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JWTService
{
    private string $secretKey;

    public function __construct()
    {
        $this->secretKey = $_ENV['JWT_SECRET'] ?? $_SERVER['JWT_SECRET'] ?? 'your-secret-key-change-this-to-something-longer-32-chars';
        if (empty($this->secretKey)) {
            throw new \Exception('JWT_SECRET environment variable not set');
        }
    }

    /**
     * @throws \Exception (SignatureInvalidException, ExpiredException, etc.)
     */
    public function verifyToken(string $token): object {
        return JWT::decode($token, new Key($this->secretKey, 'HS256'));
    }

    public function generateToken(array $user, ?int $sessionIat = null): string
    {
        $issuedAt = time();
        $sessionIat = $sessionIat ?? $issuedAt;
        $payload = [
            'iss' => 'retail-system',
            'aud' => 'retail-app',
            'iat' => $issuedAt,
            'session_iat' => $sessionIat,
            'data' => [
                'user_id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'] ?? ''
            ]
        ];
        return JWT::encode($payload, $this->secretKey, 'HS256');
    }

    /**
     * Refresh an expired-but-valid JWT within the 30-day session window.
     * Returns a new token string, or null if the token is invalid or the session has expired.
     */
    public function refreshToken(string $token): ?string
    {
        try {
            $decoded = $this->verifyToken($token);
        } catch (\Exception $e) {
            return null;
        }

        if (!isset($decoded->data)) {
            return null;
        }

        $sessionIat = isset($decoded->session_iat)
            ? (int)$decoded->session_iat
            : (isset($decoded->iat) ? (int)$decoded->iat : null);

        if ($sessionIat === null) {
            return null;
        }

        $sessionAge = time() - $sessionIat;
        if ($sessionAge > 2592000 || $sessionAge < 0) {
            return null;
        }

        return $this->generateToken(
            (array)$decoded->data,
            $sessionIat
        );
    }

}

