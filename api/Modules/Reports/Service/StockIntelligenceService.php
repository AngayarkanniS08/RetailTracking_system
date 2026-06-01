<?php
namespace Modules\Reports\Service;

use Config\Database;
use PDO;

/**
 * StockIntelligenceService — queries high/low-selling products and old stock.
 *
 * All queries are wrapped in try-catch: tables like sales_items / inventory_batches
 * may not exist yet and we return empty arrays rather than crashing.
 */
class StockIntelligenceService
{
    private PDO $db;
    private int $limit = 5;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Returns { high_selling, low_selling, old_stock }.
     */
    public function getStockIntel(): array
    {
        return [
            'high_selling' => $this->getHighSelling(),
            'low_selling'  => $this->getLowSelling(),
            'old_stock'    => $this->getOldStock(),
        ];
    }

    private function getHighSelling(): array
    {
        try {
            $sql = "
                SELECT
                    p.name,
                    COALESCE(SUM(si.quantity), 0)             AS qty_sold,
                    COALESCE(SUM(si.quantity * si.unit_price), 0) AS revenue
                FROM products p
                JOIN sales_items si ON si.product_id = p.id
                JOIN sales s        ON s.id = si.sale_id
                WHERE s.user_id = (current_setting('app.current_user_id', true))::uuid
                  AND s.created_at >= DATE_TRUNC('month', NOW())
                GROUP BY p.id, p.name
                ORDER BY qty_sold DESC
                LIMIT :limit
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':limit', $this->limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log('StockIntelligenceService::getHighSelling - ' . $e->getMessage());
            return [];
        }
    }

    private function getLowSelling(): array
    {
        try {
            $sql = "
                SELECT
                    p.name,
                    COALESCE(SUM(si.quantity), 0)                 AS qty_sold,
                    COALESCE(SUM(si.quantity * si.unit_price), 0) AS revenue
                FROM products p
                JOIN sales_items si ON si.product_id = p.id
                JOIN sales s        ON s.id = si.sale_id
                WHERE s.user_id = (current_setting('app.current_user_id', true))::uuid
                  AND s.created_at >= DATE_TRUNC('month', NOW())
                GROUP BY p.id, p.name
                ORDER BY qty_sold ASC
                LIMIT :limit
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':limit', $this->limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log('StockIntelligenceService::getLowSelling - ' . $e->getMessage());
            return [];
        }
    }

    private function getOldStock(): array
    {
        try {
            // Old stock = inventory batches older than 30 days with remaining qty > 0
            $sql = "
                SELECT
                    p.name,
                    ib.batch_number    AS batch,
                    EXTRACT(DAY FROM NOW() - ib.created_at)::int AS age_days,
                    ib.remaining_qty   AS qty
                FROM inventory_batches ib
                JOIN products p ON p.id = ib.product_id
                WHERE ib.user_id = (current_setting('app.current_user_id', true))::uuid
                  AND ib.remaining_qty > 0
                  AND ib.created_at < NOW() - INTERVAL '30 days'
                ORDER BY age_days DESC
                LIMIT :limit
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':limit', $this->limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log('StockIntelligenceService::getOldStock - ' . $e->getMessage());
            return [];
        }
    }
}
