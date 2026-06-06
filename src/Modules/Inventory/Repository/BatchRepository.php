<?php
namespace Modules\Inventory\Repository;

use PDO;
use Modules\Inventory\Repository\Contract\BatchRepositoryInterface;

class BatchRepository implements BatchRepositoryInterface
{
    private PDO $db;

    public function __construct()
    {
        $this->db = \Config\Database::getConnection();
    }

    public function findAll(): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                id, 
                product_id, 
                batch_number AS vendor_name, 
                initial_qty, 
                remaining_qty AS quantity, 
                cost_price AS purchase_price, 
                selling_price, 
                retail_price, 
                created_at, 
                updated_at
            FROM public.inventory_batches
            WHERE user_id = current_setting('app.current_user_id')::uuid
            ORDER BY created_at DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(string $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT 
                id, 
                product_id, 
                batch_number AS vendor_name, 
                initial_qty, 
                remaining_qty AS quantity, 
                cost_price AS purchase_price, 
                selling_price, 
                retail_price, 
                created_at, 
                updated_at
            FROM public.inventory_batches
            WHERE id = ? AND user_id = current_setting('app.current_user_id')::uuid
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data): array
    {
        $stmt = $this->db->prepare("
            INSERT INTO public.inventory_batches (
                user_id, product_id, batch_number, initial_qty, remaining_qty, cost_price, selling_price, retail_price, created_at
            ) VALUES (
                current_setting('app.current_user_id')::uuid, ?, ?, ?, ?, ?, ?, ?, ?
            ) RETURNING 
                id, 
                product_id, 
                batch_number AS vendor_name, 
                initial_qty, 
                remaining_qty AS quantity, 
                cost_price AS purchase_price, 
                selling_price, 
                retail_price, 
                created_at, 
                updated_at
        ");
        $stmt->execute([
            $data['product_id'],
            $data['batch_number'],
            $data['initial_qty'],
            $data['remaining_qty'] ?? $data['initial_qty'],
            $data['cost_price'],
            $data['selling_price'],
            $data['retail_price'] ?? 0.00,
            $data['created_at'] ?? date('Y-m-d H:i:s')
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateall(string $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE public.inventory_batches
            SET batch_number = ?,
                cost_price = ?,
                selling_price = ?,
                retail_price = ?,
                remaining_qty = ?,
                created_at = ?
                update_at = now()
            WHERE id = ? AND user_id = current_setting('app.current_user_id')::uuid
        ");
        return $stmt->execute([
            $data['vendor_name'],
            $data['purchase_price'],
            $data['selling_price'],
            $data['retail_price'],
            $data['quantity'],
            $data['created_at'],
            $id
        ]);
    }

    public function findPaginated(int $page, int $limit, string $search = '', string $categoryId = '', string $subcategoryId = ''): array
    {
        $offset = ($page - 1) * $limit;
        $sql = "
            FROM public.inventory_batches b
            JOIN public.products p ON p.id = b.product_id
            WHERE b.user_id = current_setting('app.current_user_id')::uuid
        ";
        $params = [];
        if (!empty($categoryId)) {
            $sql .= " AND p.category_id = ?";
            $params[] = $categoryId;
        }
        if (!empty($subcategoryId)) {
            $sql .= " AND p.subcategory_id = ?";
            $params[] = $subcategoryId;
        }
        if (!empty($search)) {
            $sql .= " AND (p.name ILIKE ? OR b.batch_number ILIKE ? OR b.id::text ILIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        // Count total matching records
        $countSql = "SELECT COUNT(DISTINCT b.id) " . $sql;
        $stmt = $this->db->prepare($countSql);
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();
        if ($total === 0) {
            return [
                'data' => [],
                'total' => 0
            ];
        }

        // Select paginated records
        $selectSql = "
            SELECT 
                b.id, 
                b.product_id, 
                b.batch_number AS vendor_name, 
                b.initial_qty, 
                b.remaining_qty AS quantity, 
                b.cost_price AS purchase_price, 
                b.selling_price, 
                b.retail_price, 
                b.created_at, 
                b.updated_at
            " . $sql . "
            ORDER BY b.created_at DESC
            LIMIT ? OFFSET ?
        ";
        $paginatedParams = $params;
        $paginatedParams[] = $limit;
        $paginatedParams[] = $offset;
        $stmt = $this->db->prepare($selectSql);
        $stmt->execute($paginatedParams);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return [
            'data' => $data,
            'total' => $total
        ];
    }
    
 public function getStats(): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                COALESCE(SUM(remaining_qty * cost_price), 0) AS total_stock_value,
                COUNT(*) AS total_batches,
                COUNT(CASE WHEN remaining_qty <= 20 THEN 1 END) AS low_stock_count
            FROM public.inventory_batches
            WHERE user_id = current_setting('app.current_user_id')::uuid
        ");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return [
            'total_stock_value' => (float)($row['total_stock_value'] ?? 0),
            'total_batches' => (int)($row['total_batches'] ?? 0),
            'low_stock_count' => (int)($row['low_stock_count'] ?? 0)
        ];
    }

}
