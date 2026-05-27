<?php
namespace Modules\Product\Repository;

use PDO;

class ProductRepository {
    private PDO $db;

    public function __construct() {
        $this->db = \Config\Database::getConnection();
    }

    // Returns all products joined with their category name
    public function findAll(): array {
        $sql = "SELECT p.id, p.name, p.category_id, c.name AS category_name,
                       p.unit, p.hsn_code, p.gst_rate, p.created_at
                FROM   products p
                JOIN   categories c ON c.id = p.category_id
                ORDER  BY p.name";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(string $name, string $categoryId, string $unit, ?string $hsnCode, float $gstRate): array {
        $sql = "INSERT INTO products (name, category_id, unit, hsn_code, gst_rate)
                VALUES (?, ?, ?, ?, ?)
                RETURNING id, name, category_id, unit, hsn_code, gst_rate, created_at";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$name, $categoryId, $unit, $hsnCode ?: null, $gstRate]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function delete(string $id): bool {
        $stmt = $this->db->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
}
