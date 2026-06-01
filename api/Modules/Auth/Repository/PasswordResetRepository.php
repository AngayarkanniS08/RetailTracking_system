<?php
namespace Modules\Auth\Repository;

use Modules\Auth\Repository\Contract\PasswordResetRepositoryInterface;
use PDO;

class PasswordResetRepository implements PasswordResetRepositoryInterface {
    private PDO $db;
    public function __construct() {
        $this->db = \Config\Database::getConnection();
    }
    
    public function create(string $userId, string $token, \DateTimeInterface $expiresAt): void {
        // Use DATE_ATOM (ISO 8601 with offset) so PostgreSQL TIMESTAMPTZ gets the correct UTC time
        $stmt = $this->db->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $token, $expiresAt->format(DATE_ATOM)]);
    }
    
    public function findByToken(string $token): ?array {
        $stmt = $this->db->prepare("SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW()");
        $stmt->execute([$token]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    public function deleteByToken(string $token): void {
        $stmt = $this->db->prepare("DELETE FROM password_resets WHERE token = ?");
        $stmt->execute([$token]);
    }
    
    public function deleteByUserId(string $userId): void {
        $stmt = $this->db->prepare("DELETE FROM password_resets WHERE user_id = ?");
        $stmt->execute([$userId]);
    }
}