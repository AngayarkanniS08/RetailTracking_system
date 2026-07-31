<?php

namespace Modules\Notification\Provider;

use Modules\Notification\DTO\NotificationDTO;
use Modules\Notification\Provider\Contract\NotificationProviderInterface;
use Modules\Notification\Repository\InventoryAlertRepository;

/**
 * InventoryAlertProvider — computes low / out-of-stock / critical-reorder /
 * aging / negative-stock notifications from live inventory health.
 *
 * Threshold rules (backend owns ALL business logic — the frontend renders only):
 *   - out_of_stock  : stock == 0                                  → critical
 *   - reorder       : 0 < stock <= emergency_stock (if configured) → critical
 *   - low_stock     : stock <= effective_rop (rop, or DEFAULT fallback) → warning
 *   - aging         : a batch still in stock is >= AGING_DAYS old → info
 *   - negative      : stock < 0                                   → critical
 *
 * The signature on each DTO is the current stock value, so the alert re-surfaces
 * as "unread" whenever the underlying stock changes after the user viewed it.
 */
class InventoryAlertProvider implements NotificationProviderInterface
{
    /** Fallback low-stock threshold when a product has no rop configured. */
    public const DEFAULT_LOW_STOCK_THRESHOLD = 10;

    /** Days after which non-empty stock is flagged as aging inventory. */
    public const AGING_DAYS = 30;

    private InventoryAlertRepository $repo;

    public function __construct(?InventoryAlertRepository $repo = null)
    {
        $this->repo = $repo ?? new InventoryAlertRepository();
    }

    public function getDomain(): string
    {
        return 'inventory';
    }

    /**
     * @return NotificationDTO[]
     */
    public function getNotifications(string $userId): array
    {
        $notifications = [];
        $rows = $this->repo->getProductStockHealth();

        foreach ($rows as $row) {
            $productId = (string) $row['id'];
            $name      = (string) ($row['name'] ?: 'Product');
            $unit      = (string) ($row['unit'] ?: 'pcs');
            $stock     = (float) $row['stock'];
            $rop       = (int) $row['rop'];
            $emergency = (int) $row['emergency_stock'];
            $ageDays   = (int) $row['oldest_batch_days'];
            $stockInt  = (int) $stock;

            $stockKey = sprintf('inventory:stock:%s:%s', $productId, (string) $stockInt);
            $actionUrl = '/products';

            if ($stock < 0) {
                $notifications[] = new NotificationDTO(
                    key:         "inventory:negative_stock:{$productId}",
                    type:        'inventory',
                    severity:    NotificationDTO::SEVERITY_CRITICAL,
                    title:       'Negative Stock Detected',
                    description: "{$name} has negative stock ({$stockInt} {$unit}). Reconcile the inventory.",
                    entityType:  'product',
                    entityId:    $productId,
                    actionUrl:   $actionUrl,
                    icon:        '⛔',
                    signature:   $stockKey,
                    metadata:    ['stock' => $stockInt, 'unit' => $unit],
                );
                continue;
            }

            if ($stock == 0) {
                $notifications[] = new NotificationDTO(
                    key:         "inventory:out_of_stock:{$productId}",
                    type:        'inventory',
                    severity:    NotificationDTO::SEVERITY_CRITICAL,
                    title:       'Out of Stock',
                    description: "{$name} is out of stock. Place a purchase order to restock.",
                    entityType:  'product',
                    entityId:    $productId,
                    actionUrl:   $actionUrl,
                    icon:        '⚠️',
                    signature:   $stockKey,
                    metadata:    ['stock' => 0, 'unit' => $unit, 'rop' => $rop],
                );
                continue;
            }

            $isReorder = $emergency > 0 && $stock <= $emergency;
            $effectiveRop = $rop > 0 ? $rop : self::DEFAULT_LOW_STOCK_THRESHOLD;

            if ($isReorder) {
                $notifications[] = new NotificationDTO(
                    key:         "inventory:reorder:{$productId}",
                    type:        'inventory',
                    severity:    NotificationDTO::SEVERITY_CRITICAL,
                    title:       'Reorder Now',
                    description: "{$name} has dropped to {$stockInt} {$unit} (emergency level {$emergency}). Restock immediately.",
                    entityType:  'product',
                    entityId:    $productId,
                    actionUrl:   $actionUrl,
                    icon:        '🚨',
                    signature:   $stockKey,
                    metadata:    ['stock' => $stockInt, 'unit' => $unit, 'emergency_stock' => $emergency],
                );
            } elseif ($stock <= $effectiveRop) {
                $notifications[] = new NotificationDTO(
                    key:         "inventory:low_stock:{$productId}",
                    type:        'inventory',
                    severity:    NotificationDTO::SEVERITY_WARNING,
                    title:       'Low Stock',
                    description: "{$name} has only {$stockInt} {$unit} remaining (reorder point {$effectiveRop}).",
                    entityType:  'product',
                    entityId:    $productId,
                    actionUrl:   $actionUrl,
                    icon:        '📉',
                    signature:   $stockKey,
                    metadata:    ['stock' => $stockInt, 'unit' => $unit, 'rop' => $effectiveRop],
                );
            }

            if ($ageDays >= self::AGING_DAYS) {
                $notifications[] = new NotificationDTO(
                    key:         "inventory:aging:{$productId}",
                    type:        'inventory',
                    severity:    NotificationDTO::SEVERITY_INFO,
                    title:       'Aging Inventory',
                    description: "{$name} has stock older than {$ageDays} days. Consider a discount or return.",
                    entityType:  'product',
                    entityId:    $productId,
                    actionUrl:   '/inventory',
                    icon:        '📦',
                    signature:   $stockKey,
                    metadata:    ['stock' => $stockInt, 'unit' => $unit, 'oldest_batch_days' => $ageDays],
                );
            }
        }

        // Deterministic order: critical → warning → info, then by key.
        $order = [
            NotificationDTO::SEVERITY_CRITICAL => 0,
            NotificationDTO::SEVERITY_WARNING  => 1,
            NotificationDTO::SEVERITY_INFO     => 2,
        ];
        usort($notifications, static function (NotificationDTO $a, NotificationDTO $b) use ($order): int {
            $bySeverity = ($order[$a->severity] ?? 9) <=> ($order[$b->severity] ?? 9);
            return $bySeverity !== 0 ? $bySeverity : strcmp($a->key, $b->key);
        });

        return $notifications;
    }
}
