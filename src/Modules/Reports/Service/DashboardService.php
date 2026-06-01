<?php
namespace Modules\Reports\Service;

use Config\Database;
use PDO;

/**
 * DashboardService — queries sales and purchase summary statistics.
 *
 * NOTE: The sales/purchases tables may not exist yet in early development.
 * All queries are wrapped in try-catch and return zero-valued fallbacks
 * so the dashboard renders without errors even on an empty database.
 */
class DashboardService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Returns sales + purchase summaries for today, this week, and this month.
     */
    public function getSalesSummary(): array
    {
        $result = [
            'today'          => ['revenue' => 0, 'bills' => 0, 'avg' => 0],
            'week'           => ['revenue' => 0, 'bills' => 0, 'avg' => 0],
            'month'          => ['revenue' => 0, 'bills' => 0, 'avg' => 0],
            'purchase_week'  => ['amount' => 0, 'count' => 0, 'paid' => 0],
            'purchase_month' => ['amount' => 0, 'count' => 0, 'paid' => 0],
        ];

        try {
            // Sales summary — grouped by period
            $sql = "
                SELECT
                    CASE
                        WHEN DATE(created_at) = CURRENT_DATE         THEN 'today'
                        WHEN created_at >= DATE_TRUNC('week',  NOW()) THEN 'week'
                        WHEN created_at >= DATE_TRUNC('month', NOW()) THEN 'month'
                        ELSE NULL
                    END AS period,
                    COALESCE(SUM(total_amount), 0) AS revenue,
                    COUNT(*)                        AS bills
                FROM sales
                WHERE created_at >= DATE_TRUNC('month', NOW())
                  AND user_id = (current_setting('app.current_user_id', true))::uuid
                GROUP BY period
                HAVING
                    CASE
                        WHEN DATE(created_at) = CURRENT_DATE         THEN 'today'
                        WHEN created_at >= DATE_TRUNC('week',  NOW()) THEN 'week'
                        WHEN created_at >= DATE_TRUNC('month', NOW()) THEN 'month'
                        ELSE NULL
                    END IS NOT NULL
            ";
            $stmt = $this->db->query($sql);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $period  = $row['period'];
                $revenue = (float) $row['revenue'];
                $bills   = (int)   $row['bills'];
                if (isset($result[$period])) {
                    $result[$period] = [
                        'revenue' => $revenue,
                        'bills'   => $bills,
                        'avg'     => $bills > 0 ? round($revenue / $bills, 2) : 0,
                    ];
                }
            }
        } catch (\Exception $e) {
            // sales table doesn't exist yet — return zeroes silently
            error_log('DashboardService::getSalesSummary - ' . $e->getMessage());
        }

        try {
            // Purchase summary — week and month
            $sql = "
                SELECT
                    CASE
                        WHEN created_at >= DATE_TRUNC('week',  NOW()) THEN 'purchase_week'
                        WHEN created_at >= DATE_TRUNC('month', NOW()) THEN 'purchase_month'
                        ELSE NULL
                    END AS period,
                    COALESCE(SUM(total_amount), 0) AS amount,
                    COUNT(*)                        AS cnt,
                    COALESCE(SUM(paid_amount), 0)  AS paid
                FROM purchases
                WHERE created_at >= DATE_TRUNC('month', NOW())
                  AND user_id = (current_setting('app.current_user_id', true))::uuid
                GROUP BY period
                HAVING
                    CASE
                        WHEN created_at >= DATE_TRUNC('week',  NOW()) THEN 'purchase_week'
                        WHEN created_at >= DATE_TRUNC('month', NOW()) THEN 'purchase_month'
                        ELSE NULL
                    END IS NOT NULL
            ";
            $stmt = $this->db->query($sql);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $period = $row['period'];
                if (isset($result[$period])) {
                    $result[$period] = [
                        'amount' => (float) $row['amount'],
                        'count'  => (int)   $row['cnt'],
                        'paid'   => (float) $row['paid'],
                    ];
                }
            }
        } catch (\Exception $e) {
            error_log('DashboardService::getPurchaseSummary - ' . $e->getMessage());
        }

        return $result;
    }
}
