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
        $highSelling = $this->repo->getHighSelling(5);
        $lowSelling  = $this->repo->getLowSelling(5);
        $oldStock    = $this->repo->getOldStock(5);

        $map = fn($items) => array_map(fn($m) => [
            'name'    => $m->name,
            'qty_sold' => $m->qtySold,
            'revenue' => $m->revenue,
        ], $items);

        $mapOld = fn($items) => array_map(fn($m) => [
            'name'     => $m->name,
            'batch'    => $m->batch,
            'age_days' => $m->ageDays,
            'qty'      => $m->qty,
        ], $items);

        return [
            'high_selling' => $map($highSelling),
            'low_selling'  => $map($lowSelling),
            'old_stock'    => $mapOld($oldStock),
        ];
    }
}
