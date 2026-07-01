<?php
namespace Modules\Customer\Repository;

use PDO;
use Modules\Customer\Model\Customer;
use Modules\Customer\Model\CreditPayment;
use Modules\Customer\Model\CreditLedger;
use Modules\Customer\Repository\Contract\CustomerRepositoryInterface;

class CustomerRepository implements CustomerRepositoryInterface
{
    private PDO $db;

    public function __construct()
    {
        $this->db = \Config\Database::getConnection();
    }

    public function beginTransaction(): void { $this->db->beginTransaction(); }
    public function commit(): void { $this->db->commit(); }
    public function rollback(): void { $this->db->rollBack(); }

    // ── Customer CRUD ────────────────────────────────────────

    public function createCustomer(Customer $customer): Customer
    {
        $stmt = $this->db->prepare("
            INSERT INTO customers (
                id, user_id, name, phone, email, gstin, address,
                credit_limit, opening_balance, status, created_at, updated_at
            ) VALUES (
                gen_random_uuid(),
                current_setting('app.current_user_id')::uuid, ?, ?, ?, ?, ?,
                ?, ?, ?, now(), now()
            )
            RETURNING id, user_id, name, phone, email, gstin, address,
                      credit_limit, opening_balance, status, created_at, updated_at
        ");
        $stmt->execute([
            $customer->name,
            $customer->phone,
            $customer->email,
            $customer->gstin,
            $customer->address,
            $customer->creditLimit,
            $customer->openingBalance,
            $customer->status
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return new Customer(
            id: $row['id'],
            userId: $row['user_id'],
            name: $row['name'],
            phone: $row['phone'],
            email: $row['email'],
            gstin: $row['gstin'],
            address: $row['address'],
            creditLimit: (float)$row['credit_limit'],
            openingBalance: (float)$row['opening_balance'],
            status: $row['status'],
            createdAt: $row['created_at'],
            updatedAt: $row['updated_at']
        );
    }

    public function findCustomerById(string $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, name, phone, email, gstin, address,
                   credit_limit, opening_balance, status, created_at, updated_at
            FROM customers
            WHERE id = ? AND user_id = current_setting('app.current_user_id')::uuid
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findAllCustomers(int $page, int $limit, ?string $search = null): array
    {
        $params = [];
        $where = '';

        if ($search) {
            $where = "AND (name ILIKE ? OR phone ILIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $countStmt = $this->db->prepare("
            SELECT COUNT(*) FROM customers
            WHERE user_id = current_setting('app.current_user_id')::uuid
            $where
        ");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $offset = ($page - 1) * $limit;
        $paginatedParams = $params;
        $paginatedParams[] = $limit;
        $paginatedParams[] = $offset;

        $stmt = $this->db->prepare("
            SELECT c.id, c.name, c.phone, c.email, c.gstin, c.address,
                   c.credit_limit, c.opening_balance, c.status, c.created_at,
                   COALESCE(l.balance, c.opening_balance) AS current_balance
            FROM customers c
            LEFT JOIN LATERAL (
                SELECT balance FROM customer_ledger
                WHERE customer_id = c.id
                ORDER BY created_at DESC
                LIMIT 1
            ) l ON true
            WHERE c.user_id = current_setting('app.current_user_id')::uuid
            $where
            ORDER BY c.name ASC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute($paginatedParams);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'data' => $rows,
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ];
    }

    public function updateCustomer(string $id, array $data): bool
    {
        $fields = [];
        $params = [];

        foreach ($data as $key => $value) {
            $fields[] = "{$key} = ?";
            $params[] = $value;
        }
        if (empty($fields)) return false;

        $params[] = $id;
        $stmt = $this->db->prepare("
            UPDATE customers
            SET " . implode(', ', $fields) . ", updated_at = now()
            WHERE id = ? AND user_id = current_setting('app.current_user_id')::uuid
        ");
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    // ── Ledger ─────────────────────────────────────────────

    public function getCustomerBalance(string $customerId): float
    {
        $stmt = $this->db->prepare("
            SELECT balance FROM customer_ledger
            WHERE customer_id = ? AND user_id = current_setting('app.current_user_id')::uuid
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$customerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (float)$row['balance'] : 0;
    }

    public function getCustomerLedger(string $customerId, int $limit = 20, int $offset = 0): array
    {
        $stmt = $this->db->prepare("
            SELECT cl.id, cl.user_id, cl.customer_id, cl.invoice_id,
                   cl.entry_type, cl.debit, cl.credit, cl.balance, cl.notes, cl.created_at,
                   pr.receipt_number AS payment_receipt,
                   i.invoice_status
            FROM customer_ledger cl
            LEFT JOIN payment_receipts pr ON pr.ledger_id = cl.id AND cl.entry_type = 'payment'
            LEFT JOIN invoices i ON i.id = cl.invoice_id
            WHERE cl.customer_id = ? AND cl.user_id = current_setting('app.current_user_id')::uuid
            ORDER BY cl.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$customerId, $limit, $offset]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($row) => new CreditLedger(
            id: $row['id'],
            userId: $row['user_id'],
            customerId: $row['customer_id'],
            entryType: $row['entry_type'],
            debit: (float)$row['debit'],
            credit: (float)$row['credit'],
            balance: (float)$row['balance'],
            invoiceId: $row['invoice_id'],
            invoiceStatus: $row['invoice_status'],
            notes: $row['payment_receipt'] ? "{$row['notes']} [{$row['payment_receipt']}]" : $row['notes'],
            createdAt: $row['created_at']
        ), $rows);
    }

    public function getCustomerSummary(string $customerId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                COALESCE(SUM(cl.debit), 0) AS total_purchases,
                COALESCE(SUM(cl.credit), 0) AS total_paid,
                COUNT(*) FILTER (WHERE cl.entry_type = 'invoice') AS total_bills,
                COUNT(*) FILTER (WHERE cl.entry_type = 'invoice' AND COALESCE(i.balance_due, 0) <= 0) AS bills_cleared
            FROM customer_ledger cl
            LEFT JOIN invoices i ON i.id = cl.invoice_id
            WHERE cl.customer_id = ? AND cl.user_id = current_setting('app.current_user_id')::uuid
        ");
        $stmt->execute([$customerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $balance = $this->getCustomerBalance($customerId);

        return [
            'total_purchases' => (float)$row['total_purchases'],
            'total_paid' => (float)$row['total_paid'],
            'balance' => $balance,
            'total_bills' => (int)$row['total_bills'],
            'bills_cleared' => (int)$row['bills_cleared']
        ];
    }

    // ── Payments ───────────────────────────────────────────

    public function createPayment(CreditPayment $payment): CreditPayment
    {
        $stmt = $this->db->prepare("
            INSERT INTO payment_receipts (
                id, user_id, customer_id, ledger_id,
                receipt_number, amount, notes, created_at
            ) VALUES (
                gen_random_uuid(),
                current_setting('app.current_user_id')::uuid, ?, ?,
                ?, ?, ?, now()
            )
            RETURNING id, user_id, customer_id, ledger_id,
                      receipt_number, amount, notes, created_at
        ");
        $stmt->execute([
            $payment->customerId,
            $payment->ledgerId,
            $payment->receiptNumber,
            $payment->amount,
            $payment->notes
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return new CreditPayment(
            id: $row['id'],
            userId: $row['user_id'],
            customerId: $row['customer_id'],
            ledgerId: $row['ledger_id'],
            receiptNumber: $row['receipt_number'],
            amount: (float)$row['amount'],
            notes: $row['notes'],
            createdAt: $row['created_at']
        );
    }

    public function getNextReceiptNumber(string $userId, string $year): string
    {
        $prefix = 'PAY';

        $stmt = $this->db->prepare("
            SELECT last_number FROM payment_receipt_sequences
            WHERE user_id = ? AND year = ? AND prefix = ?
            FOR UPDATE
        ");
        $stmt->execute([$userId, $year, $prefix]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $nextNum = $row['last_number'] + 1;
            $updateStmt = $this->db->prepare("
                UPDATE payment_receipt_sequences
                SET last_number = ?, updated_at = now()
                WHERE user_id = ? AND year = ? AND prefix = ?
            ");
            $updateStmt->execute([$nextNum, $userId, $year, $prefix]);
        } else {
            $nextNum = 1;
            $insertStmt = $this->db->prepare("
                INSERT INTO payment_receipt_sequences (id, user_id, year, prefix, last_number, updated_at)
                VALUES (gen_random_uuid(), ?, ?, ?, ?, now())
            ");
            $insertStmt->execute([$userId, $year, $prefix, $nextNum]);
        }

        return sprintf('%s-%s-%06d', $prefix, $year, $nextNum);
    }

    public function getOutstandingInvoiceItems(string $customerId): array
    {
        $stmt = $this->db->prepare("
            SELECT ii.product_name_snapshot AS product_name,
                   ii.quantity, ii.unit_price, ii.line_total,
                   i.invoice_number, i.billed_at, i.grand_total, i.balance_due
            FROM invoice_items ii
            JOIN invoices i ON i.id = ii.invoice_id
            WHERE i.customer_id = ? AND i.user_id = current_setting('app.current_user_id')::uuid
              AND i.invoice_status = 'completed' AND i.balance_due > 0
            ORDER BY i.billed_at DESC, ii.created_at ASC
        ");
        $stmt->execute([$customerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findPaymentByLedgerId(string $ledgerId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, user_id, customer_id, ledger_id,
                   receipt_number, amount, notes, created_at
            FROM payment_receipts
            WHERE ledger_id = ?
        ");
        $stmt->execute([$ledgerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
