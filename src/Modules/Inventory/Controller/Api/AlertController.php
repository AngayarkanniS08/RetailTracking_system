<?php

namespace Modules\Inventory\Controller\Api;

use Modules\Inventory\Service\AlertService;
use Modules\Inventory\Repository\AlertRepositoryFactory;
use Modules\Inventory\DTO\AlertDTO;
use Exception;

class AlertController
{
    private AlertService $service;

    public function __construct()
    {
        // FIX: inject via factory so AlertRepositoryInterface is actually resolved,
        // not bypassed by direct instantiation of the concrete class.
        $this->service = new AlertService(AlertRepositoryFactory::create());
    }

    public function index(): void
    {
        header('Content-Type: application/json');
        try {
            $alerts = $this->service->getActiveAlerts();
            echo json_encode(['success' => true, 'data' => $alerts]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to load alerts: ' . $e->getMessage()]);
        }
    }

    public function store(): void
    {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        try {
            $input = json_decode(file_get_contents('php://input'), true);

            $productId      = trim($input['product_id'] ?? '');
            $dailySales     = (int)($input['daily_sales'] ?? 0);
            $leadTime       = (int)($input['lead_time'] ?? 0);
            $emergencyStock = (int)($input['emergency_stock'] ?? 0);

            if (empty($productId)) {
                http_response_code(422);
                echo json_encode(['error' => 'Product ID is required']);
                return;
            }

            // FIX: validate UUID format before hitting the database
            if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $productId)) {
                http_response_code(422);
                echo json_encode(['error' => 'Product ID must be a valid UUID']);
                return;
            }

            // Map input payload to AlertDTO
            $dto = new AlertDTO($productId, $dailySales, $leadTime, $emergencyStock);

            $alert = $this->service->saveAlert($dto);

            // Immediately evaluate alert trigger state based on current stock
            $db = \Config\Database::getConnection();
            $currentUserId = $db->query("SELECT current_setting('app.current_user_id', true)")->fetchColumn() ?: '';
            if ($currentUserId) {
                try {
                    $hook = new \Modules\Inventory\Repository\StockAlertHook();
                    $hook->evaluateProductStockAlert($productId, $currentUserId);
                } catch (Exception $e) {
                    error_log("Failed evaluating stock alert hook on save: " . $e->getMessage());
                }
            }

            echo json_encode(['success' => true, 'data' => $alert]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to save alert parameters: ' . $e->getMessage()]);
        }
    }

    // FIX: renamed from destroy() to disable() — operation is a soft-reset, not a row deletion
    public function disable(string $productId): void
    {
        header('Content-Type: application/json');

        // FIX: validate UUID format here too
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $productId)) {
            http_response_code(422);
            echo json_encode(['error' => 'Product ID must be a valid UUID']);
            return;
        }

        try {
            $success = $this->service->disableAlert($productId);
            echo json_encode(['success' => $success]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to disable alert: ' . $e->getMessage()]);
        }
    }
}
