<?php

namespace Modules\Inventory\Model;

class LowStockAlert
{
    public function __construct(
        public string $productId,
        public int    $dailySales,
        public int    $leadTime,
        public int    $emergencyStock,
        public int    $rop,
        public bool   $alertTriggered = false
    ) {
    }
}
