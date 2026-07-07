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

    public function getProductsWithStock(): array
    {
        $uid = "current_setting('app.current_user_id')::uuid";
        $stmt = $this->db->prepare("
            SELECT DISTINCT p.id, p.name, c.name AS category
            FROM products p
            JOIN categories c ON c.id = p.category_id
            JOIN inventory_batches ib ON ib.product_id = p.id
                AND ib.remaining_qty > 0
                AND ib.user_id = {$uid}
            WHERE p.user_id = {$uid}
            ORDER BY p.name ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCatalogAvgVelocity(): float
    {
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
    }

    public function getProductAnalytics(string $productId): ProductHistoryAnalytics
    {
        $uid = "current_setting('app.current_user_id')::uuid";
        $avgVelocity = $this->getCatalogAvgVelocity();

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
        $revenue30d   = (float) $sales['revenue_30d'];
        $lastSaleDate = $sales['last_sale'];
        $firstSaleDate = $sales['first_sale'];

        // Averages
        $avgDaily7d  = $sold7d > 0 ? round($sold7d / 7, 1) : 0;
        $avgDaily30d = $sold30d > 0 ? round($sold30d / 30, 1) : 0;
        $avgDaily90d = $sold90d > 0 ? round($sold90d / 90, 1) : 0;

        // Velocity uses 30d average
        $velocity = $avgDaily30d;

        // Trend: weekSpeed vs monthSpeed (spec: 1.1x / 0.9x thresholds)
        $weekSpeed  = $avgDaily7d;
        $monthSpeed = $avgDaily30d;
        if ($monthSpeed > 0 && $weekSpeed > 0) {
            if ($weekSpeed > $monthSpeed * 1.1) {
                $trendText = 'up';
            } elseif ($weekSpeed < $monthSpeed * 0.9) {
                $trendText = 'down';
            } else {
                $trendText = 'steady';
            }
        } elseif ($weekSpeed > 0 && $monthSpeed === 0) {
            $trendText = 'up'; // new sales momentum
        } else {
            $trendText = 'steady';
        }

        // Stock from batches (now with original_quantity for remainingPct)
        $stmt = $this->db->prepare("
            SELECT
                COUNT(*)                                                        AS batch_count,
                COALESCE(SUM(remaining_qty), 0)                                AS stock_left,
                COALESCE(SUM(cost_price * remaining_qty), 0)                   AS cost_value,
                COALESCE(SUM(selling_price * remaining_qty), 0)                AS sell_value,
                COALESCE(MAX(EXTRACT(DAY FROM NOW() - created_at)), 0)::int    AS max_age,
                COALESCE(AVG(selling_price - cost_price), 0)                   AS avg_unit_margin,
                COALESCE(SUM(original_quantity), 0)                            AS total_original,
                COALESCE(SUM(remaining_qty), 0)::float /
                    NULLIF(SUM(original_quantity), 0) * 100                     AS remaining_pct
            FROM inventory_batches
            WHERE product_id = ?
              AND user_id = {$uid}
              AND remaining_qty > 0
        ");
        $stmt->execute([$productId]);
        $stock = $stmt->fetch(PDO::FETCH_ASSOC);

        $stockLeft     = (int) $stock['stock_left'];
        $batchCount    = (int) $stock['batch_count'];
        $stockValue    = (float) $stock['cost_value'];
        $maxBatchAge   = (int) $stock['max_age'];
        $remainingPct  = $stock['remaining_pct'] !== null ? round((float) $stock['remaining_pct'], 1) : 100;

        // Margin: per-unit avg (sell - cost) / cost
        $avgCostPrice  = $stockLeft > 0 ? $stockValue / $stockLeft : 0;
        $totalSellValue = (float) $stock['sell_value'];
        $avgSellPrice  = $stockLeft > 0 ? $totalSellValue / $stockLeft : 0;
        $marginPct     = $avgCostPrice > 0 ? round((($avgSellPrice - $avgCostPrice) / $avgCostPrice) * 100, 1) : 0;

        // Days of supply
        $daysOfSupply = $avgDaily30d > 0 ? (int) round($stockLeft / $avgDaily30d) : null;

        // Reorder config from product
        $rop            = $prod['rop'] ? (int) $prod['rop'] : null;
        $emergencyStock = $prod['emergency_stock'] ? (int) $prod['emergency_stock'] : null;
        $leadTime       = $prod['lead_time'] ? (int) $prod['lead_time'] : null;

        $reorderStatus = $this->calcReorderStatus($stockLeft, $rop, $emergencyStock, $avgDaily30d, $leadTime);

        // ── Badges — using spec classification rules ──────────────────
        $badges = [];
        // 🔥 High Selling: velocity >= avgVelocity × 1.5
        if ($velocity >= $avgVelocity * 1.5) {
            $badges[] = 'high';
        }
        // 😐 Normal: between 0.5x and 1.5x — no badge needed
        // 📉 Low Selling: velocity > 0 AND velocity < avgVelocity × 0.5
        if ($velocity > 0 && $velocity < $avgVelocity * 0.5) {
            $badges[] = 'low';
        }
        // 💀 Dead Stock: velocity = 0 AND stockLeft > 0 AND last sale > 30d ago
        if ($velocity === 0 && $stockLeft > 0 && $lastSaleDate !== null) {
            $lastSaleTs = strtotime($lastSaleDate);
            if ($lastSaleTs !== false && $lastSaleTs < strtotime('-30 days')) {
                $badges[] = 'dead';
            }
        }
        // 📦 Old Stock: maxBatchAge >= 30 AND remainingPct >= 50% AND velocity < avgVelocity × 0.3
        if ($maxBatchAge >= 30 && $remainingPct >= 50 && $velocity < $avgVelocity * 0.3) {
            $badges[] = 'old';
        }
        // 🆕 New Product: velocity = 0 AND maxBatchAge < 30d
        if ($velocity === 0 && $maxBatchAge < 30) {
            $badges[] = 'new';
        }
        if ($stockLeft === 0) {
            $badges[] = 'out';
        }
        if ($reorderStatus === 'reorder_now' || $reorderStatus === 'emergency') {
            $badges[] = 'reorder';
        }

        // Alert
        $alert = $this->buildAlert($stockLeft, $avgDaily30d, $daysOfSupply, $remainingPct, $badges, $reorderStatus, $rop);

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
            trendPct:        null, // not used; trendText replaces it
            stockLeft:       $stockLeft,
            daysOfSupply:    $daysOfSupply,
            batchCount:      $batchCount,
            stockValue:      $stockValue,
            marginPct:       $marginPct,
            stockPct:        (int) $remainingPct,
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
            $effectiveRop = 10;
        }

        if ($stockLeft <= ($emergencyStock ?? 0)) return 'emergency';
        if ($stockLeft <= $effectiveRop) return 'reorder_now';
        if ($stockLeft <= $effectiveRop * 1.5) return 'reorder_soon';
        return 'ok';
    }

    private function buildAlert(int $stockLeft, float $avgDaily, ?int $daysOfSupply, float $remainingPct, array $badges, string $reorderStatus, ?int $rop): array
    {
        if ($stockLeft === 0) {
            return ['type' => 'critical', 'message' => 'OUT OF STOCK — Reorder immediately.'];
        }
        if ($reorderStatus === 'emergency') {
            return ['type' => 'critical', 'message' => "EMERGENCY — Stock at {$stockLeft} units. Below emergency level. Reorder NOW."];
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
        if (in_array('old', $badges)) {
            return ['type' => 'warning', 'message' => "Old stock — {$remainingPct}% unsold for {$stockLeft} units. Consider discounting."];
        }
        if (in_array('high', $badges) && $stockLeft > 5) {
            return ['type' => 'good', 'message' => 'Strong seller with healthy stock levels.'];
        }
        if ($remainingPct < 30 && $avgDaily > 0) {
            return ['type' => 'warning', 'message' => "Stock running low — {$stockLeft} units ({$daysOfSupply} days). Consider reordering."];
        }
        return ['type' => 'info', 'message' => 'OK'];
    }
}
