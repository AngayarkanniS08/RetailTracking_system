<?php

namespace Modules\Inventory\Notification;

use Modules\Inventory\Notification\Contract\NotificationDispatcherInterface;

class LogNotificationDispatcher implements NotificationDispatcherInterface
{
    public function dispatchLowStock(string $productId, int $stock, int $rop): void
    {
        error_log(
            "ALERT DISPATCH: Product {$productId} stock has dropped to {$stock} units " .
            "(ROP threshold is {$rop})."
        );
    }
}
