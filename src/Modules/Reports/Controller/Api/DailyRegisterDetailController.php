<?php
declare(strict_types=1);

namespace Modules\Reports\Controller\Api;

use Core\Middlewares\AuthMiddleware;
use Modules\Reports\Service\DailyRegisterDetailService;

class DailyRegisterDetailController
{
    private DailyRegisterDetailService $service;

    public function __construct(?DailyRegisterDetailService $service = null)
    {
        $this->service = $service ?? new DailyRegisterDetailService();
    }

    /**
     * GET /api/daily-register/detail
     * Query Parameters: date (YYYY-MM-DD), page (int), limit (int)
     */
    public function show(): void
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
            return;
        }

        AuthMiddleware::authenticate();

        try {
            $date  = $_GET['date'] ?? date('Y-m-d');
            $page  = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
            $limit = isset($_GET['limit']) ? min(100, max(1, (int) $_GET['limit'])) : 50;

            $data = $this->service->getDailyDetail($date, $page, $limit);

            echo json_encode([
                'status' => 'success',
                'data'   => $data,
            ]);
        } catch (\Exception $e) {
            error_log('DailyRegisterDetailController::show - ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'status'  => 'error',
                'message' => 'Failed to load daily register detail',
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
