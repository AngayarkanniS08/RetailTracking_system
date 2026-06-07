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

        // 1. Build SQL to get distinct categories matching filters
        $catSql = "
            SELECT DISTINCT p.category_id, c.name AS category_name
            FROM products p
            JOIN categories c ON c.id = p.category_id
            LEFT JOIN subcategories s ON s.id = p.subcategory_id
            WHERE p.user_id = current_setting('app.current_user_id')::uuid
              AND c.user_id = current_setting('app.current_user_id')::uuid
              AND (s.id IS NULL OR s.user_id = current_setting('app.current_user_id')::uuid)
        ";
        $params = [];
        if (!empty($categoryId)) {
            $catSql .= " AND p.category_id = ?";
            $params[] = $categoryId;
        }
        if (!empty($subcategoryId)) {
            $catSql .= " AND p.subcategory_id = ?";
            $params[] = $subcategoryId;
        }
        if (!empty($search)) {
            $catSql .= " AND (p.name ILIKE ? OR c.name ILIKE ? OR COALESCE(s.name, '') ILIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        // Get total count of distinct categories matching the filters
        $countSql = "SELECT COUNT(*) FROM ($catSql) AS temp";
        $stmt = $this->db->prepare($countSql);
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        if ($total === 0) {
            return [
                'data'  => [],
                'total' => 0
            ];
        }

        // Get the paginated list of categories for the current page
        $paginatedCatSql = $catSql . " ORDER BY category_name LIMIT ? OFFSET ?";
        $paginatedParams = $params;
        $paginatedParams[] = $limit;
        $paginatedParams[] = $offset;

        $stmt = $this->db->prepare($paginatedCatSql);
        $stmt->execute($paginatedParams);
        $categoriesPage = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $categoryIds = array_column($categoriesPage, 'category_id');

        if (empty($categoryIds)) {
            return [
                'data'  => [],
                'total' => $total
            ];
        }

        // 2. Fetch all products belonging to these categories
        $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
        $productSql = "
            SELECT p.id, p.name, p.category_id, c.name AS category_name,
                   p.subcategory_id, s.name AS subcategory_name,
                   p.unit, p.hsn_code, p.gst_rate, p.created_at,
                   p.daily_sales, p.lead_time, p.emergency_stock, p.rop, p.alert_triggered
            FROM products p
            JOIN categories c ON c.id = p.category_id
            LEFT JOIN subcategories s ON s.id = p.subcategory_id
            WHERE p.user_id = current_setting('app.current_user_id')::uuid
              AND c.user_id = current_setting('app.current_user_id')::uuid
              AND (s.id IS NULL OR s.user_id = current_setting('app.current_user_id')::uuid)
              AND p.category_id IN ($placeholders)
        ";
        $productParams = $categoryIds;

        if (!empty($subcategoryId)) {
            $productSql .= " AND p.subcategory_id = ?";
            $productParams[] = $subcategoryId;
        }

        if (!empty($search)) {
            $productSql .= " AND (p.name ILIKE ? OR c.name ILIKE ? OR COALESCE(s.name, '') ILIKE ?)";
            $productParams[] = "%$search%";
            $productParams[] = "%$search%";
            $productParams[] = "%$search%";
        }

        $productSql .= " ORDER BY c.name, s.name, p.name";

        $stmt = $this->db->prepare($productSql);
        $stmt->execute($productParams);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'data'  => $data,
            'total' => $total
        ];
    }

    private function getCurrentUserId(): string {
        // This function should read from RLS setting or session
        $stmt = $this->db->query("SELECT current_setting('app.current_user_id', true)");
        return $stmt->fetchColumn() ?: '';
    }

    public function findAll(): array {
        $sql = "SELECT p.id, p.name, p.category_id, c.name AS category_name,
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
        $sql = "SELECT p.id, p.name, p.category_id, c.name AS category_name,
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
