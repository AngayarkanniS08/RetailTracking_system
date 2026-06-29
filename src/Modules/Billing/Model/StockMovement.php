<?php

namespace Modules\Billing\Model;

class StockMovement
{
    public function __construct(
        public ?string $id,
        public string  $userId,
        public string  $productId,
        public string  $referenceType,
        public string  $movementType,
        public float   $qty,
        public ?string $batchId = null,
        public ?string $referenceId = null,
        public ?string $createdAt = null
    ) {}
}
