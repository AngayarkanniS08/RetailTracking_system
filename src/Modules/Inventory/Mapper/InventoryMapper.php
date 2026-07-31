<?php
declare(strict_types=1);

namespace Modules\Inventory\Mapper;

use Modules\Inventory\DTO\InventoryBatchDTO;
use Modules\Inventory\DTO\InventoryDetailsDTO;
use Modules\Inventory\DTO\InventorySummaryDTO;

/**
 * InventoryMapper — hydrates read-model DTOs from database rows.
 *
 * Pure mapping: no business decisions. Stock status is computed upstream by
 * InventoryService::calculateStockStatus() and passed in, never derived here.
 */
final class InventoryMapper
{
    /**
     * @param array<string, mixed> $row         Raw repository row
     * @param array<string, mixed> $stockStatus {stock_status, severity, status_text, status_badge_class}
     */
    public function toBatchDTO(array $row, array $stockStatus): InventoryBatchDTO
    {
        return new InventoryBatchDTO(
            id:                (string)($row['id'] ?? ''),
            productId:         (string)($row['product_id'] ?? ''),
            productName:       (string)($row['product_name'] ?? ''),
            displayId:         isset($row['display_id']) ? (string)$row['display_id'] : null,
            batchNumber:       (string)($row['batch_number'] ?? ''),
            vendorName:        isset($row['vendor_name']) ? (string)$row['vendor_name'] : null,
            unit:              (string)($row['unit'] ?? 'pcs'),
            initialQty:        (int)($row['initial_qty'] ?? 0),
            quantity:          (int)($row['quantity'] ?? 0),
            originalQuantity:  (int)($row['original_quantity'] ?? $row['initial_qty'] ?? 0),
            costPrice:         (float)($row['cost_price'] ?? $row['purchase_price'] ?? 0),
            sellingPrice:      (float)($row['selling_price'] ?? 0),
            retailPrice:       (float)($row['retail_price'] ?? 0),
            createdAt:         isset($row['created_at']) ? (string)$row['created_at'] : null,
            updatedAt:         isset($row['updated_at']) ? (string)$row['updated_at'] : null,
            reorderLevel:      (int)($row['rop'] ?? $row['reorder_level'] ?? 0),
            emergencyStock:    (int)($row['emergency_stock'] ?? 0),
            stockStatus:       (string)($stockStatus['stock_status'] ?? 'IN_STOCK'),
            severity:          (string)($stockStatus['severity'] ?? 'info'),
            statusText:        (string)($stockStatus['status_text'] ?? 'In Stock'),
            statusBadgeClass:  (string)($stockStatus['status_badge_class'] ?? 'bg-success'),
        );
    }

    /**
     * @param array<string, mixed> $stats
     */
    public function toSummaryDTO(array $stats): InventorySummaryDTO
    {
        return new InventorySummaryDTO(
            totalStockValue:  (float)($stats['total_stock_value'] ?? 0),
            totalBatches:     (int)($stats['total_batches'] ?? 0),
            lowStockCount:    (int)($stats['low_stock_count'] ?? 0),
            outOfStockCount:  (int)($stats['out_of_stock_count'] ?? 0),
            stockSoldValue:   (float)($stats['stock_sold_value'] ?? 0),
            costOfGoodsSold:  (float)($stats['cost_of_goods_sold'] ?? 0),
        );
    }

    /**
     * @param array<string, int> $recommendation
     */
    public function toDetailsDTO(InventoryBatchDTO $batch, float $inventoryValue, array $recommendation): InventoryDetailsDTO
    {
        return new InventoryDetailsDTO(
            batch:                   $batch,
            inventoryValue:          $inventoryValue,
            recommendedTarget:       $recommendation['recommended_target'] ?? 0,
            maximumCapacity:         $recommendation['maximum_capacity'] ?? 0,
            remaining:               $recommendation['remaining'] ?? $batch->quantity,
            deficit:                 $recommendation['deficit'] ?? 0,
            recommendedOrderQuantity:$recommendation['recommended_order_quantity'] ?? 0,
        );
    }
}
