<?php
namespace Modules\Inventory\Repository;

use PDO;
use Modules\Inventory\Notification\Contract\NotificationDispatcherInterface;
use Modules\Inventory\Notification\LogNotificationDispatcher;
use Config\Database;

class StockAlertHook
{
    private PDO $db;
    private NotificationDispatcherInterface $dispatcher;

    public function __construct(?NotificationDispatcherInterface $dispatcher = null)
    {
        $this->db         = Database::getConnection();
        // FIX: dispatcher is injected; falls back to the log implementation so
        // existing call-sites that pass nothing continue to work unchanged.
        $this->dispatcher = $dispatcher ?? new LogNotificationDispatcher();
    }

    /**
     * Evaluates whether a product's stock has crossed its ROP threshold and,
     * if so, sets alert_triggered and dispatches a notification.
     *
     * FIX: both inner queries now filter by user_id so this is safe to call
     * from background/batch processes that run outside a per-user HTTP session.
     * The update + dispatch decision is serialised inside a transaction to guard
     * against concurrent batch deducts firing duplicate notifications.
     *
     * @param string $productId  UUID of the product to evaluate
     * @param string $userId     UUID of the owning user (required for RLS scoping)
     */
    public function evaluateProductStockAlert(string $productId, string $userId): void
    {
        $inTransaction = $this->db->inTransaction();
        if (!$inTransaction) {
            $this->db->beginTransaction();
        }

        try {
            // 1. Fetch product ROP details — user-scoped, with a row lock to
            //    prevent a concurrent call from reading stale alert_triggered.
            $stmt = $this->db->prepare("
                SELECT rop, alert_triggered
                FROM public.products
                WHERE id      = ?
                  AND user_id = ?
                FOR UPDATE
            ");
            $stmt->execute([$productId, $userId]);
            $prod = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$prod || (int)$prod['rop'] <= 0) {
                if (!$inTransaction && $this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                return;
            }

            // 2. Sum current batch stocks — user-scoped
            $stmtStock = $this->db->prepare("
                SELECT COALESCE(SUM(b.remaining_qty), 0)
                FROM public.inventory_batches b
                JOIN public.products p ON p.id = b.product_id
                WHERE b.product_id = ?
                  AND p.user_id    = ?
            ");
            $stmtStock->execute([$productId, $userId]);
            $currentStock = (int)$stmtStock->fetchColumn();

            // 3. Trigger only if stock is at/below ROP and not already triggered
            if ($currentStock <= (int)$prod['rop'] && !(bool)$prod['alert_triggered']) {
                // 3a. Persist trigger state
                $stmtTrigger = $this->db->prepare("
                    UPDATE public.products
                    SET alert_triggered = TRUE
                    WHERE id      = ?
                      AND user_id = ?
                ");
                $stmtTrigger->execute([$productId, $userId]);

                if (!$inTransaction) {
                    $this->db->commit();
                }

                // 3b. Dispatch outside the transaction — notification failure must
                //     not roll back the trigger state update.
                $this->dispatcher->dispatchLowStock($productId, $currentStock, (int)$prod['rop']);
            } else {
                if (!$inTransaction) {
                    $this->db->commit();
                }
            }
        } catch (\Exception $e) {
            if (!$inTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}