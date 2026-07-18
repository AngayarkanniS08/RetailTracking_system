<?php
namespace Modules\Product\Repository;

use PDO;
use Modules\Product\Repository\Contract\ProductRepositoryInterface;

class ProductRepository implements ProductRepositoryInterface {
    private PDO $db;

    public function __construct() {
        $this->db = \Config\Database::getConnection();
    }

        /**
     * Returns paginated products with total count.
     *
     * @param int    $page
     * @param int    $limit
     * @param string $search   (optional)
     * @param string $categoryId (optional)
     * @return array{data: array, total: int}
     */
    public function findPaginated(int $page, int $limit, string $search = '', string $categoryId = '', string $subcategoryId = ''): array {
        $offset = ($page - 1) * $limit;

        $joins = "FROM products p
                  JOIN categories c ON c.id = p.category_id
                  LEFT JOIN subcategories s ON s.id = p.subcategory_id";
        $where = "WHERE p.user_id = current_setting('app.current_user_id')::uuid
                  AND c.user_id = current_setting('app.current_user_id')::uuid
                  AND (s.id IS NULL OR s.user_id = current_setting('app.current_user_id')::uuid)";
        $params = [];

        if (!empty($categoryId)) {
            $where .= " AND p.category_id = ?";
            $params[] = $categoryId;
        }
        if (!empty($subcategoryId)) {
            $where .= " AND p.subcategory_id = ?";
            $params[] = $subcategoryId;
        }
        if (!empty($search)) {
            $where .= " AND (p.name ILIKE ? OR c.name ILIKE ? OR COALESCE(s.name, '') ILIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        // Count total products (not categories)
        $stmt = $this->db->prepare("SELECT COUNT(*) $joins $where");
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        if ($total === 0) {
            return ['data' => [], 'total' => 0];
        }

        // Fetch paginated products ordered by category, subcategory, name
        $sql = "SELECT p.id, p.display_id, p.name, p.category_id, c.name AS category_name,
                       p.subcategory_id, s.name AS subcategory_name,
                       p.unit, p.hsn_code, p.gst_rate, p.created_at,
                       p.daily_sales, p.lead_time, p.emergency_stock, p.rop, p.alert_triggered
                $joins $where
                ORDER BY c.name, s.name, p.name
                LIMIT ? OFFSET ?";
        $paginatedParams = $params;
        $paginatedParams[] = $limit;
        $paginatedParams[] = $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($paginatedParams);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ['data' => $data, 'total' => $total];
    }

    private function getCurrentUserId(): string {
        // This function should read from RLS setting or session
        $stmt = $this->db->query("SELECT current_setting('app.current_user_id', true)");
        return $stmt->fetchColumn() ?: '';
    }

    public function findAll(): array {
        $sql = "SELECT p.id, p.display_id, p.name, p.category_id, c.name AS category_name,
                       p.subcategory_id, s.name AS subcategory_name,
                       p.unit, p.hsn_code, p.gst_rate, p.created_at,
                       p.daily_sales, p.lead_time, p.emergency_stock, p.rop, p.alert_triggered
                FROM   products p
                JOIN   categories c   ON c.id = p.category_id
                LEFT JOIN subcategories s ON s.id = p.subcategory_id
                WHERE  p.user_id = current_setting('app.current_user_id')::uuid
                  AND  c.user_id = current_setting('app.current_user_id')::uuid
                  AND  (s.id IS NULL OR s.user_id = current_setting('app.current_user_id')::uuid)
                ORDER  BY c.name, s.name, p.name";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(string $id): ?array {
        $sql = "SELECT p.id, p.display_id, p.name, p.category_id, c.name AS category_name,
                       p.subcategory_id, s.name AS subcategory_name,
                       p.unit, p.hsn_code, p.gst_rate, p.created_at,
                       p.daily_sales, p.lead_time, p.emergency_stock, p.rop, p.alert_triggered
                FROM   products p
                JOIN   categories c   ON c.id = p.category_id
                LEFT JOIN subcategories s ON s.id = p.subcategory_id
                WHERE  p.id = ?
                  AND  p.user_id = current_setting('app.current_user_id')::uuid
                  AND  c.user_id = current_setting('app.current_user_id')::uuid
                  AND  (s.id IS NULL OR s.user_id = current_setting('app.current_user_id')::uuid)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findByName(string $name): ?array {
        $stmt = $this->db->prepare("SELECT id FROM products WHERE LOWER(name) = LOWER(?) AND user_id = current_setting('app.current_user_id')::uuid");
        $stmt->execute([$name]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function create(string $name, string $categoryId, ?string $subcategoryId, string $unit, ?string $hsnCode, float $gstRate): array {
        $sql = "INSERT INTO products (name, category_id, subcategory_id, unit, hsn_code, gst_rate, user_id)
                VALUES (?, ?, ?, ?, ?, ?, current_setting('app.current_user_id')::uuid)
                RETURNING id, name, category_id, subcategory_id, unit, hsn_code, gst_rate, created_at";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$name, $categoryId, $subcategoryId ?: null, $unit, $hsnCode ?: null, $gstRate]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function update(string $id, string $name, string $categoryId, ?string $subcategoryId, string $unit, ?string $hsnCode, float $gstRate): array {
        $sql = "UPDATE products
                SET name = ?, category_id = ?, subcategory_id = ?, unit = ?, hsn_code = ?, gst_rate = ?
                WHERE id = ? AND user_id = current_setting('app.current_user_id')::uuid
                RETURNING id, name, category_id, subcategory_id, unit, hsn_code, gst_rate, created_at";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$name, $categoryId, $subcategoryId ?: null, $unit, $hsnCode ?: null, $gstRate, $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function delete(string $id): bool {
        $stmt = $this->db->prepare("DELETE FROM products WHERE id = ? AND user_id = current_setting('app.current_user_id')::uuid");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
}
