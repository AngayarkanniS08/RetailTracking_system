<?php
namespace Modules\Billing\DTO;

class InvoiceItemDTO {
    public function __construct(
        public readonly string $productId,
        public readonly float  $quantity,
        public readonly float  $unitPrice,
        public readonly ?string $batchId = null,
        public readonly float  $discountAmount = 0
    ) {}

    public static function fromArray(array $data): self {
        return new self(
            productId: $data['product_id'] ?? '',
            quantity: (float)($data['quantity'] ?? 0),
            unitPrice: (float)($data['unit_price'] ?? 0),
            batchId: $data['batch_id'] ?? null,
            discountAmount: (float)($data['discount_amount'] ?? 0)
        );
    }
}
