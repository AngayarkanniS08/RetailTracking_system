<?php

namespace Modules\Inventory\DTO;

class AlertDTO
{
    public function __construct(
        public readonly string $productId,
        public readonly int    $dailySales,
        public readonly int    $leadTime,
        public readonly int    $emergencyStock
    ) {
    }
}
