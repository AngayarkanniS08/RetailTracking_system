<?php

namespace Modules\Notification\Repository;

use PDO;
use Config\Database;

/**
 * InventoryAlertRepository — raw data access for the inventory alert provider.
 *
 * Produces exactly ONE user-scoped query per refresh. Business classification
 * (low/out/critical/negative/aging) lives in InventoryAlertProvider so this
 * repository stays a dumb read model and can be safely cached.
 */
class InventoryAlertRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Per-product current stock health: summed remaining_qty across batches,
     * configured thresholds (rop, emergency_stock) and the age of the oldest
     * batch still carrying stock (used for aging alerts).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getProductStockHealth(): array
    {
        $stmt = $this->db->prepare("
            WITH product_stock AS (
                SELECT
                    p.id,
                    p.name,
                    p.unit,
                    p.rop,
                    p.emergency_stock,
                    p.alert_triggered,
                    COALESCE(SUM(b.remaining_qty), 0)::numeric(14,2) AS stock,
                    MIN(CASE WHEN b.remaining_qty > 0 THEN b.created_at END) AS oldest_batch_at,
                    COUNT(b.id) FILTER (WHERE b.remaining_qty > 0)::int AS active_batch_count
                FROM public.products p
                LEFT JOIN public.inventory_batches b
                    ON b.product_id = p.id
                   AND b.user_id = current_setting('app.current_user_id', true)::uuid
                WHERE p.user_id = current_setting('app.current_user_id', true)::uuid
                GROUP BY p.id, p.name, p.unit, p.rop, p.emergency_stock, p.alert_triggered
            )
            SELECT
                id,
                name,
                unit,
                COALESCE(rop, 0)            AS rop,
                COALESCE(emergency_stock, 0) AS emergency_stock,
                alert_triggered,
                stock,
                COALESCE(EXTRACT(DAY FROM (NOW() - oldest_batch_at))::int, 0) AS oldest_batch_days
            FROM product_stock
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
