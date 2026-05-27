<?php
namespace Modules\Product\Repository;

use PDO;

class CategoryRepository {
    private PDO $db;
    public function __construct() {
        $this->db = \Config\Database::getConnection();
    }
    
    public function findAll(): array {
        $stmt = $this->db->query("SELECT id, name, created_at FROM categories ORDER BY name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function findByName(string $name): ?array {
        $stmt = $this->db->prepare("SELECT id FROM categories WHERE name = ?");
        $stmt->execute([$name]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    public function create(string $name): array {
        $stmt = $this->db->prepare("INSERT INTO categories (name) VALUES (?) RETURNING id, name, created_at");
        $stmt->execute([$name]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}