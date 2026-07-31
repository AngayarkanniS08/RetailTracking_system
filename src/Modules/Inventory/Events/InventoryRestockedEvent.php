<?php
declare(strict_types=1);

namespace Modules\Inventory\Events;

final class InventoryRestockedEvent extends InventoryEvent
{
    public function name(): string
    {
        return 'inventory.restocked';
    }
}
