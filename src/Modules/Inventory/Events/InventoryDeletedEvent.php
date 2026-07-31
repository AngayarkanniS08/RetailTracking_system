<?php
declare(strict_types=1);

namespace Modules\Inventory\Events;

final class InventoryDeletedEvent extends InventoryEvent
{
    public function name(): string
    {
        return 'inventory.deleted';
    }
}
