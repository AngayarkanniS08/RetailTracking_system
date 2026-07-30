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

    public function getSalesSummary(?string $period = 'today'): array
    {
        $period = strtolower($period ?? 'today');
        $now = new \DateTimeImmutable('now');

        switch ($period) {
            case 'yesterday':
                $start = $now->modify('-1 day')->setTime(0, 0, 0);
                $prevStart = $now->modify('-2 days')->setTime(0, 0, 0);
                break;
            case 'week':
                $dayOfWeek = (int) $now->format('N');
                $start = $now->modify('-' . ($dayOfWeek - 1) . ' days')->setTime(0, 0, 0);
                $prevStart = $start->modify('-7 days');
                break;
            case 'month':
                $start = $now->modify('first day of this month')->setTime(0, 0, 0);
                $prevStart = $start->modify('first day of previous month');
                break;
            case 'quarter':
                $quarterMonth = (int)ceil((int)$now->format('n') / 3) * 3 - 2;
                $start = $now->setDate((int)$now->format('Y'), $quarterMonth, 1)->setTime(0, 0, 0);
                $prevStart = $start->modify('-3 months');
                break;
            case 'year':
                $start = $now->modify('first day of January this year')->setTime(0, 0, 0);
                $prevStart = $start->modify('-1 year');
                break;
            case 'today':
            default:
                $start = $now->setTime(0, 0, 0);
                $prevStart = $start->modify('-1 day');
                break;
        }

        $todayStart = $now->setTime(0, 0, 0);
        $dayOfWeek = (int) $now->format('N');
        $weekStart = $now->modify('-' . ($dayOfWeek - 1) . ' days')->setTime(0, 0, 0);
        $thisWeekEnd = $weekStart->modify('+7 days');
        $lastWeekStart = $weekStart->modify('-7 days');
        $monthStart = $now->modify('first day of this month')->setTime(0, 0, 0);

        $today = $this->repo->getSalesSummary('today', $todayStart);
        $week  = $this->repo->getSalesSummary('week', $weekStart);
        $month = $this->repo->getSalesSummary('month', $monthStart);

        $currentSales = $this->repo->getSalesSummary($period, $start);
        $prevSales    = $this->repo->getSalesSummary('prev_' . $period, $prevStart);

        $purchaseWeek  = $this->repo->getPurchaseSummary('purchase_week', $weekStart);
        $purchaseMonth = $this->repo->getPurchaseSummary('purchase_month', $monthStart);

        $currentPurchase = $this->repo->getPurchaseSummary($period, $start);

        // Growth rate calculations
        $revGrowth = $prevSales->revenue > 0
            ? round((($currentSales->revenue - $prevSales->revenue) / $prevSales->revenue) * 100, 1)
            : ($currentSales->revenue > 0 ? 100.0 : 0.0);

        // Profit: only calculable when purchase data exists; otherwise null
        $hasCostData = $currentPurchase->amount > 0;
        $grossProfit  = $hasCostData ? max(0, $currentSales->revenue - $currentPurchase->amount) : null;
        $profitMargin = $hasCostData && $currentSales->revenue > 0 ? round(($grossProfit / $currentSales->revenue) * 100, 1) : null;

        // Real chart data (this week vs last week daily revenue)
        $thisWeekData = $this->repo->getWeeklyChartData($weekStart, $thisWeekEnd);
        $lastWeekData = $this->repo->getWeeklyChartData($lastWeekStart, $weekStart);

        return [
            'period' => $period,
            'executive_kpis' => [
                'revenue' => [
                    'value' => $currentSales->revenue,
                    'growth_pct' => $revGrowth,
                    'prev_value' => $prevSales->revenue,
                ],
                'bills' => [
                    'count' => $currentSales->bills,
                    'avg_ticket' => $currentSales->avg,
                ],
                'profit' => [
                    'value' => $grossProfit,
                    'margin_pct' => $profitMargin,
                ],
                'outstanding_credit' => $this->repo->getOutstandingCredit(),
            ],
            'sales_summary' => [
                'revenue' => $currentSales->revenue,
                'bills' => $currentSales->bills,
                'avg_ticket' => $currentSales->avg,
                'growth_pct' => $revGrowth,
            ],
            'purchase_summary' => [
                'amount' => $currentPurchase->amount,
                'count' => $currentPurchase->count,
                'paid' => $currentPurchase->paid,
                'pending' => max(0, $currentPurchase->amount - $currentPurchase->paid),
                'avg_purchase' => $currentPurchase->count > 0 ? round($currentPurchase->amount / $currentPurchase->count, 2) : 0,
            ],
            'today'              => ['revenue' => $today->revenue, 'bills' => $today->bills, 'avg' => $today->avg],
            'week'               => ['revenue' => $week->revenue, 'bills' => $week->bills, 'avg' => $week->avg],
            'month'              => ['revenue' => $month->revenue, 'bills' => $month->bills, 'avg' => $month->avg],
            'purchase_week'      => ['amount' => $purchaseWeek->amount, 'count' => $purchaseWeek->count, 'paid' => $purchaseWeek->paid],
            'purchase_month'     => ['amount' => $purchaseMonth->amount, 'count' => $purchaseMonth->count, 'paid' => $purchaseMonth->paid],
            'total_bills'        => $this->repo->getTotalBills(),
            'outstanding_credit' => $this->repo->getOutstandingCredit(),
            'stock_value'        => $this->repo->getStockValue(),
            'chartData' => [
                'labels'   => $thisWeekData['labels'],
                'thisWeek' => $thisWeekData['values'],
                'lastWeek' => $lastWeekData['values'],
            ],
        ];
    }
}
