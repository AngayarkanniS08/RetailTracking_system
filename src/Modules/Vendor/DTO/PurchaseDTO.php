<?php
namespace Modules\Vendor\DTO;

class PurchaseDTO {
    public function __construct(
        public readonly string $vendorName,
        public readonly string $phone,
        public readonly string $purchaseDate,
        public readonly float $baseAmount,
        public readonly float $amountPaid,
        public readonly array $items // Array of PurchaseItemDTO
    
    ) {}

    public static function fromRequest(array $requestData): self
    {
        // 1. Map the child items array into strict DTO objects
        $mappedItems = [];
        foreach ($requestData['items'] ?? [] as $itemData) {
            $mappedItems[] = PurchaseItemDTO::fromArray($itemData);
        }

        // 2. Return the fully constructed, typed master DTO
        return new self(
            vendorName: $requestData['vendor_name'] ?? '',
            phone: $requestData['phone'] ?? '',
            purchaseDate: $requestData['purchase_date'] ?? date('Y-m-d'),
            baseAmount: (float)($requestData['base_amount'] ?? 0),
            amountPaid: (float)($requestData['amount_paid'] ?? 0),
            items: $mappedItems
        );
    }

}

