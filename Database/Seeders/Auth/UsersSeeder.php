<?php
declare(strict_types=1);

namespace Database\Seeders\Auth;

use Database\Seeders\BaseSeeder;

/**
 * Seeds the test user account for development and testing environments.
 *
 * Uses INSERT ... ON CONFLICT DO NOTHING (idempotent — safe to re-run).
 * Credentials are fixed for local development only — never use in production.
 *
 * Test credentials:
 *   Username: testuser
 *   Password: password123
 */
class UsersSeeder extends BaseSeeder
{
    public function module(): string
    {
        return 'Auth';
    }

    public function environments(): array
    {
        return ['development', 'testing'];
    }

    protected function seed(): void
    {
        $userId        = 'e165e33e-0b13-4db9-93bb-79858a78a74a';
        $username      = 'testuser';
        $email         = 'testuser@example.com';
        $password      = 'password123';
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $fullName      = 'Test User';

        $stmt = $this->pdo->prepare("
            INSERT INTO users (id, username, email, password_hash, full_name)
            VALUES (:id, :username, :email, :password_hash, :full_name)
            ON CONFLICT (id) DO NOTHING
        ");

        $stmt->execute([
            ':id'            => $userId,
            ':username'      => $username,
            ':email'         => $email,
            ':password_hash' => $hashedPassword,
            ':full_name'     => $fullName,
        ]);

        echo "  ✅ Seeded user: {$username} (Password: {$password})\n";
    }
}
