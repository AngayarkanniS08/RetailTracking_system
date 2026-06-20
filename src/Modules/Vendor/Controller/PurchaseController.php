<?php
namespace Modules\Vendor\Controller;

use Modules\Vendor\DTO\PurchaseDTO;
use Modules\Vendor\DTO\PurchaseItemDTO;
use Modules\Vendor\Service\PurchaseService;
use Modules\Vendor\Repository\PurchaseRepository;
use Modules\Product\Service\ProductService;
use Modules\Product\Repository\ProductRepository;
use Modules\Product\Repository\CategoryRepository;
use Modules\Inventory\Service\BatchService;
use Modules\Inventory\Repository\BatchRepository;
use Core\Middlewares\AuthMiddleware;
use Modules\Auth\Validation\ValidationException;
use Exception;

class PurchaseController
{
    private PurchaseService $service;

    public function __construct()
    {
        // Build dependencies (you can later use a DI container)
        $productRepo = new ProductRepository();
        $categoryRepo = new CategoryRepository();
        $productService = new ProductService($productRepo, $categoryRepo);
        $batchRepo = new BatchRepository();
        $batchService = new BatchService($batchRepo);
        $purchaseRepo = new PurchaseRepository();
        $this->service = new PurchaseService($purchaseRepo, $productService, $batchService);
    }

    /**
     * GET /api/purchases
     * List all purchases with pagination and filters.
     */
    public function index(): void
    {
        header('Content-Type: application/json');
        AuthMiddleware::authenticate();

        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 10);
        $search = $_GET['search'] ?? '';
        $vendorId = $_GET['vendor_id'] ?? '';
        $filters = [];
        if (!empty($search)) {
            $filters['search'] = $search;
        }
        if (!empty($_GET['vendor_id'])) {
            $filters['vendor_id'] = $_GET['vendor_id'];
        }
        // Add other filters if needed: vendor_id, date_from, date_to, status

        try {
            $result = $this->service->getPurchases($page, $limit, $filters);
            echo json_encode($result);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to load purchases: ' . $e->getMessage()]);
        }
    }

    /**
     * POST /api/purchases
     * Create a new purchase with vendor and items.
     */
    public function store(): void
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        $user = AuthMiddleware::authenticate();

        $input = json_decode(file_get_contents('php://input'), true);

        // Build item DTOs
        $items = [];
        foreach ($input['items'] ?? [] as $itemData) {
            $items[] = new PurchaseItemDTO(
                productId: $itemData['product_id'] ?? '',
                quantity: (float)($itemData['quantity'] ?? 0),
                unitPrice: (float)($itemData['unit_price'] ?? 0),
                gstRate: (float)($itemData['gst_rate'] ?? 0)
            );
        }

        // Build main DTO
        $dto = new PurchaseDTO(
            vendorName: trim($input['vendor_name'] ?? ''),
            phone: trim($input['phone'] ?? ''),
            purchaseDate: trim($input['purchase_date'] ?? date('Y-m-d')),
            baseAmount: (float)($input['base_amount'] ?? 0),
            amountPaid: (float)($input['amount_paid'] ?? 0),
            items: $items
        );

        try {
            $userId = $user->data->user_id ?? '';
            $purchase = $this->service->createPurchase($dto, $userId);
            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Purchase created successfully',
                'purchase' => $purchase
            ]);
        } catch (ValidationException $e) {
            http_response_code(422);
            echo json_encode(['error' => $e->getMessage()]);
        } catch (Exception $e) {
            error_log('Purchase creation error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }

    /**
     * GET /api/purchases/{id}
     * Get a single purchase with its items.
     */
    public function show(string $id): void
    {
        header('Content-Type: application/json');
        AuthMiddleware::authenticate();

        try {
            $purchase = $this->service->getPurchase($id, true);
            if (!$purchase) {
                http_response_code(404);
                echo json_encode(['error' => 'Purchase not found']);
                return;
            }
            echo json_encode($purchase);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to load purchase']);
        }
    }

    /**
     * POST /api/purchases/{id}/pay
     * Record a payment against a purchase.
     */
    public function recordPayment(string $id): void
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        AuthMiddleware::authenticate();

        $input = json_decode(file_get_contents('php://input'), true);
        $amount = (float)($input['amount'] ?? 0);

        if ($amount <= 0) {
            http_response_code(422);
            echo json_encode(['error' => 'Payment amount must be positive']);
            return;
        }

        try {
            $purchase = $this->service->recordPayment($id, $amount);
            echo json_encode([
                'success' => true,
                'message' => 'Payment recorded successfully',
                'purchase' => $purchase
            ]);
        } catch (ValidationException $e) {
            http_response_code(422);
            echo json_encode(['error' => $e->getMessage()]);
        } catch (Exception $e) {
            error_log('Payment error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to record payment']);
        }
    }

        /**
     * PUT /api/purchases/{id}
     * Update purchase header and items
     */
    public function update(string $id): void
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        AuthMiddleware::authenticate();

        $input = json_decode(file_get_contents('php://input'), true);

        // Build item DTOs
        $items = [];
        foreach ($input['items'] ?? [] as $itemData) {
            $items[] = new PurchaseItemDTO(
                productId: $itemData['product_id'] ?? '',
                quantity: (float)($itemData['quantity'] ?? 0),
                unitPrice: (float)($itemData['unit_price'] ?? 0),
                gstRate: (float)($itemData['gst_rate'] ?? 0)
            );
        }

        $dto = new PurchaseDTO(
            vendorName: '',  // Not updating vendor name in this version
            phone: '',       // Not updating phone
            purchaseDate: trim($input['purchase_date'] ?? date('Y-m-d')),
            baseAmount: (float)($input['base_amount'] ?? 0),
            amountPaid: (float)($input['amount_paid'] ?? 0),
            items: $items
        );

        try {
            $userId = AuthMiddleware::getUserId();
            $purchase = $this->service->updatePurchase($id, $dto, $userId);
            echo json_encode([
                'success' => true,
                'message' => 'Purchase updated successfully',
                'purchase' => $purchase
            ]);
        } catch (ValidationException $e) {
            http_response_code(422);
            echo json_encode(['error' => $e->getMessage()]);
        } catch (Exception $e) {
            error_log('Purchase update error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }

    /**
     * DELETE /api/purchases/{id} – Delete a purchase (optional)
     */
    public function destroy(string $id): void
    {
        // TODO: Implement if needed
        http_response_code(501);
        echo json_encode(['error' => 'Not implemented']);
    }

    public function vendorHistory(string $vendorId): void
    {
        header('Content-Type: application/json');
        AuthMiddleware::authenticate();

        try {
            $history = $this->service->getVendorHistory($vendorId);
            echo json_encode($history);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to load vendor history']);
        }
    }

    public function allHistory(): void
    {
        header('Content-Type: application/json');
        AuthMiddleware::authenticate();

        try {
            $history = $this->service->getAllVendorHistory();
            echo json_encode($history);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to load vendor history']);
        }
    }
}