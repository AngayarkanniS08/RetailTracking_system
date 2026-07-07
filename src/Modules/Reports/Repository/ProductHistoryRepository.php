<?php
namespace Modules\Reports\Repository;

use PDO;
use Modules\Reports\Model\ProductHistoryAnalytics;
use Modules\Reports\Repository\Contract\ProductHistoryRepositoryInterface;

class ProductHistoryRepository implements ProductHistoryRepositoryInterface
{
    private PDO $db;

    public function __construct()
    {
        $this->db = \Config\Database::getConnection();
    }

    public function getProductAnalytics(string $productId): ProductHistoryAnalytics
    {
        $uid = "current_setting('app.current_user_id')::uuid";

        // Product base info + reorder config
        $stmt = $this->db->prepare("
            SELECT
                p.id,
                p.name,
                c.name AS category,
                sc.name AS subcategory,
                p.unit,
                p.hsn_code,
                p.gst_rate,
                p.daily_sales,
                p.lead_time,
                p.emergency_stock,
                p.rop
            FROM products p
            JOIN categories c ON c.id = p.category_id
            LEFT JOIN subcategories sc ON sc.id = p.subcategory_id
            WHERE p.id = ?
              AND p.user_id = {$uid}
        ");
        $stmt->execute([$productId]);
        $prod = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$prod) {
            throw new \RuntimeException('Product not found');
        }

        // Sales in 7d, 30d, 90d periods + first/last sale
        $stmt = $this->db->prepare("
            SELECT
                COALESCE(SUM(ii.quantity) FILTER (WHERE i.billed_at >= NOW() - INTERVAL '7 days'), 0)    AS sold_7d,
                COALESCE(SUM(ii.quantity) FILTER (WHERE i.billed_at >= NOW() - INTERVAL '30 days'), 0)   AS sold_30d,
                COALESCE(SUM(ii.quantity) FILTER (WHERE i.billed_at >= NOW() - INTERVAL '90 days'), 0)   AS sold_90d,
                COALESCE(SUM(ii.quantity) FILTER (WHERE i.billed_at >= NOW() - INTERVAL '60 days' AND i.billed_at < NOW() - INTERVAL '30 days'), 0) AS sold_60_30d,
                COALESCE(SUM(ii.line_total) FILTER (WHERE i.billed_at >= NOW() - INTERVAL '30 days'), 0) AS revenue_30d,
                MAX(i.billed_at) FILTER (WHERE i.invoice_status = 'completed') AS last_sale,
                MIN(i.billed_at) FILTER (WHERE i.invoice_status = 'completed') AS first_sale
            FROM invoice_items ii
            JOIN invoices i ON i.id = ii.invoice_id
            WHERE ii.product_id = ?
              AND i.invoice_status = 'completed'
              AND i.user_id = {$uid}
        ");
        $stmt->execute([$productId]);
        $sales = $stmt->fetch(PDO::FETCH_ASSOC);

        $sold7d       = (int) $sales['sold_7d'];
        $sold30d      = (int) $sales['sold_30d'];
        $sold90d      = (int) $sales['sold_90d'];
        $sold60_30d   = (int) $sales['sold_60_30d'];
        $revenue30d   = (float) $sales['revenue_30d'];
        $lastSaleDate = $sales['last_sale'];
        $firstSaleDate = $sales['first_sale'];

        // Averages
        $avgDaily7d  = $sold7d > 0 ? round($sold7d / 7, 1) : 0;
        $avgDaily30d = $sold30d > 0 ? round($sold30d / 30, 1) : 0;
        $avgDaily90d = $sold90d > 0 ? round($sold90d / 90, 1) : 0;

        // Velocity uses 30d average
        $velocity = $avgDaily30d;

        // Trend: compare last 30d vs the 30d before that
        $trendPct = null;
        if ($sold60_30d > 0) {
            $trendPct = round((($sold30d - $sold60_30d) / $sold60_30d) * 100, 1);
        } elseif ($sold30d > 0 && $sold60_30d === 0) {
            $trendPct = 100.0; // new sales where there were none
        }

        // Stock from batches
        $stmt = $this->db->prepare("
            SELECT
                COUNT(*)                                                        AS batch_count,
                COALESCE(SUM(remaining_qty), 0)                                AS stock_left,
                COALESCE(SUM(cost_price * remaining_qty), 0)                   AS cost_value,
                COALESCE(SUM(selling_price * remaining_qty), 0)                AS sell_value,
                COALESCE(SUM(selling_price - cost_price), 0)                   AS unit_margin_sum,
                COUNT(*) FILTER (WHERE remaining_qty > 0)                      AS active_batches,
                COALESCE(MAX(EXTRACT(DAY FROM NOW() - created_at)), 0)::int    AS max_age,
                COALESCE(AVG(selling_price - cost_price), 0)                   AS avg_unit_margin
            FROM inventory_batches
            WHERE product_id = ?
              AND user_id = {$uid}
              AND remaining_qty > 0
        ");
        $stmt->execute([$productId]);
        $stock = $stmt->fetch(PDO::FETCH_ASSOC);

        $stockLeft   = (int) $stock['stock_left'];
        $batchCount  = (int) $stock['active_batches'];
        $stockValue  = (float) $stock['cost_value'];
        $maxBatchAge = (int) $stock['max_age'];

        // Margin: use avg unit margin (stable, not scaled by stock quantity)
        $avgUnitMargin = (float) $stock['avg_unit_margin'];
        $avgSellPrice = $batchCount > 0 ? $stockValue / $stockLeft : 0;
        // Actually let's compute avg cost price per unit from batches
        $avgCostPrice = $stockLeft > 0 ? $stockValue / $stockLeft : 0;
        // Avg selling price per unit
        $totalSellValue = (float) $stock['sell_value'];
        $avgSellPrice = $stockLeft > 0 ? $totalSellValue / $stockLeft : 0;

        $marginPct = $avgCostPrice > 0 ? round((($avgSellPrice - $avgCostPrice) / $avgCostPrice) * 100, 1) : 0;

        // Days of supply
        $daysOfSupply = $avgDaily30d > 0 ? (int) round($stockLeft / $avgDaily30d) : null;

        // Stock percentage (stock vs 30d total demand)
        $totalDemand = $stockLeft + $sold30d;
        $stockPct    = $totalDemand > 0 ? (int) round(($stockLeft / $totalDemand) * 100) : 100;
        $stockPct    = min(100, max(0, $stockPct));

        // Reorder config from product
        $rop            = $prod['rop'] ? (int) $prod['rop'] : null;
        $emergencyStock = $prod['emergency_stock'] ? (int) $prod['emergency_stock'] : null;
        $leadTime       = $prod['lead_time'] ? (int) $prod['lead_time'] : null;
        $dailySales     = $prod['daily_sales'] ? (int) $prod['daily_sales'] : null;

        // Reorder status
        $reorderStatus = $this->calcReorderStatus($stockLeft, $rop, $emergencyStock, $avgDaily30d, $leadTime);

        // Badges
        $badges = [];
        if ($sold30d > 10) $badges[] = 'high';
        if ($sold30d <= 2 && $sold30d > 0) $badges[] = 'low';
        if ($sold30d === 0 && $stockLeft > 0) $badges[] = 'dead';
        if ($maxBatchAge >= 5) $badges[] = 'old';
        if ($stockLeft === 0) $badges[] = 'out';
        if ($reorderStatus === 'reorder_now') $badges[] = 'reorder';

        // Alert
        $alert = $this->buildAlert($stockLeft, $avgDaily30d, $daysOfSupply, $stockPct, $sold30d, $badges, $reorderStatus, $rop);

        return new ProductHistoryAnalytics(
            productId:       $prod['id'],
            productName:     $prod['name'],
            category:        $prod['category'],
            subcategory:     $prod['subcategory'],
            unit:            $prod['unit'],
            hsnCode:         $prod['hsn_code'],
            gstRate:         (float) $prod['gst_rate'],
            sold7d:          $sold7d,
            sold30d:         $sold30d,
            sold90d:         $sold90d,
            avgDaily7d:      $avgDaily7d,
            avgDaily30d:     $avgDaily30d,
            avgDaily90d:     $avgDaily90d,
            revenue30d:      $revenue30d,
            velocity:        $velocity,
            lastSaleDate:    $lastSaleDate,
            firstSaleDate:   $firstSaleDate,
            trendPct:        $trendPct,
            stockLeft:       $stockLeft,
            daysOfSupply:    $daysOfSupply,
            batchCount:      $batchCount,
            stockValue:      $stockValue,
            marginPct:       $marginPct,
            stockPct:        $stockPct,
            maxBatchAgeDays: $maxBatchAge,
            reorderPoint:    $rop,
            emergencyStock:  $emergencyStock,
            reorderStatus:   $reorderStatus,
            badges:          $badges,
            alert:           $alert
        );
    }

    public function getDailySales(string $productId, int $limit = 30): array
    {
        $stmt = $this->db->prepare("
            SELECT id, sale_date, quantity, notes, created_at
            FROM product_daily_sales
            WHERE product_id = ?
              AND user_id = current_setting('app.current_user_id')::uuid
            ORDER BY sale_date DESC
            LIMIT ?
        ");
        $stmt->execute([$productId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function upsertDailySale(string $productId, string $saleDate, int $quantity, ?string $notes): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO product_daily_sales (user_id, product_id, sale_date, quantity, notes)
            VALUES (current_setting('app.current_user_id')::uuid, ?, ?, ?, ?)
            ON CONFLICT (user_id, product_id, sale_date)
            DO UPDATE SET quantity = EXCLUDED.quantity,
                          notes   = COALESCE(EXCLUDED.notes, product_daily_sales.notes),
                          updated_at = now()
        ");
        $stmt->execute([$productId, $saleDate, $quantity, $notes]);
    }

    public function deleteDailySale(string $saleId): void
    {
        $stmt = $this->db->prepare("
            DELETE FROM product_daily_sales
            WHERE id = ?
              AND user_id = current_setting('app.current_user_id')::uuid
        ");
        $stmt->execute([$saleId]);
        if ($stmt->rowCount() === 0) {
            throw new \RuntimeException('Daily sale entry not found');
        }
    }

    private function calcReorderStatus(int $stockLeft, ?int $rop, ?int $emergencyStock, float $avgDaily, ?int $leadTime): string
    {
        if ($stockLeft === 0) return 'out_of_stock';

        $effectiveRop = $rop;
        if ($effectiveRop === null && $avgDaily > 0 && $leadTime !== null) {
            $effectiveRop = (int) ceil($avgDaily * $leadTime);
        }
        if ($effectiveRop === null) {
            $effectiveRop = 10; // sensible default
        }

        if ($stockLeft <= ($emergencyStock ?? 0)) return 'emergency';
        if ($stockLeft <= $effectiveRop) return 'reorder_now';
        if ($stockLeft <= $effectiveRop * 1.5) return 'reorder_soon';

        return 'ok';
    }

    private function buildAlert(int $stockLeft, float $avgDaily, ?int $daysOfSupply, int $stockPct, int $sold30d, array $badges, string $reorderStatus, ?int $rop): array
    {
        if ($stockLeft === 0) {
            return ['type' => 'critical', 'message' => 'OUT OF STOCK — Reorder immediately.'];
        }
        if ($reorderStatus === 'emergency') {
            return ['type' => 'critical', 'message' => "EMERGENCY — Stock at {$stockLeft} units. Below emergency stock level. Reorder NOW."];
        }
        if ($reorderStatus === 'reorder_now') {
            $atRop = $rop !== null ? " (ROP: {$rop})" : '';
            return ['type' => 'critical', 'message' => "Stock below reorder point{$atRop} — {$stockLeft} units left. Place order now."];
        }
        if ($reorderStatus === 'reorder_soon' && $avgDaily > 0) {
            return ['type' => 'warning', 'message' => "Stock approaching reorder point — {$stockLeft} units ({$daysOfSupply} days of supply). Plan reorder."];
        }
        if (in_array('dead', $badges)) {
            return ['type' => 'warning', 'message' => 'No sales in 30 days. Review pricing or consider discontinuing.'];
        }
        if (in_array('high', $badges) && $stockLeft > 5) {
            return ['type' => 'good', 'message' => 'Strong seller with healthy stock levels.'];
        }
        if ($stockPct <= 30 && $avgDaily > 0) {
            return ['type' => 'warning', 'message' => "Stock running low — {$stockLeft} units ({$daysOfSupply} days). Consider reordering."];
        }
        return ['type' => 'info', 'message' => 'OK'];
    }
}
