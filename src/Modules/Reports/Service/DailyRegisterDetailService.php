<?php
declare(strict_types=1);

namespace Modules\Reports\Service;

use PDO;
use Config\Database;

class DailyRegisterDetailService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    public function getDailyDetail(string $date, int $page = 1, int $limit = 50): array
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }

        $summarySql = "
            SELECT 
                COUNT(*) FILTER (WHERE i.invoice_status != 'cancelled') AS forms_generated,
                COUNT(*) FILTER (WHERE i.invoice_status = 'cancelled') AS cancelled_forms_count,
                COALESCE(SUM(i.grand_total) FILTER (WHERE i.invoice_status != 'cancelled'), 0.00) AS gross_revenue,
                COALESCE(SUM(i.discount_amount) FILTER (WHERE i.invoice_status != 'cancelled'), 0.00) AS total_discounts,
                COALESCE(SUM(i.total_gst) FILTER (WHERE i.invoice_status != 'cancelled'), 0.00) AS total_tax,
                COALESCE(SUM(i.amount_paid) FILTER (WHERE i.invoice_status != 'cancelled'), 0.00) AS cash_collected,
                COALESCE(SUM(i.balance_due) FILTER (WHERE i.invoice_status != 'cancelled'), 0.00) AS credit_issued
            FROM invoices i
            WHERE (i.billed_at AT TIME ZONE 'Asia/Kolkata')::date = ?
              AND i.user_id = current_setting('app.current_user_id', true)::uuid
        ";

        $stmt = $this->db->prepare($summarySql);
        $stmt->execute([$date]);
        $summaryRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $formsGenerated     = (int)   ($summaryRow['forms_generated'] ?? 0);
        $cancelledCount     = (int)   ($summaryRow['cancelled_forms_count'] ?? 0);
        $grossRevenue       = (float) ($summaryRow['gross_revenue'] ?? 0);
        $totalDiscounts     = (float) ($summaryRow['total_discounts'] ?? 0);
        $totalTax           = (float) ($summaryRow['total_tax'] ?? 0);
        $cashCollected      = (float) ($summaryRow['cash_collected'] ?? 0);
        $creditIssued       = (float) ($summaryRow['credit_issued'] ?? 0);
        $avgFormValue       = $formsGenerated > 0 ? round($grossRevenue / $formsGenerated, 2) : 0.0;

        $offset = max(0, ($page - 1) * $limit);
        $invoicesSql = "
            SELECT 
                i.id,
                i.invoice_number,
                i.customer_id,
                COALESCE(i.customer_name_snapshot, 'Walk-in Customer') AS customer_name,
                COALESCE(i.customer_phone_snapshot, '') AS customer_phone,
                COALESCE(u.full_name, u.username, 'System Operator') AS operator_name,
                i.subtotal,
                i.discount_amount,
                i.total_gst,
                i.grand_total,
                i.amount_paid,
                i.balance_due,
                i.invoice_status,
                i.payment_status,
                i.notes,
                i.billed_at AT TIME ZONE 'Asia/Kolkata' AS billed_at,
                (
                    SELECT string_agg(ii.product_name_snapshot || ' (x' || trim(to_char(ii.quantity, 'FM999999990.###')) || ')', ', ')
                    FROM invoice_items ii
                    WHERE ii.invoice_id = i.id
                ) AS items_summary
            FROM invoices i
            LEFT JOIN users u ON i.user_id = u.id
            WHERE (i.billed_at AT TIME ZONE 'Asia/Kolkata')::date = ?
              AND i.user_id = current_setting('app.current_user_id', true)::uuid
            ORDER BY i.billed_at DESC
            LIMIT ? OFFSET ?
        ";

        $invQueryParams = [$date, $limit, $offset];
        $invStmt = $this->db->prepare($invoicesSql);
        $invStmt->execute($invQueryParams);
        $rawInvoices = $invStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $formattedInvoices = array_map(function ($inv) {
            return [
                'id'             => $inv['id'],
                'invoice_number' => $inv['invoice_number'],
                'customer_name'  => $inv['customer_name'],
                'customer_phone' => $inv['customer_phone'],
                'operator_name'  => $inv['operator_name'],
                'items_summary'  => $inv['items_summary'] ?: '1 item',
                'grand_total'    => (float) $inv['grand_total'],
                'amount_paid'    => (float) $inv['amount_paid'],
                'balance_due'    => (float) $inv['balance_due'],
                'invoice_status' => strtoupper($inv['invoice_status']),
                'payment_status' => strtoupper($inv['payment_status']),
                'billed_at'      => $inv['billed_at'],
                'notes'          => $inv['notes'],
            ];
        }, $rawInvoices);

        return [
            'date'     => $date,
            'summary'  => [
                'forms_generated'       => $formsGenerated,
                'cancelled_forms_count' => $cancelledCount,
                'gross_revenue'         => $grossRevenue,
                'total_discounts'       => $totalDiscounts,
                'total_tax'             => $totalTax,
                'cash_collected'        => $cashCollected,
                'credit_issued'         => $creditIssued,
                'avg_form_value'        => $avgFormValue,
            ],
            'invoices'   => $formattedInvoices,
            'pagination' => [
                'total'     => $formsGenerated + $cancelledCount,
                'page'      => $page,
                'per_page'  => $limit,
                'total_pages' => max(1, (int) ceil(($formsGenerated + $cancelledCount) / $limit)),
            ],
        ];
    }
}
