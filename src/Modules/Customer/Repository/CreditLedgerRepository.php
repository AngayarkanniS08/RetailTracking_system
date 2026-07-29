<?php
namespace Modules\Customer\Repository;

use PDO;
use Modules\Customer\Model\CreditLedger;

/**
 * CreditLedgerRepository — dedicated SQL operations for the customer_ledger table.
 * Extracted from CustomerRepository so each class has a single responsibility.
 */
class CreditLedgerRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = \Config\Database::getConnection();
    }

    // ── Read ─────────────────────────────────────────────────

    public function getLatestBalance(string $customerId): float
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

    public function getLatestBalanceForUpdate(string $customerId): float
    {
        $stmt = $this->db->prepare("
            SELECT balance FROM customer_ledger
            WHERE customer_id = ? AND user_id = current_setting('app.current_user_id', true)::uuid
            ORDER BY created_at DESC
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute([$customerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (float)$row['balance'] : 0;
    }

    /**
     * @return CreditLedger[]
     */
    public function findByCustomer(string $customerId, int $limit = 50, int $offset = 0, ?string $type = null): array
    {
        $where = "cl.customer_id = ? AND cl.user_id = current_setting('app.current_user_id', true)::uuid";
        $params = [$customerId];

        if ($type !== null) {
            $where .= " AND cl.entry_type = ?";
            $params[] = $type;
        }

        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db->prepare("
            SELECT cl.id, cl.user_id, cl.customer_id, cl.invoice_id,
                   cl.entry_type, cl.debit, cl.credit, cl.balance, cl.notes, cl.created_at,
                   pr.receipt_number AS payment_receipt,
                   i.invoice_status
            FROM customer_ledger cl
            LEFT JOIN payment_receipts pr ON pr.ledger_id = cl.id AND cl.entry_type = 'payment'
            LEFT JOIN invoices i ON i.id = cl.invoice_id
            WHERE {$where}
            ORDER BY cl.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute($params);
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

    public function countByCustomer(string $customerId, ?string $type = null): int
    {
        $where = "customer_id = ? AND user_id = current_setting('app.current_user_id', true)::uuid";
        $params = [$customerId];

        if ($type !== null) {
            $where .= " AND entry_type = ?";
            $params[] = $type;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM customer_ledger WHERE {$where}");
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    // ── Write ─────────────────────────────────────────────────

    public function insert(CreditLedger $entry): CreditLedger
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

        return new CreditLedger(
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

    // ── Aggregates ────────────────────────────────────────────

    public function getSummary(string $customerId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                COALESCE(SUM(debit)  FILTER (WHERE entry_type IN ('invoice', 'opening')), 0)
                - COALESCE(SUM(credit) FILTER (WHERE entry_type = 'return'), 0) AS total_purchases,
                GREATEST(0,
                    COALESCE(SUM(credit) FILTER (WHERE entry_type = 'payment'), 0)
                    - COALESCE(SUM(credit) FILTER (WHERE entry_type = 'return'), 0)
                ) AS total_paid,
                COUNT(*) FILTER (WHERE entry_type = 'invoice') AS total_invoices,
                COUNT(*) FILTER (WHERE entry_type = 'payment') AS total_payments
            FROM customer_ledger
            WHERE customer_id = ? AND user_id = current_setting('app.current_user_id', true)::uuid
        ");
        $stmt->execute([$customerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total_purchases' => (float)($row['total_purchases'] ?? 0),
            'total_paid'      => (float)($row['total_paid'] ?? 0),
            'total_invoices'  => (int)($row['total_invoices'] ?? 0),
            'total_payments'  => (int)($row['total_payments'] ?? 0),
        ];
    }
}
