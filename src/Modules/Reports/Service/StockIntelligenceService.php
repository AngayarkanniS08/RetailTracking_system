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
            'product_id'  => $m->productId,
            'name'        => $m->name,
            'qty_sold'    => $m->qtySold,
            'revenue'     => $m->revenue,
            'velocity'    => $m->velocity,
            'stock_status' => $m->stockStatus,
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

        $health = $this->repo->getInventoryHealthCounts();

        $hasInventory = $health['total_products'] > 0;
        $hasAlerts = $health['out_of_stock'] > 0 || $health['low_stock'] > 0 || count($oldStock) > 0;

        if (!$hasInventory) {
            $alertStatus = 'no_data';
        } elseif ($hasAlerts) {
            $alertStatus = 'has_alerts';
        } else {
            $alertStatus = 'healthy';
        }

        return [
            'high_selling'    => $map($highSelling),
            'low_selling'     => $map($lowSelling),
            'normal_selling'  => $map($normalSelling),
            'new_products'    => $map($newProducts),
            'old_stock'       => $mapOld($oldStock),
            'avg_velocity'    => $avgVelocity,
            'inventory_health' => [
                'total_products'  => $health['total_products'],
                'out_of_stock'    => $health['out_of_stock'],
                'low_stock'       => $health['low_stock'],
                'healthy_count'   => max(0, $health['total_products'] - $health['out_of_stock'] - $health['low_stock']),
            ],
            'alert_summary' => [
                'status' => $alertStatus,
            ],
        ];
    }
}
