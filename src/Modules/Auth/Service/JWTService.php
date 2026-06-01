<?php

namespace Modules\Auth\Service;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JWTService
{
    private string $secretKey;

    public function __construct()
    {
        // Store this secret in an environment variable or config file
        $this->secretKey = $_ENV['JWT_SECRET'] ?? $_SERVER['JWT_SECRET'] ?? 'your-secret-key-change-this';
        if (empty($this->secretKey)) {
            throw new \Exception('JWT_SECRET environment variable not set');
        }
    }

    /**
     * @throws \Exception (SignatureInvalidException, ExpiredException, etc.)
     */
    public function verifyToken(string $token): object {
        // Decode with algorithm 'HS256' – will throw on failure
        return JWT::decode($token, new Key($this->secretKey, 'HS256'));
    }

    public function generateToken(array $user): string
    {
        $issuedAt = time();
        $payload = [
            'iss' => 'retail-system',      // issuer
            'aud' => 'retail-app',         // audience
            'iat' => $issuedAt,            // issued at
            'data' => [
                'user_id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'] ?? ''
            ]
        ];
        return JWT::encode($payload, $this->secretKey, 'HS256');
    }

    }

