<?php

namespace Modules\Vendor\Model;

class Purchase {
    public function __construct(
        public ?string $id,
        public string $vendorId,
        public string $status,
        public ?array $items,
        public ?float $baseAmount = null,
        public ?float $totalAmount = null,
        public ?float $amountPaid = null,
        public ?string $purchaseDate = null,
        public ?string $userId = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
        public ?string $vendorName = null,
        public ?string $vendorPhone = null,
        public ?int $totalOrders = null
    ) {}
}