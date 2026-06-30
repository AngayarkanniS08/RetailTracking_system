<?php
namespace Modules\Customer\Model;

class CreditPayment
{
    public function __construct(
        public ?string $id,
        public string  $userId,
        public string  $customerId,
        public string  $ledgerId,
        public string  $receiptNumber,
        public float   $amount,
        public ?string $notes = null,
        public ?string $createdAt = null
    ) {}
}
