<?php
namespace Modules\Reports\Repository\Contract;

use Modules\Reports\Model\SalesPeriodSummary;
use Modules\Reports\Model\PurchasePeriodSummary;
use Modules\Reports\Model\TopProduct;
use Modules\Reports\Model\OldStockItem;

interface DashboardRepositoryInterface
{
    public function getSalesSummary(string $period, \DateTimeImmutable $startDate): SalesPeriodSummary;
    public function getPurchaseSummary(string $period, \DateTimeImmutable $startDate): PurchasePeriodSummary;
    public function getHighSelling(int $limit = 5): array;
    public function getLowSelling(int $limit = 5): array;
    public function getOldStock(int $limit = 5): array;
    public function getTotalBills(): int;
    public function getOutstandingCredit(): float;
    public function getStockValue(): float;
    public function getCatalogAvgVelocity(): float;
}
