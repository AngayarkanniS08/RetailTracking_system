<?php
namespace Modules\Customer\Model;

class Customer
{
    public function __construct(
        public ?string $id,
        public string  $userId,
        public string  $name,
        public string  $phone,
        public ?string $email = null,
        public ?string $gstin = null,
        public ?string $address = null,
        public float   $creditLimit = 0,
        public float   $openingBalance = 0,
        public string  $status = 'active',
        public ?string $createdAt = null,
        public ?string $updatedAt = null
    ) {}
}
