<?php
namespace Modules\Reports\Model;

class ProductHistoryAnalytics
{
    public function __construct(
        public string  $productId,
        public ?int    $displayId,
        public string  $productName,
        public string  $category,
        public ?string $subcategory,
        public string  $unit,
        public ?string $hsnCode,
        public float   $gstRate,
        // Sales
        public int     $sold7d,
        public int     $sold30d,
        public int     $sold90d,
        public float   $avgDaily7d,
        public float   $avgDaily30d,
        public float   $avgDaily90d,
        public float   $revenue30d,
        public float   $velocity,
        public ?string $lastSaleDate,
        public ?string $firstSaleDate,
        // Trend
        public ?float  $trendPct,
        // Stock
        public int     $stockLeft,
        public ?int    $daysOfSupply,
        public int     $batchCount,
        public float   $stockValue,
        public float   $marginPct,
        public int     $stockPct,
        public int     $maxBatchAgeDays,
        // Reorder
        public ?int    $reorderPoint,
        public ?int    $emergencyStock,
        public string  $reorderStatus,
        // Derived
        public array   $badges,
        public array   $alert
    ) {}
}
