<?php
namespace Modules\Customer\DTO;

class CreditPaymentDTO
{
    public function __construct(
        public readonly string $customerId,
        public readonly float  $amount,
        public readonly ?string $notes = null
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            customerId: $data['customer_id'] ?? '',
            amount: (float)($data['amount'] ?? 0),
            notes: $data['notes'] ?? null
        );
    }
}
