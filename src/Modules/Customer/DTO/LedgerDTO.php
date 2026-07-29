<?php
namespace Modules\Customer\DTO;

class CreditSaleItemDTO
{
    public function __construct(
        public readonly string $productId,
        public readonly float  $qty,
        public readonly float  $price,
        public readonly ?string $productName = null
    ) {}
}

class CreditSaleDTO
{
    /** @param CreditSaleItemDTO[] $items */
    public function __construct(
        public readonly string $customerId,
        public readonly array  $items,
        public readonly ?string $notes = null
    ) {}

    public static function fromRequest(array $data): self
    {
        $items = array_map(
            fn($i) => new CreditSaleItemDTO(
                productId: $i['product_id'] ?? '',
                qty: (float)($i['qty'] ?? $i['quantity'] ?? 1),
                price: (float)($i['price'] ?? $i['unit_price'] ?? 0),
                productName: $i['product_name'] ?? null
            ),
            $data['items'] ?? []
        );

        return new self(
            customerId: $data['customer_id'] ?? '',
            items: $items,
            notes: $data['notes'] ?? null
        );
    }
}

class LedgerQueryDTO
{
    public function __construct(
        public readonly string $customerId,
        public readonly int    $limit = 50,
        public readonly int    $offset = 0,
        public readonly ?string $type = null
    ) {}

    public static function fromRequest(string $customerId, array $query): self
    {
        return new self(
            customerId: $customerId,
            limit: (int)($query['limit'] ?? 50),
            offset: (int)($query['offset'] ?? 0),
            type: $query['type'] ?? null
        );
    }
}
