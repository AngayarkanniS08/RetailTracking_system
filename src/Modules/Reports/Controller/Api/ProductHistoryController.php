<?php
namespace Modules\Reports\Controller\Api;

use Core\Middlewares\AuthMiddleware;
use Modules\Reports\Service\ProductHistoryService;
use Modules\Reports\Repository\ProductHistoryRepository;

class ProductHistoryController
{
    private ProductHistoryService $service;

    public function __construct()
    {
        $this->service = new ProductHistoryService(new ProductHistoryRepository());
    }

    /**
     * GET /api/products/{id}/history
     * Returns product analytics: sold, revenue, velocity, stock, margin, etc.
     */
    public function show(string $id): void
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        AuthMiddleware::authenticate();

        try {
            $data = $this->service->getProductAnalytics($id);
            echo json_encode($data);
        } catch (\RuntimeException $e) {
            http_response_code(404);
            echo json_encode(['error' => $e->getMessage()]);
        } catch (\Exception $e) {
            error_log('ProductHistoryController::show - ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to load product history']);
        }
    }

    /**
     * GET /api/products/{id}/daily-sales
     * Returns recent daily sales entries for this product.
     */
    public function dailySales(string $id): void
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        AuthMiddleware::authenticate();

        try {
            $data = $this->service->getDailySales($id);
            echo json_encode($data);
        } catch (\Exception $e) {
            error_log('ProductHistoryController::dailySales - ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to load daily sales']);
        }
    }

    /**
     * POST /api/products/{id}/daily-sales
     * Body: { sale_date: "2026-07-06", quantity: 5, notes?: "..." }
     */
    public function storeDailySale(string $id): void
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        AuthMiddleware::authenticate(900);

        $body = json_decode(file_get_contents('php://input'), true);

        if (empty($body['sale_date']) || empty($body['quantity'])) {
            http_response_code(422);
            echo json_encode(['error' => 'sale_date and quantity are required']);
            return;
        }

        try {
            $this->service->upsertDailySale(
                $id,
                $body['sale_date'],
                (int) $body['quantity'],
                $body['notes'] ?? null
            );
            http_response_code(201);
            echo json_encode(['message' => 'Daily sale recorded']);
        } catch (\InvalidArgumentException $e) {
            http_response_code(422);
            echo json_encode(['error' => $e->getMessage()]);
        } catch (\Exception $e) {
            error_log('ProductHistoryController::storeDailySale - ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to record daily sale']);
        }
    }

    /**
     * DELETE /api/products/daily-sales/{saleId}
     */
    public function destroyDailySale(string $saleId): void
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        AuthMiddleware::authenticate(900);

        try {
            $this->service->deleteDailySale($saleId);
            echo json_encode(['message' => 'Daily sale deleted']);
        } catch (\RuntimeException $e) {
            http_response_code(404);
            echo json_encode(['error' => $e->getMessage()]);
        } catch (\Exception $e) {
            error_log('ProductHistoryController::destroyDailySale - ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete daily sale']);
        }
    }
}
