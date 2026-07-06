<?php
namespace Modules\Reports\Service;

use Modules\Reports\Repository\Contract\DashboardRepositoryInterface;

class DashboardService
{
    private DashboardRepositoryInterface $repo;

    public function __construct(DashboardRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    public function getSalesSummary(): array
    {
        $now = new \DateTimeImmutable('now');
        $todayStart = $now->setTime(0, 0, 0);

        $dayOfWeek = (int) $now->format('N');
        $weekStart = $now->modify('-' . ($dayOfWeek - 1) . ' days')->setTime(0, 0, 0);

        $monthStart = $now->modify('first day of this month')->setTime(0, 0, 0);

        $today = $this->repo->getSalesSummary('today', $todayStart);
        $week  = $this->repo->getSalesSummary('week', $weekStart);
        $month = $this->repo->getSalesSummary('month', $monthStart);

        $purchaseWeek  = $this->repo->getPurchaseSummary('purchase_week', $weekStart);
        $purchaseMonth = $this->repo->getPurchaseSummary('purchase_month', $monthStart);

        return [
            'today'             => ['revenue' => $today->revenue, 'bills' => $today->bills, 'avg' => $today->avg],
            'week'              => ['revenue' => $week->revenue, 'bills' => $week->bills, 'avg' => $week->avg],
            'month'             => ['revenue' => $month->revenue, 'bills' => $month->bills, 'avg' => $month->avg],
            'purchase_week'     => ['amount' => $purchaseWeek->amount, 'count' => $purchaseWeek->count, 'paid' => $purchaseWeek->paid],
            'purchase_month'    => ['amount' => $purchaseMonth->amount, 'count' => $purchaseMonth->count, 'paid' => $purchaseMonth->paid],
            'total_bills'       => $this->repo->getTotalBills(),
            'outstanding_credit' => $this->repo->getOutstandingCredit(),
            'stock_value'       => $this->repo->getStockValue(),
        ];
    }
}
