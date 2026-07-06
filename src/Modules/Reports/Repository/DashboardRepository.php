<?php
namespace Modules\Reports\Repository;

use PDO;
use Modules\Reports\Model\SalesPeriodSummary;
use Modules\Reports\Model\PurchasePeriodSummary;
use Modules\Reports\Model\TopProduct;
use Modules\Reports\Model\OldStockItem;
use Modules\Reports\Repository\Contract\DashboardRepositoryInterface;

class DashboardRepository implements DashboardRepositoryInterface
{
    private PDO $db;

    public function __construct()
    {
        $this->db = \Config\Database::getConnection();
    }

    public function getSalesSummary(string $period, \DateTimeImmutable $startDate): SalesPeriodSummary
    {
        $stmt = $this->db->prepare("
            SELECT
                COALESCE(SUM(grand_total), 0) AS revenue,
                COUNT(*)                        AS bills
            FROM invoices
            WHERE billed_at >= ?
              AND invoice_status = 'completed'
              AND user_id = current_setting('app.current_user_id')::uuid
        ");
        $stmt->execute([$startDate->format('Y-m-d H:i:s')]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $revenue = (float) $row['revenue'];
        $bills   = (int)   $row['bills'];
        $avg     = $bills > 0 ? round($revenue / $bills, 2) : 0;

        return new SalesPeriodSummary(
            revenue: $revenue,
            bills: $bills,
            avg: $avg
        );
    }

    public function getPurchaseSummary(string $period, \DateTimeImmutable $startDate): PurchasePeriodSummary
    {
        $stmt = $this->db->prepare("
            SELECT
                COALESCE(SUM(total_amount), 0) AS amount,
                COUNT(*)                        AS cnt,
                COALESCE(SUM(amount_paid), 0)  AS paid
            FROM vendor_purchases
            WHERE purchase_date >= ?
              AND user_id = current_setting('app.current_user_id')::uuid
        ");
        $stmt->execute([$startDate->format('Y-m-d H:i:s')]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return new PurchasePeriodSummary(
            amount: (float) $row['amount'],
            count:  (int)   $row['cnt'],
            paid:   (float) $row['paid']
        );
    }

    public function getHighSelling(int $limit = 5): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    ii.product_name_snapshot AS name,
                    COALESCE(SUM(ii.quantity), 0)::int         AS qty_sold,
                    COALESCE(SUM(ii.line_total), 0)            AS revenue
                FROM invoice_items ii
                JOIN invoices i ON i.id = ii.invoice_id
                WHERE i.user_id = current_setting('app.current_user_id')::uuid
                  AND i.invoice_status = 'completed'
                  AND i.billed_at >= DATE_TRUNC('month', NOW())
                GROUP BY ii.product_name_snapshot
                ORDER BY qty_sold DESC
                LIMIT :limit
            ");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return array_map(fn($r) => new TopProduct(
                name:    $r['name'],
                qtySold: (int) $r['qty_sold'],
                revenue: (float) $r['revenue']
            ), $rows);
        } catch (\Exception $e) {
            error_log('DashboardRepository::getHighSelling - ' . $e->getMessage());
            return [];
        }
    }

    public function getLowSelling(int $limit = 5): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    ii.product_name_snapshot AS name,
                    COALESCE(SUM(ii.quantity), 0)::int         AS qty_sold,
                    COALESCE(SUM(ii.line_total), 0)            AS revenue
                FROM invoice_items ii
                JOIN invoices i ON i.id = ii.invoice_id
                WHERE i.user_id = current_setting('app.current_user_id')::uuid
                  AND i.invoice_status = 'completed'
                  AND i.billed_at >= DATE_TRUNC('month', NOW())
                GROUP BY ii.product_name_snapshot
                ORDER BY qty_sold ASC
                LIMIT :limit
            ");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return array_map(fn($r) => new TopProduct(
                name:    $r['name'],
                qtySold: (int) $r['qty_sold'],
                revenue: (float) $r['revenue']
            ), $rows);
        } catch (\Exception $e) {
            error_log('DashboardRepository::getLowSelling - ' . $e->getMessage());
            return [];
        }
    }

    public function getOldStock(int $limit = 5): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    p.name,
                    ib.batch_number                          AS batch,
                    EXTRACT(DAY FROM NOW() - ib.created_at)::int AS age_days,
                    ib.remaining_qty::int                    AS qty
                FROM inventory_batches ib
                JOIN products p ON p.id = ib.product_id
                WHERE ib.user_id = current_setting('app.current_user_id')::uuid
                  AND ib.remaining_qty > 0
                  AND ib.created_at < NOW() - INTERVAL '30 days'
                ORDER BY age_days DESC
                LIMIT :limit
            ");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return array_map(fn($r) => new OldStockItem(
                name:    $r['name'],
                batch:   $r['batch'],
                ageDays: (int) $r['age_days'],
                qty:     (int) $r['qty']
            ), $rows);
        } catch (\Exception $e) {
            error_log('DashboardRepository::getOldStock - ' . $e->getMessage());
            return [];
        }
    }

    public function getTotalBills(): int
    {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*)
                FROM invoices
                WHERE invoice_status = 'completed'
                  AND user_id = current_setting('app.current_user_id')::uuid
            ");
            $stmt->execute();
            return (int) $stmt->fetchColumn();
        } catch (\Exception $e) {
            error_log('DashboardRepository::getTotalBills - ' . $e->getMessage());
            return 0;
        }
    }

    public function getOutstandingCredit(): float
    {
        try {
            $stmt = $this->db->prepare("
                SELECT COALESCE(SUM(balance_due), 0)
                FROM invoices
                WHERE invoice_status = 'completed'
                  AND balance_due > 0
                  AND user_id = current_setting('app.current_user_id')::uuid
            ");
            $stmt->execute();
            return (float) $stmt->fetchColumn();
        } catch (\Exception $e) {
            error_log('DashboardRepository::getOutstandingCredit - ' . $e->getMessage());
            return 0;
        }
    }

    public function getStockValue(): float
    {
        try {
            $stmt = $this->db->prepare("
                SELECT COALESCE(SUM(remaining_qty * cost_price), 0)
                FROM inventory_batches
                WHERE user_id = current_setting('app.current_user_id')::uuid
                  AND remaining_qty > 0
            ");
            $stmt->execute();
            return (float) $stmt->fetchColumn();
        } catch (\Exception $e) {
            error_log('DashboardRepository::getStockValue - ' . $e->getMessage());
            return 0;
        }
    }
}
