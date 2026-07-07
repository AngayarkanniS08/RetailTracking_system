<?php
namespace Modules\Reports\Service;

use Modules\Reports\Repository\Contract\DashboardRepositoryInterface;

class StockIntelligenceService
{
    private DashboardRepositoryInterface $repo;

    public function __construct(DashboardRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    public function getStockIntel(): array
    {
        $highSelling = $this->repo->getHighSelling(10);
        $lowSelling  = $this->repo->getLowSelling(10);
        $normalSelling = $this->repo->getNormalSelling(10);
        $newProducts = $this->repo->getNewProducts(10);
        $oldStock    = $this->repo->getOldStock(10);
        $avgVelocity = $this->repo->getCatalogAvgVelocity();

        $map = fn($items) => array_map(fn($m) => [
            'product_id' => $m->productId,
            'name'       => $m->name,
            'qty_sold'   => $m->qtySold,
            'revenue'    => $m->revenue,
            'velocity'   => $m->velocity,
        ], $items);

        $mapOld = fn($items) => array_map(fn($m) => [
            'product_id'  => $m->productId,
            'name'        => $m->name,
            'batch'       => $m->batch,
            'age_days'    => $m->ageDays,
            'qty'         => $m->qty,
            'remaining_pct' => $m->remainingPct,
            'velocity'    => $m->velocity,
        ], $items);

        return [
            'high_selling'  => $map($highSelling),
            'low_selling'   => $map($lowSelling),
            'normal_selling' => $map($normalSelling),
            'new_products'  => $map($newProducts),
            'old_stock'     => $mapOld($oldStock),
            'avg_velocity'  => $avgVelocity,
        ];
    }
}
