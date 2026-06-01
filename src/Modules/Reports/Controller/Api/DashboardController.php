<?php
namespace Modules\Reports\Controller\Api;

use Core\Middlewares\AuthMiddleware;
use Modules\Reports\Service\DashboardService;
use Modules\Reports\Service\StockIntelligenceService;

class DashboardController
{
    private DashboardService $dashboardService;
    private StockIntelligenceService $stockIntelService;

    public function __construct()
    {
        $this->dashboardService  = new DashboardService();
        $this->stockIntelService = new StockIntelligenceService();
    }

    /**
     * GET /api/dashboard/stats
     * Returns: today/week/month sales + purchase_week/purchase_month summaries.
     */
    public function stats(): void
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        // Validate JWT and set RLS user context
        AuthMiddleware::authenticate();

        try {
            $data = $this->dashboardService->getSalesSummary();
            echo json_encode($data);
        } catch (\Exception $e) {
            error_log('DashboardController::stats - ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to load dashboard stats']);
        }
    }

    /**
     * GET /api/dashboard/stock-intel
     * Returns: high_selling, low_selling, old_stock arrays.
     */
    public function stockIntel(): void
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        // Validate JWT and set RLS user context
        AuthMiddleware::authenticate();

        try {
            $data = $this->stockIntelService->getStockIntel();
            echo json_encode($data);
        } catch (\Exception $e) {
            error_log('DashboardController::stockIntel - ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to load stock intelligence']);
        }
    }
}
