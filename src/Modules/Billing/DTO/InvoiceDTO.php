<?php
namespace Modules\Billing\DTO;

class InvoiceDTO {
    public function __construct(
        public readonly ?string $customerId,
        public readonly ?string $customerName,
        public readonly ?string $customerPhone,
    public readonly bool    $applyGst,
    public readonly float   $discountAmount,
    public readonly float   $amountPaid,
    public readonly float   $expectedGrandTotal,
    public readonly array   $items,
    public readonly ?string $notes = null
    ) {}

    public static function fromRequest(array $requestData): self
    {
        $mappedItems = [];
        foreach ($requestData['items'] ?? [] as $itemData) {
            $mappedItems[] = InvoiceItemDTO::fromArray($itemData);
        }

        return new self(
            customerId: $requestData['customer_id'] ?? null,
            customerName: $requestData['customer_name'] ?? null,
            customerPhone: $requestData['customer_phone'] ?? null,
            applyGst: (bool)($requestData['apply_gst'] ?? true),
            discountAmount: (float)($requestData['discount_amount'] ?? 0),
            amountPaid: (float)($requestData['amount_paid'] ?? 0),
            expectedGrandTotal: (float)($requestData['expected_grand_total'] ?? 0),
            items: $mappedItems,
            notes: $requestData['notes'] ?? null
        );
    }
}
