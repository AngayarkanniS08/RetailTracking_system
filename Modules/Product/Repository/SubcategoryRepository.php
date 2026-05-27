<?php
namespace Modules\Product\Repository;

use PDO;
use Modules\Product\Repository\Contract\SubcategoryRepositoryInterface;

class SubcategoryRepository implements SubcategoryRepositoryInterface {
    private PDO $db;

    public function __construct() {
        $this->db = \Config\Database::getConnection();
    }

    public function findAll(): array {
        $stmt = $this->db->query(
            "SELECT s.id, s.name, s.category_id, c.name AS category_name, s.created_at
             FROM subcategories s
             JOIN categories c ON c.id = s.category_id
             ORDER BY c.name, s.name"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByCategory(string $categoryId): array {
        $stmt = $this->db->prepare(
            "SELECT id, name, category_id FROM subcategories WHERE category_id = ? ORDER BY name"
        );
        $stmt->execute([$categoryId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(string $id): ?array {
        $stmt = $this->db->prepare(
            "SELECT id, name, category_id, created_at FROM subcategories WHERE id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findByNameInCategory(string $categoryId, string $name): ?array {
        $stmt = $this->db->prepare(
            "SELECT id FROM subcategories WHERE category_id = ? AND LOWER(name) = LOWER(?)"
        );
        $stmt->execute([$categoryId, $name]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function create(string $categoryId, string $name): array {
        $stmt = $this->db->prepare(
            "INSERT INTO subcategories (category_id, name, user_id) VALUES (?, ?, current_setting('app.current_user_id')::uuid) RETURNING id, name, category_id, created_at"
        );
        $stmt->execute([$categoryId, $name]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function update(string $id, string $name): array {
        $stmt = $this->db->prepare(
            "UPDATE subcategories SET name = ? WHERE id = ? RETURNING id, name, category_id, created_at"
        );
        $stmt->execute([$name, $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function delete(string $id): bool {
        $stmt = $this->db->prepare("DELETE FROM subcategories WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
}