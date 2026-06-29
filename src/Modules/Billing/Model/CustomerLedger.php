<?php

namespace Modules\Billing\Model;

class CustomerLedger
{
    public function __construct(
        public ?string $id,
        public string  $userId,
        public string  $customerId,
        public string  $entryType,
        public float   $debit,
        public float   $credit,
        public float   $balance,
        public ?string $invoiceId = null,
        public ?string $notes = null,
        public ?string $createdAt = null
    ) {}
}
