<?php

namespace Modules\Billing\Model;

class Invoice {
    public function __construct(
        public ?string $id,
        public string  $userId,
        public string  $invoiceNumber,
        public ?string $customerId = null,
        public ?string $customerNameSnapshot = null,
        public ?string $customerPhoneSnapshot = null,
        public ?string $customerGstinSnapshot = null,
        public float   $subtotal = 0,
        public float   $discountAmount = 0,
        public float   $totalGst = 0,
        public float   $roundOff = 0,
        public float   $grandTotal = 0,
        public float   $amountPaid = 0,
        public float   $balanceDue = 0,
        public string  $invoiceStatus = 'completed',
        public string  $paymentStatus = 'paid',
        public ?string $notes = null,
        public ?string $billedAt = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
        public ?array  $items = null,
        public ?string $customerName = null,
        public ?string $customerPhone = null
    ) {}
}
