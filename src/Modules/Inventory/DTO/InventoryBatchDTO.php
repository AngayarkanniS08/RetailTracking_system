<?php
declare(strict_types=1);

namespace Modules\Inventory\DTO;

/**
 * InventoryBatchDTO — immutable read model for a single inventory batch row.
 *
 * Contains only hydrated values + a serializable stock status (computed by
 * InventoryService::calculateStockStatus(), never by the repository).
 */
final class InventoryBatchDTO
{
    public function __construct(
        public readonly string $id,
        public readonly string $productId,
        public readonly string $productName,
        public readonly ?string $displayId,
        public readonly string $batchNumber,
        public readonly ?string $vendorName,
        public readonly string $unit,
        public readonly int $initialQty,
        public readonly int $quantity,
        public readonly int $originalQuantity,
        public readonly float $costPrice,
        public readonly float $sellingPrice,
        public readonly float $retailPrice,
        public readonly ?string $createdAt,
        public readonly ?string $updatedAt,
        public readonly int $reorderLevel,
        public readonly int $emergencyStock,
        public readonly string $stockStatus,
        public readonly string $severity,
        public readonly string $statusText,
        public readonly string $statusBadgeClass,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'                => $this->id,
            'product_id'        => $this->productId,
            'product_name'      => $this->productName,
            'display_id'        => $this->displayId,
            'batch_number'      => $this->batchNumber,
            'vendor_name'       => $this->vendorName,
            'unit'              => $this->unit,
            'initial_qty'       => $this->initialQty,
            'quantity'          => $this->quantity,
            'original_quantity' => $this->originalQuantity,
            'cost_price'        => $this->costPrice,
            'selling_price'     => $this->sellingPrice,
            'retail_price'      => $this->retailPrice,
            'created_at'        => $this->createdAt,
            'updated_at'        => $this->updatedAt,
            'reorder_level'     => $this->reorderLevel,
            'emergency_stock'   => $this->emergencyStock,
            'stock_status'      => $this->stockStatus,
            'severity'          => $this->severity,
            'status_text'       => $this->statusText,
            'status_badge_class'=> $this->statusBadgeClass,
        ];
    }
}
