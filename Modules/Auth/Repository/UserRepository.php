<?php

namespace Modules\Auth\Repository;

use Modules\Auth\Repository\Contract\UserRepositoryInterface;
use Config\Database; // adjust namespace to your actual Database class

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

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
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
