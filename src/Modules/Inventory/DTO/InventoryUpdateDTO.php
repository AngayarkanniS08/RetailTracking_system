<?php
declare(strict_types=1);

namespace Modules\Inventory\DTO;

/**
 * InventoryUpdateDTO — validated input for editing an existing batch.
 *
 * Only *editable* fields are allowed. Immutable fields (id, user_id, product_id,
 * created_at) are rejected by InventoryValidator, not here.
 *
 * `expectedUpdatedAt` provides optimistic concurrency: when supplied, the
 * service refuses the write if the stored row changed since it was loaded.
 */
final class InventoryUpdateDTO
{
    public function __construct(
        public readonly string $batchId,
        public readonly string $batchNumber,
        public readonly string $vendorName,
        public readonly int $quantity,
        public readonly float $costPrice,
        public readonly float $sellingPrice,
        public readonly float $retailPrice,
        public readonly ?string $createdAt,
        public readonly ?string $expectedUpdatedAt = null,
    ) {
    }
}
