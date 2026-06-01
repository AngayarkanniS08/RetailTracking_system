<?php

namespace Modules\Auth\Repository;

use Modules\Auth\Repository\Contract\UserRepositoryInterface;
use Config\Database; // adjust namespace to your actual Database class
use Override;

class UserRepository implements UserRepositoryInterface
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = \Config\Database::getConnection();
    }

    public function findByUsername(string $username): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    #[Override]
    public function findById(string $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function updatePassword(string $userId, string $hashedPassword): void {
    $stmt = $this->db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $stmt->execute([$hashedPassword, $userId]);
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function findByUsernameOrEmail(string $usernameOrEmail): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$usernameOrEmail, $usernameOrEmail]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function save(string $username, string $email, string $hashedPassword): array
    {
        $stmt = $this->db->prepare("
            INSERT INTO users (username, email, password_hash) 
            VALUES (?, ?, ?) 
            RETURNING id, username, email, created_at
        ");
        $stmt->execute([$username, $email, $hashedPassword]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
}
