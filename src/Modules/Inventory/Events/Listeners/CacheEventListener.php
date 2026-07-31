<?php
declare(strict_types=1);

namespace Modules\Inventory\Events\Listeners;

use Modules\Inventory\Cache\InventoryCacheService;
use Modules\Inventory\Events\InventoryEvent;
use Modules\Inventory\Events\InventoryEventListenerInterface;

/**
 * CacheEventListener — centralises read-model cache invalidation for the
 * inventory, POS search, notification and dashboard domains. No other layer
 * performs cache invalidation.
 */
final class CacheEventListener implements InventoryEventListenerInterface
{
    private InventoryCacheService $cache;

    public function __construct(?InventoryCacheService $cache = null)
    {
        $this->cache = $cache ?? new InventoryCacheService();
    }

    public function handle(InventoryEvent $event): void
    {
        $this->cache->invalidateAll(['event' => $event->name(), 'user' => $event->userId]);
    }
}
