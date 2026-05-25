<?php
namespace Modules\Auth\Service;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JWTService {
    private string $secretKey;
    private int $expirySeconds;

    public function __construct() {
        // Store this secret in an environment variable or config file
        $this->secretKey = 'your-secret-key-change-this-to-random-string';
        $this->expirySeconds = 3600 * 24; // 24 hours
    }

    public function generateToken(array $user): string {
        $issuedAt = time();
        $payload = [
            'iss' => 'retail-system',      // issuer
            'aud' => 'retail-app',         // audience
            'iat' => $issuedAt,            // issued at
            'exp' => $issuedAt + $this->expirySeconds,
            'data' => [
                'user_id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'] ?? ''
            ]
        ];
        return JWT::encode($payload, $this->secretKey, 'HS256');
    }

    public function verifyToken(string $token): ?object {
        try {
            $decoded = JWT::decode($token, new Key($this->secretKey, 'HS256'));
            return $decoded;
        } catch (\Exception $e) {
            return null;
        }
    }
}