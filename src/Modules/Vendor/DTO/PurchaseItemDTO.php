<?php
namespace Modules\Vendor\DTO;

class PurchaseItemDTO {
    public function __construct(
        public readonly string $productId,
        public readonly float $quantity,
        public readonly float $unitPrice
    ){}

    public static function fromArray(array $data): self {
        return new self(
            productId: $data['product_id'] ?? '',
            quantity: (float)($data['quantity'] ?? 0),
            unitPrice: (float)($data['unit_price'] ?? 0.0)
        );
    }
}
