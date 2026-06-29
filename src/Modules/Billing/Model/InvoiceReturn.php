<?php

namespace Modules\Billing\Model;

class InvoiceReturn
{
    public function __construct(
        public ?string $id,
        public string  $invoiceId,
        public string  $productId,
        public float   $qtyReturned,
        public float   $refundAmount,
        public ?string $invoiceItemId = null,
        public ?string $batchId = null,
        public float   $restockQty = 0,
        public ?string $reason = null,
        public ?string $createdAt = null
    ) {}
}
