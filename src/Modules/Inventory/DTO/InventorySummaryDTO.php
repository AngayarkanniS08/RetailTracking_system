<?php
declare(strict_types=1);

namespace Modules\Inventory\DTO;

/**
 * InventorySummaryDTO — immutable aggregate stats read model.
 */
final class InventorySummaryDTO
{
    public function __construct(
        public readonly float $totalStockValue,
        public readonly int $totalBatches,
        public readonly int $lowStockCount,
        public readonly int $outOfStockCount,
        public readonly float $stockSoldValue,
        public readonly float $costOfGoodsSold,
    ) {
    }

    /**
     * @return array<string, int|float>
     */
    public function toArray(): array
    {
        return [
            'total_stock_value'   => $this->totalStockValue,
            'total_batches'       => $this->totalBatches,
            'low_stock_count'     => $this->lowStockCount,
            'out_of_stock_count'  => $this->outOfStockCount,
            'stock_sold_value'    => $this->stockSoldValue,
            'cost_of_goods_sold'  => $this->costOfGoodsSold,
        ];
    }
}
