<?php
declare(strict_types=1);

namespace Modules\Inventory\DTO;

/**
 * InventoryRestockDTO — validated restock operation.
 *
 * Carries the *additive* quantity (add_quantity) which the client submits.
 * Total quantity = current remaining_qty + add_quantity is computed by
 * InventoryService, never by the frontend.
 */
final class InventoryRestockDTO
{
    public function __construct(
        public readonly string $batchId,
        public readonly int $addQuantity,
        public readonly string $reason = '',
    ) {
    }
}
