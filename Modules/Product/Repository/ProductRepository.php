<?php
namespace Modules\Product\Repository;

use PDO;
use Modules\Product\Repository\Contract\ProductRepositoryInterface;

class ProductRepository implements ProductRepositoryInterface {
    private PDO $db;

    public function __construct() {
        $this->db = \Config\Database::getConnection();
    }

    public function findAll(): array {
        $sql = "SELECT p.id, p.name, p.category_id, c.name AS category_name,
                       p.subcategory_id, s.name AS subcategory_name,
                       p.unit, p.hsn_code, p.gst_rate, p.created_at
                FROM   products p
                JOIN   categories c   ON c.id = p.category_id
                LEFT JOIN subcategories s ON s.id = p.subcategory_id
                ORDER  BY p.name";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(string $id): ?array {
        $sql = "SELECT p.id, p.name, p.category_id, c.name AS category_name,
                       p.subcategory_id, s.name AS subcategory_name,
                       p.unit, p.hsn_code, p.gst_rate, p.created_at
                FROM   products p
                JOIN   categories c   ON c.id = p.category_id
                LEFT JOIN subcategories s ON s.id = p.subcategory_id
                WHERE  p.id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findByName(string $name): ?array {
        $stmt = $this->db->prepare("SELECT id FROM products WHERE LOWER(name) = LOWER(?)");
        $stmt->execute([$name]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function create(string $name, string $categoryId, ?string $subcategoryId, string $unit, ?string $hsnCode, float $gstRate): array {
        $sql = "INSERT INTO products (name, category_id, subcategory_id, unit, hsn_code, gst_rate)
                VALUES (?, ?, ?, ?, ?, ?)
                RETURNING id, name, category_id, subcategory_id, unit, hsn_code, gst_rate, created_at";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$name, $categoryId, $subcategoryId ?: null, $unit, $hsnCode ?: null, $gstRate]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function update(string $id, string $name, string $categoryId, ?string $subcategoryId, string $unit, ?string $hsnCode, float $gstRate): array {
        $sql = "UPDATE products
                SET name = ?, category_id = ?, subcategory_id = ?, unit = ?, hsn_code = ?, gst_rate = ?
                WHERE id = ?
                RETURNING id, name, category_id, subcategory_id, unit, hsn_code, gst_rate, created_at";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$name, $categoryId, $subcategoryId ?: null, $unit, $hsnCode ?: null, $gstRate, $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function delete(string $id): bool {
        $stmt = $this->db->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
}
