<?php
declare(strict_types=1);

namespace Modules\Inventory\Events;

/**
 * InventoryEventListenerInterface — contract for inventory event consumers.
 */
interface InventoryEventListenerInterface
{
    public function handle(InventoryEvent $event): void;
}
