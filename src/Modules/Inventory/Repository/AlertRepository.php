<?php

namespace Modules\Inventory\Repository;

use PDO;
use Modules\Inventory\Repository\Contract\AlertRepositoryInterface;
use Modules\Inventory\Model\LowStockAlert;
use Config\Database;

class AlertRepository implements AlertRepositoryInterface
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Returns all products where current stock is at or below their configured ROP.
     *
     * NOTE: This query is intentionally independent of alert_triggered.
     * Its purpose is to reflect live stock health for the UI badge and banner.
     * The one-shot alert_triggered flag (used to prevent notification spam) is
     * managed separately by evaluateProductStockAlert().
     */
    public function findAllActive(): array
    {
        $stmt = $this->db->prepare("
            SELECT
                p.id              AS product_id,
                p.name            AS product_name,
                p.unit,
                p.daily_sales,
                p.lead_time,
                p.emergency_stock,
                p.rop,
                p.alert_triggered,
                COALESCE(SUM(b.remaining_qty), 0) AS current_stock
            FROM public.products p
            LEFT JOIN public.inventory_batches b ON b.product_id = p.id
            WHERE p.user_id = current_setting('app.current_user_id')::uuid
              AND p.rop > 0
            GROUP BY p.id
            HAVING COALESCE(SUM(b.remaining_qty), 0) <= p.rop
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * FIX: fetch a single product's current ROP fields.
     * Used by AlertService to compare the incoming ROP against the stored value
     * before deciding whether to reset alert_triggered.
     */
    public function findByProductId(string $productId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, rop, alert_triggered
            FROM public.products
            WHERE id = ?
              AND user_id = current_setting('app.current_user_id')::uuid
        ");
        $stmt->execute([$productId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Persists ROP configuration for a product.
     *
     * FIX: wrapped in a transaction so a partial failure cannot leave the row
     * in an inconsistent state (important if this method is later extended to
     * also write to an audit log or trigger table).
     */
    public function save(LowStockAlert $model): array
    {
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare("
                UPDATE public.products
                SET daily_sales     = ?,
                    lead_time       = ?,
                    emergency_stock = ?,
                    rop             = ?,
                    alert_triggered = ?,
                    updated_at      = now()
                WHERE id      = ?
                  AND user_id = current_setting('app.current_user_id')::uuid
                RETURNING id, daily_sales, lead_time, emergency_stock, rop, alert_triggered
            ");

            $stmt->execute([
                $model->dailySales,
                $model->leadTime,
                $model->emergencyStock,
                $model->rop,
                $model->alertTriggered ? 1 : 0,
                $model->productId,
            ]);

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$result) {
                throw new \Exception(
                    "Product alert parameters save failed — check Product ID ownership."
                );
            }

            $this->db->commit();
            return $result;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Soft-disables an alert by zeroing all ROP fields.
     * FIX: renamed from delete() — no row is removed; this is a soft-reset.
     */
    public function disable(string $productId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE public.products
            SET daily_sales     = 0,
                lead_time       = 0,
                emergency_stock = 0,
                rop             = 0,
                alert_triggered = FALSE,
                updated_at      = now()
            WHERE id      = ?
              AND user_id = current_setting('app.current_user_id')::uuid
        ");
        $stmt->execute([$productId]);
        return $stmt->rowCount() > 0;
    }
}
