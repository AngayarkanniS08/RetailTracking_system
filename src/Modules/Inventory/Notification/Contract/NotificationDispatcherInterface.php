<?php

namespace Modules\Inventory\Notification\Contract;

interface NotificationDispatcherInterface
{
    /**
     * Send a low-stock notification for the given product.
     *
     * @param string $productId  UUID of the affected product
     * @param int    $stock      Current remaining quantity
     * @param int    $rop        Configured reorder point threshold
     */
    public function dispatchLowStock(string $productId, int $stock, int $rop): void;
}
