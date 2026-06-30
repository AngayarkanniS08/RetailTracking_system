<?php
namespace Modules\Customer\DTO;

class CustomerDTO
{
    public function __construct(
        public readonly string  $name,
        public readonly string  $phone,
        public readonly ?string $email = null,
        public readonly ?string $gstin = null,
        public readonly ?string $address = null,
        public readonly float   $creditLimit = 0,
        public readonly float   $openingBalance = 0,
        public readonly ?string $status = 'active'
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            name: trim($data['name'] ?? ''),
            phone: trim($data['phone'] ?? ''),
            email: isset($data['email']) ? trim($data['email']) : null,
            gstin: isset($data['gstin']) ? strtoupper(trim($data['gstin'])) : null,
            address: isset($data['address']) ? trim($data['address']) : null,
            creditLimit: (float)($data['credit_limit'] ?? 0),
            openingBalance: (float)($data['opening_balance'] ?? 0),
            status: $data['status'] ?? 'active'
        );
    }
}
