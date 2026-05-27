<?php
namespace Modules\Product\Repository;

use PDO;

class SubcategoryRepository {
    private PDO $db;
    public function __construct() {
        $this->db = \Config\Database::getConnection();
    }
    
    public function findByCategory(string $categoryId): array {
        $stmt = $this->db->prepare("SELECT id, name FROM subcategories WHERE category_id = ? ORDER BY name");
        $stmt->execute([$categoryId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function create(string $categoryId, string $name): array {
        $stmt = $this->db->prepare("INSERT INTO subcategories (category_id, name) VALUES (?, ?) RETURNING id, name, category_id");
        $stmt->execute([$categoryId, $name]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function findByNameInCategory(string $categoryId, string $name): ?array {
        $stmt = $this->db->prepare("SELECT id FROM subcategories WHERE category_id = ? AND name = ?");
        $stmt->execute([$categoryId, $name]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}