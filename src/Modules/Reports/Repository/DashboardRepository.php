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

    public function getCatalogAvgVelocity(): float
    {
        try {
            $stmt = $this->db->prepare("
                SELECT COALESCE(AVG(velocity), 0) FROM (
                    SELECT
                        SUM(ii.quantity)::float / 30.0 AS velocity
                    FROM products p
                    JOIN invoice_items ii ON ii.product_id = p.id
                    JOIN invoices i ON i.id = ii.invoice_id
                        AND i.invoice_status = 'completed'
                        AND i.billed_at >= NOW() - INTERVAL '30 days'
                    WHERE p.user_id = current_setting('app.current_user_id')::uuid
                    GROUP BY p.id
                    HAVING SUM(ii.quantity) > 0
                ) vel
            ");
            $stmt->execute();
            return (float) $stmt->fetchColumn();
        } catch (\Exception $e) {
            error_log('DashboardRepository::getCatalogAvgVelocity - ' . $e->getMessage());
            return 0;
        }
    }

    public function getHighSelling(int $limit = 5): array
    {
        try {
            $avgVel = $this->getCatalogAvgVelocity();
            $threshold = $avgVel * 1.5;

            $stmt = $this->db->prepare("
                WITH product_sales AS (
                    SELECT
                        p.id,
                        p.name,
                        COALESCE(SUM(ii.quantity), 0)::int AS qty_sold,
                        COALESCE(SUM(ii.line_total), 0)    AS revenue,
                        CASE WHEN SUM(ii.quantity) > 0
                            THEN SUM(ii.quantity)::float / 30.0
                            ELSE 0
                        END AS velocity
                    FROM products p
                    LEFT JOIN invoice_items ii ON ii.product_id = p.id
                    LEFT JOIN invoices i ON i.id = ii.invoice_id
                        AND i.invoice_status = 'completed'
                        AND i.billed_at >= NOW() - INTERVAL '30 days'
                    WHERE p.user_id = current_setting('app.current_user_id')::uuid
                    GROUP BY p.id, p.name
                )
                SELECT id, name, qty_sold, revenue, velocity
                FROM product_sales
                WHERE velocity >= :threshold
                ORDER BY velocity DESC
                LIMIT :limit
            ");
            $stmt->bindValue(':threshold', $threshold, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return array_map(fn($r) => new TopProduct(
                productId: $r['id'],
                name:      $r['name'],
                qtySold:   (int) $r['qty_sold'],
                revenue:   (float) $r['revenue'],
                velocity:  (float) $r['velocity']
            ), $rows);
        } catch (\Exception $e) {
            error_log('DashboardRepository::getHighSelling - ' . $e->getMessage());
            return [];
        }
    }

    public function getLowSelling(int $limit = 5): array
    {
        try {
            $avgVel = $this->getCatalogAvgVelocity();
            $threshold = $avgVel * 0.5;

            $stmt = $this->db->prepare("
                WITH product_sales AS (
                    SELECT
                        p.id,
                        p.name,
                        COALESCE(SUM(ii.quantity), 0)::int AS qty_sold,
                        COALESCE(SUM(ii.line_total), 0)    AS revenue,
                        CASE WHEN SUM(ii.quantity) > 0
                            THEN SUM(ii.quantity)::float / 30.0
                            ELSE 0
                        END AS velocity
                    FROM products p
                    LEFT JOIN invoice_items ii ON ii.product_id = p.id
                    LEFT JOIN invoices i ON i.id = ii.invoice_id
                        AND i.invoice_status = 'completed'
                        AND i.billed_at >= NOW() - INTERVAL '30 days'
                    WHERE p.user_id = current_setting('app.current_user_id')::uuid
                    GROUP BY p.id, p.name
                )
                SELECT id, name, qty_sold, revenue, velocity
                FROM product_sales
                WHERE velocity > 0 AND velocity < :threshold
                ORDER BY velocity ASC
                LIMIT :limit
            ");
            $stmt->bindValue(':threshold', $threshold, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return array_map(fn($r) => new TopProduct(
                productId: $r['id'],
                name:      $r['name'],
                qtySold:   (int) $r['qty_sold'],
                revenue:   (float) $r['revenue'],
                velocity:  (float) $r['velocity']
            ), $rows);
        } catch (\Exception $e) {
            error_log('DashboardRepository::getLowSelling - ' . $e->getMessage());
            return [];
        }
    }

    public function getOldStock(int $limit = 5): array
    {
        try {
            $avgVel = $this->getCatalogAvgVelocity();
            $velThreshold = $avgVel * 0.3;

            $stmt = $this->db->prepare("
                WITH batch_stock AS (
                    SELECT
                        p.id        AS product_id,
                        p.name,
                        ib.batch_number AS batch,
                        EXTRACT(DAY FROM NOW() - ib.created_at)::int AS age_days,
                        ib.remaining_qty::int  AS qty,
                        ib.original_quantity::int AS original_qty,
                        CASE WHEN ib.original_quantity > 0
                            THEN ROUND((ib.remaining_qty::float / ib.original_quantity) * 100, 1)
                            ELSE 100
                        END AS remaining_pct
                    FROM inventory_batches ib
                    JOIN products p ON p.id = ib.product_id
                    WHERE ib.user_id = current_setting('app.current_user_id')::uuid
                      AND ib.remaining_qty > 0
                ),
                product_velocity AS (
                    SELECT
                        ii.product_id,
                        CASE WHEN SUM(ii.quantity) > 0
                            THEN SUM(ii.quantity)::float / 30.0
                            ELSE 0
                        END AS velocity
                    FROM invoice_items ii
                    JOIN invoices i ON i.id = ii.invoice_id
                        AND i.invoice_status = 'completed'
                        AND i.billed_at >= NOW() - INTERVAL '30 days'
                    WHERE ii.product_id IN (SELECT product_id FROM batch_stock)
                    GROUP BY ii.product_id
                )
                SELECT
                    bs.product_id,
                    bs.name,
                    bs.batch,
                    bs.age_days,
                    bs.qty,
                    bs.original_qty,
                    bs.remaining_pct,
                    COALESCE(pv.velocity, 0) AS velocity
                FROM batch_stock bs
                LEFT JOIN product_velocity pv ON pv.product_id = bs.product_id
                WHERE bs.age_days >= 30
                  AND bs.remaining_pct >= 50
                  AND COALESCE(pv.velocity, 0) < :velThreshold
                ORDER BY bs.age_days DESC, bs.remaining_pct DESC
                LIMIT :limit
            ");
            $stmt->bindValue(':velThreshold', $velThreshold, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return array_map(fn($r) => new OldStockItem(
                productId:    $r['product_id'],
                name:         $r['name'],
                batch:        $r['batch'],
                ageDays:      (int) $r['age_days'],
                qty:          (int) $r['qty'],
                originalQty:  (int) $r['original_qty'],
                remainingPct: (float) $r['remaining_pct'],
                velocity:     (float) $r['velocity']
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
