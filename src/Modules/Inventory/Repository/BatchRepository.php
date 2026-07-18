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
                batch_number, 
                vendor_name, 
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
                batch_number, 
                vendor_name, 
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
                user_id, product_id, batch_number,vendor_name, initial_qty, remaining_qty, original_quantity, cost_price, selling_price, retail_price, created_at
            ) VALUES (
                current_setting('app.current_user_id')::uuid, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            ) RETURNING 
                id, 
                product_id, 
                batch_number,
                vendor_name, 
                initial_qty, 
                remaining_qty AS quantity, 
                cost_price AS purchase_price, 
                selling_price, 
                retail_price, 
                original_quantity,
                created_at, 
                updated_at
        ");
        $stmt->execute([
            $data['product_id'],
            $data['batch_number'],
            $data['vendor_name'],
            $data['initial_qty'],
            $data['remaining_qty'] ?? $data['initial_qty'],
            $data['original_quantity'] ?? $data['initial_qty'],
            $data['cost_price'],
            $data['selling_price'],
            $data['retail_price'] ?? 0.00,
            $data['created_at'] ?? date('Y-m-d H:i:s')
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result && !empty($result['product_id'])) {
            $currentUserId = $this->getCurrentUserId();
            if ($currentUserId) {
                try {
                    $hook = new \Modules\Inventory\Repository\StockAlertHook();
                    $hook->evaluateProductStockAlert($result['product_id'], $currentUserId);
                } catch (\Exception $e) {
                    error_log("Failed evaluating stock alert hook: " . $e->getMessage());
                }
            }
        }

        return $result;
    }

    public function updateall(string $id, array $data): bool
    {
        $stmtProduct = $this->db->prepare("
            SELECT product_id FROM public.inventory_batches 
            WHERE id = ? AND user_id = current_setting('app.current_user_id')::uuid
        ");
        $stmtProduct->execute([$id]);
        $productId = $stmtProduct->fetchColumn();

        $initialQty = isset($data['initial_qty']) ? (int)$data['initial_qty'] : null;
        if ($initialQty === null) {
            $initialQty = max((int)$data['quantity'], $this->getInitialQtyForBatch($id));
        }

        $stmt = $this->db->prepare("
            UPDATE public.inventory_batches
            SET batch_number = ?,
                vendor_name = ?,
                cost_price = ?,
                selling_price = ?,
                retail_price = ?,
                remaining_qty = ?,
                initial_qty = ?,
                original_quantity = ?,
                created_at = ?,
                updated_at = now()
            WHERE id = ? AND user_id = current_setting('app.current_user_id')::uuid
        ");
        $success = $stmt->execute([
            $data['batch_number'],
            $data['vendor_name'],
            $data['purchase_price'],
            $data['selling_price'],
            $data['retail_price'],
            $data['quantity'],
            $initialQty,
            $initialQty,
            $data['created_at'],
            $id
        ]);

        if ($success && $productId) {
            $currentUserId = $this->getCurrentUserId();
            if ($currentUserId) {
                try {
                    $hook = new \Modules\Inventory\Repository\StockAlertHook();
                    $hook->evaluateProductStockAlert($productId, $currentUserId);
                } catch (\Exception $e) {
                    error_log("Failed evaluating stock alert hook in update: " . $e->getMessage());
                }
            }
        }

        return $success;
    }

    private function getCurrentUserId(): string
    {
        $stmt = $this->db->query("SELECT current_setting('app.current_user_id', true)");
        return $stmt->fetchColumn() ?: '';
    }

    private function getInitialQtyForBatch(string $id): int
    {
        $stmt = $this->db->prepare("SELECT initial_qty FROM public.inventory_batches WHERE id = ?");
        $stmt->execute([$id]);
        return (int)$stmt->fetchColumn();
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
                b.batch_number,
                b.vendor_name, 
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
    
    public function getStats(string $search = '', string $categoryId = '', string $subcategoryId = ''): array
    {
        // Shared filter conditions (applied to both queries)
        $filterSql = '';
        $subFilterSql = '';
        $params = [];
        if (!empty($categoryId)) {
            $filterSql .= " AND p.category_id = ?";
            $subFilterSql .= " AND p2.category_id = ?";
            $params[] = $categoryId;
        }
        if (!empty($subcategoryId)) {
            $filterSql .= " AND p.subcategory_id = ?";
            $subFilterSql .= " AND p2.subcategory_id = ?";
            $params[] = $subcategoryId;
        }
        if (!empty($search)) {
            $filterSql .= " AND (p.name ILIKE ? OR b.batch_number ILIKE ? OR b.id::text ILIKE ?)";
            $subFilterSql .= " AND (p2.name ILIKE ? OR b2.batch_number ILIKE ? OR b2.id::text ILIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        // Query 1: Stock value, batches, low stock (per-product)
        $selectSql = "
            SELECT
                COALESCE(SUM(b.remaining_qty * b.cost_price), 0) AS total_stock_value,
                COUNT(b.id) AS total_batches,
                (
                    SELECT COUNT(*) FROM (
                        SELECT p2.id
                        FROM public.inventory_batches b2
                        JOIN public.products p2 ON p2.id = b2.product_id
                        WHERE b2.user_id = current_setting('app.current_user_id')::uuid
                        $subFilterSql
                        GROUP BY p2.id, p2.rop
                        HAVING p2.rop > 0 AND COALESCE(SUM(b2.remaining_qty), 0) <= p2.rop
                    ) low_products
                ) AS low_stock_count
            FROM public.inventory_batches b
            JOIN public.products p ON p.id = b.product_id
            WHERE b.user_id = current_setting('app.current_user_id')::uuid
        " . $filterSql;

        $stmt = $this->db->prepare($selectSql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // Query 2: Sales data (respects same category/subcategory/search filters)
        $salesSql = "
            SELECT
                COALESCE(SUM(ii.unit_price * ii.quantity), 0) AS stock_sold_value,
                COALESCE(SUM(ii.cost_price_snapshot * ii.quantity), 0) AS cost_of_goods_sold
            FROM invoice_items ii
            JOIN invoices i ON i.id = ii.invoice_id
            JOIN public.inventory_batches b ON b.id = ii.batch_id
            JOIN public.products p ON p.id = b.product_id
            WHERE i.user_id = current_setting('app.current_user_id')::uuid
              AND i.invoice_status = 'completed'
        " . $filterSql;

        $stmt2 = $this->db->prepare($salesSql);
        $stmt2->execute($params);
        $salesRow = $stmt2->fetch(PDO::FETCH_ASSOC);

        return [
            'total_stock_value' => (float)($row['total_stock_value'] ?? 0),
            'total_batches' => (int)($row['total_batches'] ?? 0),
            'low_stock_count' => (int)($row['low_stock_count'] ?? 0),
            'stock_sold_value' => (float)($salesRow['stock_sold_value'] ?? 0),
            'cost_of_goods_sold' => (float)($salesRow['cost_of_goods_sold'] ?? 0)
        ];
    }
}
