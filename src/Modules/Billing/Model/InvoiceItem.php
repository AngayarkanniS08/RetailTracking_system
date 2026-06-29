<?php

namespace Modules\Billing\Model;

class InvoiceItem
{
    public function __construct(
        public ?string $id,
        public string  $invoiceId,
        public string  $productId,
        public float   $quantity,
        public float   $unitPrice,
        public ?string $batchId = null,
        public ?string $productNameSnapshot = null,
        public ?string $hsnCodeSnapshot = null,
        public ?string $unitSnapshot = null,
        public ?float  $costPriceSnapshot = null,
        public float   $gstRateSnapshot = 0,
        public float   $gstAmount = 0,
        public float   $discountAmount = 0,
        public float   $lineTotal = 0,
        public ?string $createdAt = null
    ) {}
}
