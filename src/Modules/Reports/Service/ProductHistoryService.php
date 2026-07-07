<?php
namespace Modules\Reports\Service;

use Modules\Reports\Repository\Contract\ProductHistoryRepositoryInterface;

class ProductHistoryService
{
    private ProductHistoryRepositoryInterface $repo;

    public function __construct(ProductHistoryRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    public function getProductAnalytics(string $productId): array
    {
        $a = $this->repo->getProductAnalytics($productId);
        return [
            'product' => [
                'id'          => $a->productId,
                'name'        => $a->productName,
                'category'    => $a->category,
                'subcategory' => $a->subcategory,
                'unit'        => $a->unit,
                'hsn'         => $a->hsnCode,
                'gst'         => $a->gstRate,
            ],
            'analytics' => [
                'sold_7d'           => $a->sold7d,
                'sold_30d'          => $a->sold30d,
                'sold_90d'          => $a->sold90d,
                'avg_daily_7d'      => $a->avgDaily7d,
                'avg_daily_30d'     => $a->avgDaily30d,
                'avg_daily_90d'     => $a->avgDaily90d,
                'revenue_30d'       => $a->revenue30d,
                'velocity'          => $a->velocity,
                'last_sale_date'    => $a->lastSaleDate,
                'first_sale_date'   => $a->firstSaleDate,
                'trend_pct'         => $a->trendPct,
                'stock_left'        => $a->stockLeft,
                'days_of_supply'    => $a->daysOfSupply,
                'batch_count'       => $a->batchCount,
                'stock_value'       => $a->stockValue,
                'margin_pct'        => $a->marginPct,
                'stock_pct'         => $a->stockPct,
                'max_batch_age_days'=> $a->maxBatchAgeDays,
                'reorder_point'     => $a->reorderPoint,
                'emergency_stock'   => $a->emergencyStock,
                'reorder_status'    => $a->reorderStatus,
            ],
            'badges' => $a->badges,
            'alert'  => $a->alert,
        ];
    }

    public function getDailySales(string $productId): array
    {
        return $this->repo->getDailySales($productId);
    }

    public function upsertDailySale(string $productId, string $saleDate, int $quantity, ?string $notes): void
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantity must be positive');
        }
        $this->repo->upsertDailySale($productId, $saleDate, $quantity, $notes);
    }

    public function deleteDailySale(string $saleId): void
    {
        $this->repo->deleteDailySale($saleId);
    }
}
