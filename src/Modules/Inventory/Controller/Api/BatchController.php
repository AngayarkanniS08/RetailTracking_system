<?php
namespace Modules\Inventory\Controller\Api;

use Modules\Inventory\Service\BatchService;
use Modules\Inventory\Repository\BatchRepository;
use Core\Middlewares\AuthMiddleware;
use Exception;

class BatchController
{
    private BatchService $service;

    public function __construct()
    {
        $this->service = new BatchService(new BatchRepository());
    }

    // GET /api/inventory/batches
    public function index(): void
    {
        header('Content-Type: application/json');
        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 5);
        $search = trim($_GET['search'] ?? '');
        $categoryId = trim($_GET['category_id'] ?? '');
        $subcategoryId = trim($_GET['subcategory_id'] ?? '');
        
        try {
            AuthMiddleware::authenticate();
            $batches = $this->service->getBatchesPaginated($page, $limit, $search, $categoryId, $subcategoryId);
            echo json_encode($batches);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to load stock batches: ' . $e->getMessage()]);
        }
    }

    // POST /api/inventory/batches
    public function store(): void
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        try {
            AuthMiddleware::authenticate();
            $input = json_decode(file_get_contents('php://input'), true);

            $productId   = trim($input['product_id'] ?? '');
            $batchNumber = trim($input['vendor_name'] ?? $input['batch_number'] ?? '');
            $initialQty  = (int)($input['quantity'] ?? $input['initial_qty'] ?? 0);
            $costPrice   = (float)($input['purchase_price'] ?? $input['cost_price'] ?? 0);
            $sellingPrice = (float)($input['selling_price'] ?? 0);
            $retailPrice = (float)($input['retail_price'] ?? 0);
            $createdAt   = trim($input['created_at'] ?? '');

            if (empty($productId)) {
                http_response_code(422);
                echo json_encode(['error' => 'Product is required']);
                return;
            }

            if (empty($batchNumber)) {
                http_response_code(422);
                echo json_encode(['error' => 'Batch number/Vendor Name is required']);
                return;
            }

            if ($initialQty <= 0) {
                http_response_code(422);
                echo json_encode(['error' => 'Quantity must be greater than zero']);
                return;
            }

            $batchData = [
                'product_id'   => $productId,
                'batch_number' => $batchNumber,
                'initial_qty'  => $initialQty,
                'cost_price'   => $costPrice,
                'selling_price' => $sellingPrice,
                'retail_price' => $retailPrice,
                'created_at'   => !empty($createdAt) ? $createdAt : null
            ];

            $batch = $this->service->createBatch($batchData);
            http_response_code(201);
            echo json_encode(['success' => true, 'batch' => $batch]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create stock batch: ' . $e->getMessage()]);
        }
    }

    // PUT /api/inventory/batches/{id}
    public function update(string $id): void
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        try {
            AuthMiddleware::authenticate();
            $input = json_decode(file_get_contents('php://input'), true);
            $qty = isset($input['quantity']) ? (int)$input['quantity'] : null;

            if ($qty === null || $qty < 0) {
                http_response_code(422);
                echo json_encode(['error' => 'Invalid or missing quantity']);
                return;
            }
             // Fetch current state of the batch for merging
            $existing = $this->service->getBatchById($id);
            if (!$existing) {
                http_response_code(404);
                echo json_encode(['error' => 'Batch not found or unauthorized']);
                return;
            }

            // Merge input parameters or fallback to current database values
            $vendorName = isset($input['vendor_name']) ? trim($input['vendor_name']) : $existing['vendor_name'];
            $purchasePrice = isset($input['purchase_price']) ? (float)$input['purchase_price'] : $existing['purchase_price'];
            $sellingPrice = isset($input['selling_price']) ? (float)$input['selling_price'] : $existing['selling_price'];
            $retailPrice = isset($input['retail_price']) ? (float)$input['retail_price'] : $existing['retail_price'];
            $quantity = isset($input['quantity']) ? (int)$input['quantity'] : $existing['quantity'];
            $createdAt = isset($input['created_at']) ? trim($input['created_at']) : $existing['created_at'];
            if (empty($vendorName)) {
                http_response_code(422);
                echo json_encode(['error' => 'Vendor Name/Batch number is required']);
                return;
            }
            if ($quantity < 0) {
                http_response_code(422);
                echo json_encode(['error' => 'Quantity must be non-negative']);
                return;
            }
            
            $batchData = [
                'vendor_name' => $vendorName,
                'purchase_price' => $purchasePrice,
                'selling_price' => $sellingPrice,
                'retail_price' => $retailPrice,
                'quantity' => $quantity,
                'created_at' => $createdAt
            ];


            $success = $this->service->updateBatch($id, $batchData);
            if ($success) {
                echo json_encode(['success' => true]);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Batch not found or unauthorized']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update stock batch: ' . $e->getMessage()]);
        }
    }
}
