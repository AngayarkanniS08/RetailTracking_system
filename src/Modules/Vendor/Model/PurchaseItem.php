<?php

namespace Modules\Vendor\Model;

class PurchaseItem
{
    public function __construct(
        public ?string $id,
        public string  $purchaseId,
        public string  $productId,
        public float   $quantity,
        public float   $unitPrice,
        public ?float  $totalPrice = null,
        public ?string $productName = null,
        public float   $gstRate = 0
    ) {}
}
