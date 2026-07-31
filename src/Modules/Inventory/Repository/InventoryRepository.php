<?php
declare(strict_types=1);

namespace Modules\Inventory\Repository;

use PDO;
use Config\Database;
use Modules\Inventory\Repository\Contract\InventoryRepositoryInterface;

/**
 * InventoryRepository — data-access only.
 *
 * Every query is tenant-scoped via `current_setting('app.current_user_id')`
 * (RLS session variable set by AuthMiddleware). No business rules here.
 */
final class InventoryRepository implements InventoryRepositoryInterface
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    private const SELECT_COLUMNS = <<<SQL
        b.id,
        b.product_id,
        p.name AS product_name,
        p.display_id,
        p.unit,
        COALESCE(p.rop, 0) AS rop,
        COALESCE(p.emergency_stock, 0) AS emergency_stock,
        b.batch_number,
        b.vendor_name,
        b.initial_qty,
        b.remaining_qty AS quantity,
        COALESCE(b.original_quantity, b.initial_qty) AS original_quantity,
        b.cost_price,
        b.selling_price,
        b.retail_price,
        b.created_at,
        b.updated_at
    SQL;

    public function paginate(int $page, int $limit, string $search = '', string $categoryId = '', string $subcategoryId = ''): array
    {
        $offset = ($page - 1) * $limit;
        [$where, $params] = $this->buildWhere($search, $categoryId, $subcategoryId);

        $countStmt = $this->db->prepare("
            SELECT COUNT(DISTINCT b.id) AS total
            FROM public.inventory_batches b
            JOIN public.products p ON p.id = b.product_id
            LEFT JOIN public.categories c ON c.id = p.category_id
            LEFT JOIN public.subcategories sc ON sc.id = p.subcategory_id
            WHERE b.user_id = current_setting('app.current_user_id', true)::uuid
            {$where}
        ");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        if ($total === 0) {
            return ['data' => [], 'total' => 0];
        }

        $selectStmt = $this->db->prepare("
            SELECT " . self::SELECT_COLUMNS . "
            FROM public.inventory_batches b
            JOIN public.products p ON p.id = b.product_id
            LEFT JOIN public.categories c ON c.id = p.category_id
            LEFT JOIN public.subcategories sc ON sc.id = p.subcategory_id
            WHERE b.user_id = current_setting('app.current_user_id', true)::uuid
            {$where}
            ORDER BY b.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $execParams = array_merge($params, [$limit, $offset]);
        $selectStmt->execute($execParams);

        return [
            'data'  => $selectStmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
        ];
    }

    public function findById(string $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT " . self::SELECT_COLUMNS . "
            FROM public.inventory_batches b
            JOIN public.products p ON p.id = b.product_id
            LEFT JOIN public.categories c ON c.id = p.category_id
            LEFT JOIN public.subcategories sc ON sc.id = p.subcategory_id
            WHERE b.id = ? AND b.user_id = current_setting('app.current_user_id', true)::uuid
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data): array
    {
        $stmt = $this->db->prepare("
            INSERT INTO public.inventory_batches (
                user_id, product_id, batch_number, vendor_name,
                initial_qty, remaining_qty, original_quantity,
                cost_price, selling_price, retail_price, created_at
            ) VALUES (
                current_setting('app.current_user_id')::uuid, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            ) RETURNING id
        ");
        $stmt->execute([
            $data['product_id'],
            $data['batch_number'],
            $data['vendor_name'],
            $data['initial_qty'],
            $data['initial_qty'],
            $data['original_quantity'] ?? $data['initial_qty'],
            $data['cost_price'],
            $data['selling_price'],
            $data['retail_price'] ?? 0.0,
            $data['created_at'] ?? date('Y-m-d H:i:s'),
        ]);
        $id = (string)$stmt->fetchColumn();

        $created = $this->findById($id);
        if ($created === null) {
            throw new \RuntimeException('Failed to load created batch');
        }
        return $created;
    }

    public function update(string $id, array $data): bool
    {
        $current = $this->findById($id);
        if ($current === null) {
            return false;
        }

        $initialQty = isset($data['initial_qty']) ? (int)$data['initial_qty'] : null;
        if ($initialQty === null) {
            $initialQty = max((int)$data['quantity'], (int)$current['initial_qty']);
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
            WHERE id = ? AND user_id = current_setting('app.current_user_id', true)::uuid
        ");
        $success = $stmt->execute([
            $data['batch_number'],
            $data['vendor_name'],
            $data['cost_price'],
            $data['selling_price'],
            $data['retail_price'],
            $data['quantity'],
            $initialQty,
            $initialQty,
            $data['created_at'] ?? $current['created_at'],
            $id,
        ]);

        return $success;
    }

    public function batchNumberExists(string $productId, string $batchNumber, ?string $excludeId = null): bool
    {
        $sql = "
            SELECT 1 FROM public.inventory_batches
            WHERE user_id = current_setting('app.current_user_id', true)::uuid
              AND product_id = ?
              AND batch_number = ?
        ";
        $params = [$productId, $batchNumber];
        if ($excludeId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }
        $sql .= ' LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() !== false;
    }

    public function restock(string $id, int $newQuantity): bool
    {
        $stmt = $this->db->prepare("
            UPDATE public.inventory_batches
            SET remaining_qty = ?,
                updated_at = now()
            WHERE id = ? AND user_id = current_setting('app.current_user_id', true)::uuid
        ");
        return $stmt->execute([$newQuantity, $id]);
    }

    public function getStats(string $search = '', string $categoryId = '', string $subcategoryId = ''): array
    {
        [$where, $params] = $this->buildWhere($search, $categoryId, $subcategoryId);

        $stmt = $this->db->prepare("
            SELECT
                COALESCE(SUM(b.remaining_qty * b.cost_price), 0) AS total_stock_value,
                COUNT(b.id) AS total_batches,
                COALESCE(SUM(b.remaining_qty), 0)::int AS total_remaining_qty,
                (
                    SELECT COUNT(*) FROM (
                        SELECT p2.id
                        FROM public.inventory_batches b2
                        JOIN public.products p2 ON p2.id = b2.product_id
                        WHERE b2.user_id = current_setting('app.current_user_id', true)::uuid
                        GROUP BY p2.id, p2.rop
                        HAVING COALESCE(SUM(b2.remaining_qty), 0) > 0
                           AND COALESCE(SUM(b2.remaining_qty), 0) <= COALESCE(NULLIF(p2.rop, 0), 10)
                    ) low_products
                ) AS low_stock_count,
                (
                    SELECT COUNT(*) FROM (
                        SELECT p2.id
                        FROM public.inventory_batches b2
                        JOIN public.products p2 ON p2.id = b2.product_id
                        WHERE b2.user_id = current_setting('app.current_user_id', true)::uuid
                        GROUP BY p2.id
                        HAVING COALESCE(SUM(b2.remaining_qty), 0) <= 0
                    ) out_products
                ) AS out_of_stock_count
            FROM public.inventory_batches b
            JOIN public.products p ON p.id = b.product_id
            LEFT JOIN public.categories c ON c.id = p.category_id
            LEFT JOIN public.subcategories sc ON sc.id = p.subcategory_id
            WHERE b.user_id = current_setting('app.current_user_id', true)::uuid
            {$where}
        ");
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $salesStmt = $this->db->prepare("
            SELECT
                COALESCE(SUM(ii.unit_price * ii.quantity), 0) AS stock_sold_value,
                COALESCE(SUM(ii.cost_price_snapshot * ii.quantity), 0) AS cost_of_goods_sold
            FROM invoice_items ii
            JOIN invoices i ON i.id = ii.invoice_id
            JOIN public.inventory_batches b ON b.id = ii.batch_id
            JOIN public.products p ON p.id = b.product_id
            LEFT JOIN public.categories c ON c.id = p.category_id
            LEFT JOIN public.subcategories sc ON sc.id = p.subcategory_id
            WHERE i.user_id = current_setting('app.current_user_id', true)::uuid
              AND i.invoice_status = 'completed'
            {$where}
        ");
        $salesStmt->execute($params);
        $salesRow = $salesStmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total_stock_value'  => (float)($row['total_stock_value'] ?? 0),
            'total_batches'      => (int)($row['total_batches'] ?? 0),
            'total_remaining_qty'=> (int)($row['total_remaining_qty'] ?? 0),
            'low_stock_count'    => (int)($row['low_stock_count'] ?? 0),
            'out_of_stock_count' => (int)($row['out_of_stock_count'] ?? 0),
            'stock_sold_value'   => (float)($salesRow['stock_sold_value'] ?? 0),
            'cost_of_goods_sold' => (float)($salesRow['cost_of_goods_sold'] ?? 0),
        ];
    }

    /**
     * Build tenant-scoped WHERE fragment + params for list/stats queries.
     *
     * @return array{0: string, 1: array<int, string|int>}
     */
    private function buildWhere(string $search, string $categoryId, string $subcategoryId): array
    {
        $clauses = [];
        $params  = [];

        if ($categoryId !== '') {
            $clauses[] = 'p.category_id = ?';
            $params[]  = $categoryId;
        }
        if ($subcategoryId !== '') {
            $clauses[] = 'p.subcategory_id = ?';
            $params[]  = $subcategoryId;
        }
        if ($search !== '') {
            $clauses[] = "(
                p.name ILIKE ? OR
                p.display_id::text ILIKE ? OR
                p.hsn_code ILIKE ? OR
                b.batch_number ILIKE ? OR
                b.vendor_name ILIKE ? OR
                c.name ILIKE ? OR
                sc.name ILIKE ?
            )";
            $like = "%{$search}%";
            foreach (range(1, 7) as $_) {
                $params[] = $like;
            }
        }

        return [
            $clauses !== [] ? ' AND ' . implode(' AND ', $clauses) : '',
            $params,
        ];
    }
}
