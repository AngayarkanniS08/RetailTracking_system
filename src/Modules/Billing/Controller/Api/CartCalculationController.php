<?php
namespace Modules\Billing\Controller\Api;

use Core\Middlewares\AuthMiddleware;
use Modules\Billing\Service\InvoiceService;
use Modules\Billing\Repository\InvoiceRepository;
use Modules\Auth\Validation\ValidationException;

class CartCalculationController
{
    private InvoiceService $service;

    public function __construct()
    {
        $repo = new InvoiceRepository();
        $this->service = new InvoiceService($repo);
    }

    /**
     * POST /api/billing/calculate
     *
     * Request body:
     * {
     *   "items": [
     *     { "product_id": "...", "quantity": 2, "batch_id": "...", "discount_amount": 0 }
     *   ],
     *   "bill_discount": 50,
     *   "apply_gst": true,
     *   "payment_mode": "cash"
     * }
     *
     * Response:
     * {
     *   "items": [ { "product_id": "...", "product_name": "...", ... } ],
     *   "subtotal": 400,
     *   "item_discount": 0,
     *   "bill_discount": 50,
     *   "gst_total": 63,
     *   "round_off": 0.42,
     *   "grand_total": 413.42,
     *   "payment_mode": "cash"
     * }
     */
    public function calculate(): void
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        AuthMiddleware::authenticate();
        $input = json_decode(file_get_contents('php://input'), true);

        $items = $input['items'] ?? [];
        $billDiscount = max(0, (float)($input['bill_discount'] ?? 0));
        $applyGst = (bool)($input['apply_gst'] ?? true);
        $paymentMode = $input['payment_mode'] ?? 'cash';

        try {
            $result = $this->service->calculateCart($items, $billDiscount, $applyGst);
            $result['payment_mode'] = $paymentMode;

            echo json_encode($result);
        } catch (ValidationException $e) {
            http_response_code(422);
            echo json_encode(['error' => $e->getMessage()]);
        } catch (\Exception $e) {
            error_log('CartCalculationController::calculate - ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to calculate cart']);
        }
    }
}
