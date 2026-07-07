<?php
namespace Modules\Reports\Repository\Contract;

use Modules\Reports\Model\ProductHistoryAnalytics;

interface ProductHistoryRepositoryInterface
{
    public function getProductAnalytics(string $productId): ProductHistoryAnalytics;
    public function getDailySales(string $productId, int $limit = 30): array;
    public function upsertDailySale(string $productId, string $saleDate, int $quantity, ?string $notes): void;
    public function deleteDailySale(string $saleId): void;
}
