<?php
declare(strict_types=1);

namespace Modules\Inventory\Events\Listeners;

use Modules\Inventory\Events\InventoryEvent;
use Modules\Inventory\Events\InventoryEventListenerInterface;
use Modules\Notification\Cache\NotificationCacheService;

/**
 * NotificationEventListener — keeps the notification platform in sync with
 * inventory mutations by invalidating the per-user notification cache.
 *
 * The badge/notification payload is *computed* from current stock by the
 * InventoryAlertProvider on next read; we only invalidate here. This listener
 * never computes alert state itself.
 */
final class NotificationEventListener implements InventoryEventListenerInterface
{
    private NotificationCacheService $notificationCache;

    public function __construct(?NotificationCacheService $notificationCache = null)
    {
        $this->notificationCache = $notificationCache ?? new NotificationCacheService();
    }

    public function handle(InventoryEvent $event): void
    {
        $this->notificationCache->invalidateInventory();
    }
}
