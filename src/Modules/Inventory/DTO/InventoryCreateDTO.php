<?php
declare(strict_types=1);

namespace Modules\Inventory\DTO;

/**
 * InventoryCreateDTO — validated input for creating a new stock batch.
 */
final class InventoryCreateDTO
{
    public function __construct(
        public readonly string $productId,
        public readonly string $batchNumber,
        public readonly string $vendorName,
        public readonly int $initialQty,
        public readonly float $costPrice,
        public readonly float $sellingPrice,
        public readonly float $retailPrice,
        public readonly ?string $createdAt,
    ) {
    }
}
