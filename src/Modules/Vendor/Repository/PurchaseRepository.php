<?php

namespace Modules\Vendor\Repository;

use PDO;
use Modules\Vendor\Model\Vendor;
use Modules\Vendor\Model\Purchase;
use Modules\Vendor\Model\PurchaseItem;
use Modules\Vendor\Repository\Contract\PurchaseRepositoryInterface;

class PurchaseRepository implements PurchaseRepositoryInterface
{
    private PDO $db;

    public function __construct()
    {
        $this->db = \Config\Database::getConnection();
    }

    // ── Vendor methods ────────────────────────────────────────────────────────

    public function findOrCreateVendor(string $name, string $phone): Vendor
    {
        $stmt = $this->db->prepare(
            "SELECT id, name, contact_info AS phone, created_at, updated_at FROM vendors
             WHERE name = ?
               AND user_id = current_setting('app.current_user_id')::uuid"
        );
        $stmt->execute([$name]);
        $vendor = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($vendor) {
            if ($vendor['phone'] !== $phone) {
                $updateStmt = $this->db->prepare(
                    "UPDATE vendors SET contact_info = ?, updated_at = now()
                     WHERE id = ? AND user_id = current_setting('app.current_user_id')::uuid"
                );
                $updateStmt->execute([$phone, $vendor['id']]);
                $vendor['phone'] = $phone;
            }
        } else {
            $stmt = $this->db->prepare(
                "INSERT INTO vendors (name, contact_info, user_id)
                 VALUES (?, ?, current_setting('app.current_user_id')::uuid)
                 RETURNING id, name, contact_info AS phone, created_at, updated_at"
             );
            $stmt->execute([$name, $phone]);
            $vendor = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        return new Vendor(
            id: $vendor['id'],
            name: $vendor['name'],
            phone: $vendor['phone'],
            createdAt: $vendor['created_at'] ?? null,
            updatedAt: $vendor['updated_at'] ?? null
        );
    }

    public function findVendorById(string $id, bool $withPurchases = false): ?Vendor
    {
        $stmt = $this->db->prepare("
            SELECT id, name, contact_info AS phone, created_at, updated_at
            FROM vendors
            WHERE id = ? AND user_id = current_setting('app.current_user_id')::uuid
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        return new Vendor(
            id: $row['id'],
            name: $row['name'],
            phone: $row['phone'],
            createdAt: $row['created_at'] ?? null,
            updatedAt: $row['updated_at'] ?? null
        );
    }

    public function updateVendor(Vendor $vendor): Vendor
    {
        $stmt = $this->db->prepare("
            UPDATE vendors
            SET name = ?, contact_info = ?, updated_at = now()
            WHERE id = ? AND user_id = current_setting('app.current_user_id')::uuid
            RETURNING updated_at
        ");
        $stmt->execute([$vendor->name, $vendor->phone, $vendor->id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) $vendor->updatedAt = $row['updated_at'];
        return $vendor;
    }

    public function deleteVendor(string $id): bool
    {
        // Check if vendor has purchases
        $stmt = $this->db->prepare("
            SELECT 1 FROM vendor_purchases
            WHERE vendor_id = ? AND user_id = current_setting('app.current_user_id')::uuid LIMIT 1
        ");
        $stmt->execute([$id]);
        if ($stmt->fetchColumn()) {
            throw new \Exception('Cannot delete vendor with existing purchases');
        }
        $stmt = $this->db->prepare("
            DELETE FROM vendors
            WHERE id = ? AND user_id = current_setting('app.current_user_id')::uuid
        ");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    // ── Purchase methods ──────────────────────────────────────────────────────

    public function createPurchase(Purchase $purchase): Purchase
    {
        $stmt = $this->db->prepare(
            "INSERT INTO vendor_purchases (id, vendor_id, total_amount, amount_paid, purchase_date, user_id, created_at, updated_at)
             VALUES (gen_random_uuid(), ?, ?, ?, ?, current_setting('app.current_user_id')::uuid, now(), now())
             RETURNING id, vendor_id, total_amount, amount_paid, purchase_date, user_id, created_at, updated_at"
        );
        $stmt->execute([
            $purchase->vendorId,
            $purchase->baseAmount,
            $purchase->amountPaid,
            $purchase->purchaseDate ?: date('Y-m-d H:i:s')
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $status = $row['amount_paid'] >= $row['total_amount'] ? 'paid' : ($row['amount_paid'] > 0 ? 'partial' : 'pending');

        return new Purchase(
            id: $row['id'],
            vendorId: $row['vendor_id'],
            status: $status,
            items: $purchase->items,
            baseAmount: (float)$row['total_amount'],
            amountPaid: (float)$row['amount_paid'],
            purchaseDate: $row['purchase_date'],
            userId: $row['user_id'],
            createdAt: $row['created_at'],
            updatedAt: $row['updated_at']
        );
    }

    public function findPurchaseById(string $id, bool $withItems = false): ?Purchase
    {
        $stmt = $this->db->prepare("
            SELECT p.id, p.vendor_id, p.purchase_date, p.total_amount AS base_amount, p.amount_paid, p.user_id, p.created_at, p.updated_at,
                   v.name AS vendor_name
            FROM vendor_purchases p
            LEFT JOIN vendors v ON v.id = p.vendor_id
            WHERE p.id = ? AND p.user_id = current_setting('app.current_user_id')::uuid
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;

        $items = [];
        if ($withItems) {
            $itemStmt = $this->db->prepare("
                SELECT id, purchase_id, product_id, product_name_snapshot, quantity, unit_cost
                FROM vendor_purchase_items
                WHERE purchase_id = ?
            ");
            $itemStmt->execute([$id]);
            $itemRows = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($itemRows as $itemRow) {
                $items[] = new PurchaseItem(
                    id: $itemRow['id'],
                    purchaseId: $itemRow['purchase_id'],
                    productId: $itemRow['product_id'],
                    quantity: (float)$itemRow['quantity'],
                    unitPrice: (float)$itemRow['unit_cost'],
                    totalPrice: (float)($itemRow['quantity'] * $itemRow['unit_cost']),
                    productName: $itemRow['product_name_snapshot']
                );
            }
        }

        $status = $row['amount_paid'] >= $row['base_amount'] ? 'paid' : ($row['amount_paid'] > 0 ? 'partial' : 'pending');

        return new Purchase(
            id: $row['id'],
            vendorId: $row['vendor_id'],
            status: $status,
            items: $items,
            baseAmount: (float)$row['base_amount'],
            amountPaid: (float)$row['amount_paid'],
            purchaseDate: $row['purchase_date'],
            userId: $row['user_id'],
            createdAt: $row['created_at'] ?? null,
            updatedAt: $row['updated_at'] ?? null,
            vendorName: $row['vendor_name'] ?? null
        );
    }

    public function findAllPurchases(int $page, int $limit, array $filters): array
    {
        $offset = ($page - 1) * $limit;
        
        $sqlBase = "
            FROM vendor_purchases p
            LEFT JOIN vendors v ON v.id = p.vendor_id
            WHERE p.user_id = current_setting('app.current_user_id')::uuid
            AND (v.id IS NULL OR v.user_id = current_setting('app.current_user_id')::uuid)
        ";
        
        $params = [];
        $filterSql = "";
        
        if (!empty($filters['vendor_id'])) {
            $filterSql .= " AND p.vendor_id = ?";
            $params[] = $filters['vendor_id'];
        }
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'paid') {
                $filterSql .= " AND p.amount_paid >= p.total_amount";
            } elseif ($filters['status'] === 'pending') {
                $filterSql .= " AND p.amount_paid = 0";
            } elseif ($filters['status'] === 'partial') {
                $filterSql .= " AND p.amount_paid > 0 AND p.amount_paid < p.total_amount";
            }
        }
        if (!empty($filters['date_from'])) {
            $filterSql .= " AND p.purchase_date >= ?";
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $filterSql .= " AND p.purchase_date <= ?";
            $params[] = $filters['date_to'];
        }
        if (!empty($filters['search'])) {
            $filterSql .= " AND (v.name ILIKE ? OR p.id::text ILIKE ?)";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
        }
        
        // 1. Get total count
        $countStmt = $this->db->prepare("SELECT COUNT(p.id) " . $sqlBase . $filterSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        
        // 2. Get paginated data
        $dataSql = "
            SELECT p.id, p.vendor_id, p.purchase_date, p.total_amount AS base_amount, p.amount_paid,
                p.user_id, p.created_at, p.updated_at,
                v.name AS vendor_name
        " . $sqlBase . $filterSql . " ORDER BY p.purchase_date DESC LIMIT ? OFFSET ?";
        
        $dataParams = array_merge($params, [$limit, $offset]);
        $stmt = $this->db->prepare($dataSql);
        $stmt->execute($dataParams);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $purchases = array_map(function($row) {
            $status = $row['amount_paid'] >= $row['base_amount'] ? 'paid' : ($row['amount_paid'] > 0 ? 'partial' : 'pending');
            return new Purchase(
                id: $row['id'],
                vendorId: $row['vendor_id'],
                status: $status,
                items: null,
                baseAmount: (float)$row['base_amount'],
                amountPaid: (float)$row['amount_paid'],
                purchaseDate: $row['purchase_date'],
                userId: $row['user_id'],
                createdAt: $row['created_at'] ?? null,
                updatedAt: $row['updated_at'] ?? null,
                vendorName: $row['vendor_name'] ?? null
            );
        }, $rows);

        return [
            'data' => $purchases,
            'total' => $total
        ];
    }

    public function updatePurchase(string $purchaseId, string $status): bool
    {
        if ($status === 'paid') {
            $stmt = $this->db->prepare("
                UPDATE vendor_purchases
                SET amount_paid = total_amount, updated_at = now()
                WHERE id = ? AND user_id = current_setting('app.current_user_id')::uuid
            ");
            $stmt->execute([$purchaseId]);
            return $stmt->rowCount() > 0;
        } elseif ($status === 'pending') {
            $stmt = $this->db->prepare("
                UPDATE vendor_purchases
                SET amount_paid = 0, updated_at = now()
                WHERE id = ? AND user_id = current_setting('app.current_user_id')::uuid
            ");
            $stmt->execute([$purchaseId]);
            return $stmt->rowCount() > 0;
        }
        return false;
    }

    public function recordPayment(string $purchaseId, float $amount): bool
    {
        $stmt = $this->db->prepare("
            UPDATE vendor_purchases
            SET amount_paid = amount_paid + ?,
                updated_at = now()
            WHERE id = ? AND user_id = current_setting('app.current_user_id')::uuid
              AND amount_paid + ? <= total_amount
        ");
        $stmt->execute([$amount, $purchaseId, $amount]);
        return $stmt->rowCount() > 0;
    }

    public function getVendorHistory(string $vendorId): array
    {
        $stmt = $this->db->prepare("
            SELECT p.id, p.purchase_date, p.total_amount AS base_amount, p.amount_paid,
                p.created_at,
                pi.product_id, pi.quantity, pi.unit_cost AS unit_price,
                COALESCE(pr.name, pi.product_name_snapshot) AS product_name
            FROM vendor_purchases p
            LEFT JOIN vendor_purchase_items pi ON pi.purchase_id = p.id
            LEFT JOIN products pr ON pr.id = pi.product_id
            WHERE p.vendor_id = ? AND p.user_id = current_setting('app.current_user_id')::uuid
            ORDER BY p.purchase_date DESC
        ");
        $stmt->execute([$vendorId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $history = [];
        foreach ($rows as $row) {
            $purchaseId = $row['id'];
            if (!isset($history[$purchaseId])) {
                $history[$purchaseId] = [
                    'id' => $row['id'],
                    'purchase_date' => $row['purchase_date'],
                    'base_amount' => (float)$row['base_amount'],
                    'amount_paid' => (float)$row['amount_paid'],
                    'status' => $row['amount_paid'] >= $row['base_amount'] ? 'paid' : ($row['amount_paid'] > 0 ? 'partial' : 'pending'),
                    'items' => []
                ];
            }
            if ($row['product_id']) {
                $history[$purchaseId]['items'][] = [
                    'product_id' => $row['product_id'],
                    'product_name' => $row['product_name'],
                    'quantity' => (float)$row['quantity'],
                    'unit_price' => (float)$row['unit_price'],
                    'total_line' => (float)($row['quantity'] * $row['unit_price'])
                ];
            }
        }
        return array_values($history);
    }

    public function getVendorBalance(string $vendorId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                COALESCE(SUM(total_amount), 0) AS total_spent,
                COALESCE(SUM(amount_paid), 0) AS total_paid,
                COALESCE(SUM(total_amount - amount_paid), 0) AS balance_due
            FROM vendor_purchases
            WHERE vendor_id = ? AND user_id = current_setting('app.current_user_id')::uuid
        ");
        $stmt->execute([$vendorId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return [
            'total_spent' => (float)$row['total_spent'],
            'total_paid'  => (float)$row['total_paid'],
            'balance_due' => (float)$row['balance_due']
        ];
    }

    public function getGlobalPurchaseStats(): array
    {
        $stmt = $this->db->prepare("
            SELECT
                COALESCE(COUNT(DISTINCT vendor_id), 0) AS total_vendors,
                COALESCE(SUM(total_amount), 0) AS total_purchased,
                COALESCE(SUM(amount_paid), 0) AS total_paid,
                COALESCE(SUM(total_amount - amount_paid), 0) AS balance_due
            FROM vendor_purchases
            WHERE user_id = current_setting('app.current_user_id')::uuid
        ");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return [
            'total_vendors' => (int)$row['total_vendors'],
            'total_purchased' => (float)$row['total_purchased'],
            'total_paid' => (float)$row['total_paid'],
            'balance_due' => (float)$row['balance_due']
        ];
    }

    public function createPurchaseItems(array $items, string $purchaseId): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO vendor_purchase_items (id, purchase_id, product_id, product_name_snapshot, quantity, unit_cost) 
            VALUES (gen_random_uuid(), ?, ?, ?, ?, ?)"
        );
        $this->db->beginTransaction();
        try {
            $nameStmt = $this->db->prepare("SELECT name FROM products WHERE id = ?");
            foreach ($items as $item) {
                $nameStmt->execute([$item->productId]);
                $productName = $nameStmt->fetchColumn() ?: 'Unknown Product';

                $stmt->execute([
                    $purchaseId,
                    $item->productId,
                    $productName,
                    $item->quantity,
                    $item->unitPrice
                ]);
            }
            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}