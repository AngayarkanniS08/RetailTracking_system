<?php

namespace Modules\Billing\Repository;

use PDO;
use Modules\Billing\Model\Invoice;
use Modules\Billing\Model\InvoiceItem;
use Modules\Billing\Model\InvoiceReturn;
use Modules\Billing\Model\StockMovement;
use Modules\Billing\Model\CustomerLedger;
use Modules\Billing\Repository\Contract\InvoiceRepositoryInterface;

class InvoiceRepository implements InvoiceRepositoryInterface
{
    private PDO $db;

    public function __construct()
    {
        $this->db = \Config\Database::getConnection();
    }

    public function beginTransaction(): void { $this->db->beginTransaction(); }
    public function commit(): void { $this->db->commit(); }
    public function rollback(): void { $this->db->rollBack(); }

    // ── Customer ───────────────────────────────────────────

    public function findCustomerById(string $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, name, phone, gstin, address, status
            FROM customers
            WHERE id = ? AND user_id = current_setting('app.current_user_id', true)::uuid
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ── Product ───────────────────────────────────────────

    public function findProductById(string $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, name, hsn_code, unit, gst_rate
            FROM products
            WHERE id = ? AND user_id = current_setting('app.current_user_id', true)::uuid
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ── Batch / Stock lookups ─────────────────────────────

    public function findAvailableBatch(string $productId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, product_id, batch_number, selling_price, cost_price,
                   remaining_qty, initial_qty
            FROM inventory_batches
            WHERE product_id = ? AND user_id = current_setting('app.current_user_id', true)::uuid
              AND remaining_qty > 0
            ORDER BY created_at ASC
            LIMIT 1
        ");
        $stmt->execute([$productId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ── Invoice ────────────────────────────────────────────

    public function createInvoice(Invoice $invoice): Invoice
    {
        $stmt = $this->db->prepare("
            INSERT INTO invoices (
                id, user_id, invoice_number, customer_id,
                customer_name_snapshot, customer_phone_snapshot, customer_gstin_snapshot,
                subtotal, discount_amount, total_gst, round_off, grand_total,
                amount_paid, balance_due, invoice_status, payment_status,
                notes, billed_at, created_at, updated_at
            ) VALUES (
                gen_random_uuid(),
                current_setting('app.current_user_id', true)::uuid, ?, ?,
                ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, now(), now()
            )
            RETURNING id, user_id, invoice_number, customer_id,
                      customer_name_snapshot, customer_phone_snapshot, customer_gstin_snapshot,
                      subtotal, discount_amount, total_gst, round_off, grand_total,
                      amount_paid, balance_due, invoice_status, payment_status,
                      notes, billed_at, created_at, updated_at
        ");
        $stmt->execute([
            $invoice->invoiceNumber,
            $invoice->customerId,
            $invoice->customerNameSnapshot,
            $invoice->customerPhoneSnapshot,
            $invoice->customerGstinSnapshot,
            $invoice->subtotal,
            $invoice->discountAmount,
            $invoice->totalGst,
            $invoice->roundOff,
            $invoice->grandTotal,
            $invoice->amountPaid,
            $invoice->balanceDue,
            $invoice->invoiceStatus,
            $invoice->paymentStatus,
            $invoice->notes,
            $invoice->billedAt ?: date('Y-m-d H:i:s')
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return new Invoice(
            id: $row['id'],
            userId: $row['user_id'],
            invoiceNumber: $row['invoice_number'],
            customerId: $row['customer_id'],
            customerNameSnapshot: $row['customer_name_snapshot'],
            customerPhoneSnapshot: $row['customer_phone_snapshot'],
            customerGstinSnapshot: $row['customer_gstin_snapshot'],
            subtotal: (float)$row['subtotal'],
            discountAmount: (float)$row['discount_amount'],
            totalGst: (float)$row['total_gst'],
            roundOff: (float)$row['round_off'],
            grandTotal: (float)$row['grand_total'],
            amountPaid: (float)$row['amount_paid'],
            balanceDue: (float)$row['balance_due'],
            invoiceStatus: $row['invoice_status'],
            paymentStatus: $row['payment_status'],
            notes: $row['notes'],
            billedAt: $row['billed_at'],
            createdAt: $row['created_at'],
            updatedAt: $row['updated_at']
        );
    }

    public function findInvoiceById(string $id, bool $withItems = false): ?Invoice
    {
        $stmt = $this->db->prepare("
            SELECT id, user_id, invoice_number, customer_id,
                   customer_name_snapshot, customer_phone_snapshot, customer_gstin_snapshot,
                   subtotal, discount_amount, total_gst, round_off, grand_total,
                   amount_paid, balance_due, invoice_status, payment_status,
                   notes, billed_at, created_at, updated_at
            FROM invoices
            WHERE id = ? AND user_id = current_setting('app.current_user_id', true)::uuid
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;

        $items = [];
        if ($withItems) {
            $itemStmt = $this->db->prepare("
                SELECT id, invoice_id, product_id, batch_id,
                       product_name_snapshot, hsn_code_snapshot, unit_snapshot,
                       quantity, unit_price, cost_price_snapshot,
                       gst_rate_snapshot, gst_amount, discount_amount, line_total,
                       created_at
                FROM invoice_items
                WHERE invoice_id = ?
                ORDER BY created_at ASC
            ");
            $itemStmt->execute([$id]);
            $itemRows = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($itemRows as $itemRow) {
                $items[] = new InvoiceItem(
                    id: $itemRow['id'],
                    invoiceId: $itemRow['invoice_id'],
                    productId: $itemRow['product_id'],
                    quantity: (float)$itemRow['quantity'],
                    unitPrice: (float)$itemRow['unit_price'],
                    batchId: $itemRow['batch_id'],
                    productNameSnapshot: $itemRow['product_name_snapshot'],
                    hsnCodeSnapshot: $itemRow['hsn_code_snapshot'],
                    unitSnapshot: $itemRow['unit_snapshot'],
                    costPriceSnapshot: $itemRow['cost_price_snapshot'] ? (float)$itemRow['cost_price_snapshot'] : null,
                    gstRateSnapshot: (float)$itemRow['gst_rate_snapshot'],
                    gstAmount: (float)$itemRow['gst_amount'],
                    discountAmount: (float)$itemRow['discount_amount'],
                    lineTotal: (float)$itemRow['line_total'],
                    createdAt: $itemRow['created_at']
                );
            }
        }

        return new Invoice(
            id: $row['id'],
            userId: $row['user_id'],
            invoiceNumber: $row['invoice_number'],
            customerId: $row['customer_id'],
            customerNameSnapshot: $row['customer_name_snapshot'],
            customerPhoneSnapshot: $row['customer_phone_snapshot'],
            customerGstinSnapshot: $row['customer_gstin_snapshot'],
            subtotal: (float)$row['subtotal'],
            discountAmount: (float)$row['discount_amount'],
            totalGst: (float)$row['total_gst'],
            roundOff: (float)$row['round_off'],
            grandTotal: (float)$row['grand_total'],
            amountPaid: (float)$row['amount_paid'],
            balanceDue: (float)$row['balance_due'],
            invoiceStatus: $row['invoice_status'],
            paymentStatus: $row['payment_status'],
            notes: $row['notes'],
            billedAt: $row['billed_at'],
            createdAt: $row['created_at'],
            updatedAt: $row['updated_at'],
            items: $items
        );
    }

    public function findAllInvoices(int $page, int $limit, array $filters = []): array
    {
        $params = [];
        $where = [];
        $idx = 0;

        if (!empty($filters['search'])) {
            $idx++;
            $where[] = "(invoice_number ILIKE ? OR customer_name_snapshot ILIKE ?)";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
        }
        if (!empty($filters['date_from'])) {
            $idx++;
            $where[] = "billed_at::date >= ?";
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $idx++;
            $where[] = "billed_at::date <= ?";
            $params[] = $filters['date_to'];
        }
        if (!empty($filters['invoice_status'])) {
            $idx++;
            $where[] = "invoice_status = ?";
            $params[] = $filters['invoice_status'];
        }
        if (!empty($filters['payment_status'])) {
            $idx++;
            $where[] = "payment_status = ?";
            $params[] = $filters['payment_status'];
        }

        $whereSql = $where ? 'AND ' . implode(' AND ', $where) : '';

        $countStmt = $this->db->prepare("
            SELECT COUNT(*) FROM invoices
            WHERE user_id = current_setting('app.current_user_id', true)::uuid
            $whereSql
        ");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $offset = ($page - 1) * $limit;
        $paginatedParams = $params;
        $paginatedParams[] = $limit;
        $paginatedParams[] = $offset;

        $stmt = $this->db->prepare("
            SELECT id, user_id, invoice_number, customer_id,
                   customer_name_snapshot, customer_phone_snapshot,
                   subtotal, discount_amount, total_gst, round_off, grand_total,
                   amount_paid, balance_due, invoice_status, payment_status,
                   billed_at, created_at
            FROM invoices
            WHERE user_id = current_setting('app.current_user_id', true)::uuid
            $whereSql
            ORDER BY billed_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute($paginatedParams);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $data = array_map(fn($r) => [
            'id' => $r['id'],
            'invoiceNumber' => $r['invoice_number'],
            'customerId' => $r['customer_id'],
            'customerName' => $r['customer_name_snapshot'],
            'customerPhone' => $r['customer_phone_snapshot'],
            'subtotal' => (float)$r['subtotal'],
            'discountAmount' => (float)$r['discount_amount'],
            'totalGst' => (float)$r['total_gst'],
            'roundOff' => (float)$r['round_off'],
            'grandTotal' => (float)$r['grand_total'],
            'amountPaid' => (float)$r['amount_paid'],
            'balanceDue' => (float)$r['balance_due'],
            'invoiceStatus' => $r['invoice_status'],
            'paymentStatus' => $r['payment_status'],
            'billedAt' => $r['billed_at'],
            'createdAt' => $r['created_at']
        ], $rows);

        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ];
    }

    public function updateInvoiceStatus(string $id, string $status): bool
    {
        $stmt = $this->db->prepare("
            UPDATE invoices
            SET invoice_status = ?, updated_at = now()
            WHERE id = ? AND user_id = current_setting('app.current_user_id', true)::uuid
        ");
        $stmt->execute([$status, $id]);
        return $stmt->rowCount() > 0;
    }

    // ── Invoice items ──────────────────────────────────────

    public function createInvoiceItems(array $items, string $invoiceId): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO invoice_items (
                id, invoice_id, product_id, batch_id,
                product_name_snapshot, hsn_code_snapshot, unit_snapshot,
                quantity, unit_price, cost_price_snapshot,
                gst_rate_snapshot, gst_amount, discount_amount, line_total,
                created_at, user_id
            ) VALUES (
                gen_random_uuid(), ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?, ?,
                now(), current_setting('app.current_user_id', true)::uuid
            )
        ");

        $nameStmt = $this->db->prepare("
            SELECT p.name, p.hsn_code, p.unit, p.gst_rate,
                   COALESCE(b.cost_price, 0) AS cost_price
            FROM products p
            LEFT JOIN inventory_batches b ON b.id = ?
            WHERE p.id = ?
        ");

        foreach ($items as $item) {
            $nameStmt->execute([$item->batchId, $item->productId]);
            $product = $nameStmt->fetch(PDO::FETCH_ASSOC);

            $productName = $product ? $product['name'] : 'Unknown Product';
            $hsnCode = $product ? $product['hsn_code'] : null;
            $unit = $product ? $product['unit'] : 'pcs';
            $gstRate = $product ? (float)$product['gst_rate'] : 0;
            $costPrice = $product ? (float)$product['cost_price'] : 0;

            $stmt->execute([
                $invoiceId,
                $item->productId,
                $item->batchId,
                $productName,
                $hsnCode,
                $unit,
                $item->quantity,
                $item->unitPrice,
                $costPrice,
                $gstRate,
                $item->gstAmount,
                $item->discountAmount,
                $item->lineTotal
            ]);
        }
    }

    // ── Sequence ───────────────────────────────────────────

    public function getNextInvoiceNumber(string $userId, string $year): string
    {
        $prefix = 'INV';

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                SELECT last_number FROM invoice_sequences
                WHERE user_id = ? AND year = ? AND prefix = ?
                FOR UPDATE
            ");
            $stmt->execute([$userId, $year, $prefix]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $nextNum = $row['last_number'] + 1;
                $updateStmt = $this->db->prepare("
                    UPDATE invoice_sequences
                    SET last_number = ?, updated_at = now()
                    WHERE user_id = ? AND year = ? AND prefix = ?
                ");
                $updateStmt->execute([$nextNum, $userId, $year, $prefix]);
            } else {
                $nextNum = 1;
                $insertStmt = $this->db->prepare("
                    INSERT INTO invoice_sequences (id, user_id, year, prefix, last_number, updated_at)
                    VALUES (gen_random_uuid(), ?, ?, ?, ?, now())
                ");
                $insertStmt->execute([$userId, $year, $prefix, $nextNum]);
            }

            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }

        return sprintf('%s-%s-%06d', $prefix, $year, $nextNum);
    }

    // ── Returns ────────────────────────────────────────────

    public function createReturn(InvoiceReturn $return): InvoiceReturn
    {
        $stmt = $this->db->prepare("
            INSERT INTO invoice_returns (
                id, invoice_id, invoice_item_id, product_id, batch_id,
                qty_returned, refund_amount, restock_qty, reason, created_at, user_id
            ) VALUES (
                gen_random_uuid(), ?, ?, ?, ?,
                ?, ?, ?, ?, now(), current_setting('app.current_user_id', true)::uuid
            )
            RETURNING id, invoice_id, invoice_item_id, product_id, batch_id,
                      qty_returned, refund_amount, restock_qty, reason, created_at
        ");
        $stmt->execute([
            $return->invoiceId,
            $return->invoiceItemId,
            $return->productId,
            $return->batchId,
            $return->qtyReturned,
            $return->refundAmount,
            $return->restockQty,
            $return->reason
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return new InvoiceReturn(
            id: $row['id'],
            invoiceId: $row['invoice_id'],
            productId: $row['product_id'],
            qtyReturned: (float)$row['qty_returned'],
            refundAmount: (float)$row['refund_amount'],
            invoiceItemId: $row['invoice_item_id'],
            batchId: $row['batch_id'],
            restockQty: (float)$row['restock_qty'],
            reason: $row['reason'],
            createdAt: $row['created_at']
        );
    }

    public function getTotalReturnedQty(string $invoiceItemId): float
    {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(qty_returned), 0) FROM invoice_returns
            WHERE invoice_item_id = ?
        ");
        $stmt->execute([$invoiceItemId]);
        return (float)$stmt->fetchColumn();
    }

    public function findReturnsByInvoice(string $invoiceId): array
    {
        $stmt = $this->db->prepare("
            SELECT id, invoice_id, invoice_item_id, product_id, batch_id,
                   qty_returned, refund_amount, restock_qty, reason, created_at
            FROM invoice_returns
            WHERE invoice_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$invoiceId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($row) => new InvoiceReturn(
            id: $row['id'],
            invoiceId: $row['invoice_id'],
            productId: $row['product_id'],
            qtyReturned: (float)$row['qty_returned'],
            refundAmount: (float)$row['refund_amount'],
            invoiceItemId: $row['invoice_item_id'],
            batchId: $row['batch_id'],
            restockQty: (float)$row['restock_qty'],
            reason: $row['reason'],
            createdAt: $row['created_at']
        ), $rows);
    }

    // ── Stock ──────────────────────────────────────────────

    public function findBatchById(string $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, product_id, batch_number, selling_price, cost_price,
                   remaining_qty, initial_qty
            FROM inventory_batches
            WHERE id = ? AND user_id = current_setting('app.current_user_id', true)::uuid
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function decrementBatchStock(string $batchId, float $qty): void
    {
        $stmt = $this->db->prepare("
            UPDATE inventory_batches
            SET remaining_qty = remaining_qty - ?,
                updated_at = now()
            WHERE id = ? AND user_id = current_setting('app.current_user_id', true)::uuid
            AND remaining_qty >= ?
        ");
        $stmt->execute([$qty, $batchId, $qty]);
        if ($stmt->rowCount() === 0) {
            throw new \RuntimeException(
                "Failed to decrement batch stock: batch {$batchId} has insufficient stock or not found"
            );
        }
    }

    public function incrementBatchStock(string $batchId, float $qty): void
    {
        $stmt = $this->db->prepare("
            UPDATE inventory_batches
            SET remaining_qty = remaining_qty + ?,
                updated_at = now()
            WHERE id = ? AND user_id = current_setting('app.current_user_id', true)::uuid
        ");
        $stmt->execute([$qty, $batchId]);
    }

    public function refreshStockList(): void
    {
        $this->db->exec("REFRESH MATERIALIZED VIEW CONCURRENTLY public.stock_list");
    }

    // ── Stock movements ────────────────────────────────────

    public function createStockMovement(StockMovement $movement): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO stock_movements (
                id, user_id, batch_id, product_id,
                reference_type, reference_id, movement_type, qty, created_at
            ) VALUES (
                gen_random_uuid(),
                current_setting('app.current_user_id', true)::uuid, ?, ?,
                ?, ?, ?, ?, now()
            )
        ");
        $stmt->execute([
            $movement->batchId,
            $movement->productId,
            $movement->referenceType,
            $movement->referenceId,
            $movement->movementType,
            $movement->qty
        ]);
    }

    // ── Customer ledger ────────────────────────────────────

    public function addLedgerEntry(CustomerLedger $entry): CustomerLedger
    {
        $stmt = $this->db->prepare("
            INSERT INTO customer_ledger (
                id, user_id, customer_id, invoice_id,
                entry_type, debit, credit, balance, notes, created_at
            ) VALUES (
                gen_random_uuid(),
                current_setting('app.current_user_id', true)::uuid, ?, ?,
                ?, ?, ?, ?, ?, clock_timestamp()
            )
            RETURNING id, user_id, customer_id, invoice_id,
                      entry_type, debit, credit, balance, notes, created_at
        ");
        $stmt->execute([
            $entry->customerId,
            $entry->invoiceId,
            $entry->entryType,
            $entry->debit,
            $entry->credit,
            $entry->balance,
            $entry->notes
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return new CustomerLedger(
            id: $row['id'],
            userId: $row['user_id'],
            customerId: $row['customer_id'],
            entryType: $row['entry_type'],
            debit: (float)$row['debit'],
            credit: (float)$row['credit'],
            balance: (float)$row['balance'],
            invoiceId: $row['invoice_id'],
            notes: $row['notes'],
            createdAt: $row['created_at']
        );
    }

    public function getCustomerBalance(string $customerId): float
    {
        $stmt = $this->db->prepare("
            SELECT balance FROM customer_ledger
            WHERE customer_id = ? AND user_id = current_setting('app.current_user_id', true)::uuid
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$customerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (float)$row['balance'] : 0;
    }

    public function getCustomerLedger(string $customerId, int $limit = 20): array
    {
        $stmt = $this->db->prepare("
            SELECT id, user_id, customer_id, invoice_id,
                   entry_type, debit, credit, balance, notes, created_at
            FROM customer_ledger
            WHERE customer_id = ? AND user_id = current_setting('app.current_user_id', true)::uuid
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$customerId, $limit]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($row) => new CustomerLedger(
            id: $row['id'],
            userId: $row['user_id'],
            customerId: $row['customer_id'],
            entryType: $row['entry_type'],
            debit: (float)$row['debit'],
            credit: (float)$row['credit'],
            balance: (float)$row['balance'],
            invoiceId: $row['invoice_id'],
            notes: $row['notes'],
            createdAt: $row['created_at']
        ), $rows);
    }
}
