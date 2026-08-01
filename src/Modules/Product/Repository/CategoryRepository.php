<?php
namespace Modules\Product\Repository;

use PDO;
use Modules\Product\Repository\Contract\CategoryRepositoryInterface;

class CategoryRepository implements CategoryRepositoryInterface {
    private PDO $db;

    public function __construct() {
        $this->db = \Config\Database::getConnection();
    }

    public function findAll(): array {
        $stmt = $this->db->query("
            SELECT c.id, c.name, c.created_at, COUNT(p.id) AS product_count 
            FROM categories c 
            LEFT JOIN products p ON p.category_id = c.id 
            WHERE c.user_id = current_setting('app.current_user_id')::uuid 
            GROUP BY c.id, c.name, c.created_at 
            ORDER BY c.name
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(string $id): ?array {
        $stmt = $this->db->prepare("SELECT id, name, created_at FROM categories WHERE id = ? AND user_id = current_setting('app.current_user_id')::uuid");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findByName(string $name): ?array {
        $stmt = $this->db->prepare("SELECT id, name FROM categories WHERE LOWER(name) = LOWER(?) AND user_id = current_setting('app.current_user_id')::uuid");
        $stmt->execute([$name]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function create(string $name): array {
        $stmt = $this->db->prepare(
            "INSERT INTO categories (name, user_id) VALUES (?, current_setting('app.current_user_id')::uuid) RETURNING id, name, created_at"
        );
        $stmt->execute([$name]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function update(string $id, string $name): array {
        $stmt = $this->db->prepare(
            "UPDATE categories SET name = ? WHERE id = ? AND user_id = current_setting('app.current_user_id')::uuid RETURNING id, name, created_at"
        );
        $stmt->execute([$name, $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function countProductsUsingCategory(string $id): int {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM products WHERE category_id = ? AND user_id = current_setting('app.current_user_id')::uuid");
        $stmt->execute([$id]);
        return (int) $stmt->fetchColumn();
    }

    public function findDependentCounts(string $id): array {
        $productStmt = $this->db->prepare("SELECT COUNT(*) FROM products WHERE category_id = ? AND user_id = current_setting('app.current_user_id')::uuid");
        $productStmt->execute([$id]);
        $productCount = (int) $productStmt->fetchColumn();

        $subCatStmt = $this->db->prepare("SELECT COUNT(*) FROM subcategories WHERE category_id = ? AND user_id = current_setting('app.current_user_id')::uuid");
        $subCatStmt->execute([$id]);
        $subCatCount = (int) $subCatStmt->fetchColumn();

        return [
            'products' => $productCount,
            'subcategories' => $subCatCount,
            'inventory' => 0,
            'variants' => 0,
            'images' => 0
        ];
    }

    public function deleteProductImagesByCategory(string $id): int {
        return 0; // Handled gracefully if image table is non-existent
    }

    public function deleteInventoryByCategory(string $id): int {
        return 0; // Handled via foreign key cascade or products deletion
    }

    public function deleteProductVariantsByCategory(string $id): int {
        return 0; // Handled via products deletion
    }

    public function deleteProductsByCategory(string $id): int {
        $stmt = $this->db->prepare("DELETE FROM products WHERE category_id = ? AND user_id = current_setting('app.current_user_id')::uuid");
        $stmt->execute([$id]);
        return $stmt->rowCount();
    }

    public function delete(string $id): bool {
        $stmt = $this->db->prepare("DELETE FROM categories WHERE id = ? AND user_id = current_setting('app.current_user_id')::uuid");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public function beginTransaction(): void {
        if (!$this->db->inTransaction()) {
            $this->db->beginTransaction();
        }
    }

    public function commit(): void {
        if ($this->db->inTransaction()) {
            $this->db->commit();
        }
    }

    public function rollback(): void {
        if ($this->db->inTransaction()) {
            $this->db->rollback();
        }
    }
}