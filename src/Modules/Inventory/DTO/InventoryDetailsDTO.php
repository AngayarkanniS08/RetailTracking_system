<?php
declare(strict_types=1);

namespace Modules\Inventory\DTO;

/**
 * InventoryDetailsDTO — full detail view for a single batch including the
 * current stock status, computed inventory value and a restock recommendation.
 *
 * The recommendation is produced by InventoryService (backend business logic),
 * never by the client.
 */
final class InventoryDetailsDTO
{
    public function __construct(
        public readonly InventoryBatchDTO $batch,
        public readonly float $inventoryValue,
        public readonly int $recommendedTarget,
        public readonly int $maximumCapacity,
        public readonly int $remaining,
        public readonly int $deficit,
        public readonly int $recommendedOrderQuantity,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_merge($this->batch->toArray(), [
            'inventory_value'        => $this->inventoryValue,
            'restock_recommendation' => [
                'recommended_target'          => $this->recommendedTarget,
                'maximum_capacity'            => $this->maximumCapacity,
                'remaining'                   => $this->remaining,
                'deficit'                     => $this->deficit,
                'recommended_order_quantity'  => $this->recommendedOrderQuantity,
            ],
        ]);
    }
}
