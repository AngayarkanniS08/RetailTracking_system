<?php
namespace Modules\Vendor\Controller;

use Modules\Vendor\DTO\PurchaseDTO;
use Modules\Vendor\DTO\PurchaseItemDTO;
use Modules\Vendor\Service\PurchaseService;
use Modules\Vendor\Repository\PurchaseRepository;
use Core\Middlewares\AuthMiddleware;
use Core\Cache\ValkeyCache;
use Modules\Auth\Validation\ValidationException;
use Exception;

class PurchaseController
{
    private PurchaseService $service;

    public function __construct()
    {
        $purchaseRepo = new PurchaseRepository();
        $this->service = new PurchaseService($purchaseRepo);
    }

    /**
     * GET /api/purchases
     * List all purchases with pagination and filters.
     */
    public function index(): void
    {
        header('Content-Type: application/json');
        $user = AuthMiddleware::authenticate();

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

        $userId = $user->data->user_id ?? null;

        // Build cache key unique per search, page, filters, user, and version
        $cacheVersion = $this->getVendorCacheVersion();
        $cacheKey = sprintf(
            'vendors:list:v%d:search:%s:page:%d:limit:%d:user:%s',
            $cacheVersion,
            md5($search),
            $page,
            $limit,
            $userId ?: 'guest'
        );

        $valkey = null;
        try {
            $valkey = ValkeyCache::getClient();
            $cached = $valkey->get($cacheKey);
            if ($cached !== false && $cached !== null) {
                echo $cached;
                return;
            }
        } catch (\Exception $e) {
            error_log('Valkey read error: ' . $e->getMessage());
        }

        try {
            $result = $this->service->getPurchases($page, $limit, $filters);
            $json = json_encode($result);

            if ($valkey) {
                try {
                    $valkey->setex($cacheKey, 300, $json);
                } catch (\Exception $e) {
                    error_log('Valkey write error: ' . $e->getMessage());
                }
            }

            echo $json;
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
            $response = json_decode(json_encode($purchase), true);
            $response['totalGst'] = ($purchase->totalAmount ?? 0) - ($purchase->baseAmount ?? 0);
            echo json_encode($response);
        } catch (\Throwable $e) {
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
        $paymentDate = $input['payment_date'] ?? date('Y-m-d');

        if ($amount <= 0) {
            http_response_code(422);
            echo json_encode(['error' => 'Payment amount must be positive']);
            return;
        }

        try {
            $purchase = $this->service->recordPayment($id, $amount, $paymentDate);
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
        $user = AuthMiddleware::authenticate();
        $userId = $user->data->user_id ?? null;

        $month = $_GET['month'] ?? '';
        $year = $_GET['year'] ?? '';
        $date = $_GET['date'] ?? '';
        $filters = [];
        if (!empty($date)) {
            $filters['date'] = $date;
        } elseif (!empty($month) && !empty($year)) {
            $filters['month'] = $month;
            $filters['year'] = $year;
        }

        $cacheVersion = $this->getVendorCacheVersion();
        $cacheKey = sprintf(
            'vendors:history:v%d:%s:date:%s:month:%s:year:%s:user:%s',
            $cacheVersion,
            $vendorId,
            md5($date),
            md5($month),
            md5($year),
            $userId ?: 'guest'
        );

        $valkey = null;
        try {
            $valkey = ValkeyCache::getClient();
            $cached = $valkey->get($cacheKey);
            if ($cached !== false && $cached !== null) {
                echo $cached;
                return;
            }
        } catch (\Exception $e) {
            error_log('Valkey read error: ' . $e->getMessage());
        }

        try {
            $history = $this->service->getVendorHistory($vendorId, $filters);
            $json = json_encode($history);
            if ($valkey) {
                try {
                    $valkey->setex($cacheKey, 300, $json);
                } catch (\Exception $e) {
                    error_log('Valkey write error: ' . $e->getMessage());
                }
            }
            echo $json;
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to load vendor history']);
        }
    }

    public function allHistory(): void
    {
        header('Content-Type: application/json');
        $user = AuthMiddleware::authenticate();
        $userId = $user->data->user_id ?? null;

        $month = $_GET['month'] ?? '';
        $year = $_GET['year'] ?? '';
        $date = $_GET['date'] ?? '';
        $filters = [];
        if (!empty($date)) {
            $filters['date'] = $date;
        } elseif (!empty($month) && !empty($year)) {
            $filters['month'] = $month;
            $filters['year'] = $year;
        }

        $cacheVersion = $this->getVendorCacheVersion();
        $cacheKey = sprintf(
            'vendors:history:all:v%d:date:%s:month:%s:year:%s:user:%s',
            $cacheVersion,
            md5($date),
            md5($month),
            md5($year),
            $userId ?: 'guest'
        );

        $valkey = null;
        try {
            $valkey = ValkeyCache::getClient();
            $cached = $valkey->get($cacheKey);
            if ($cached !== false && $cached !== null) {
                echo $cached;
                return;
            }
        } catch (\Exception $e) {
            error_log('Valkey read error: ' . $e->getMessage());
        }

        try {
            $history = $this->service->getAllVendorHistory($filters);
            $json = json_encode($history);
            if ($valkey) {
                try {
                    $valkey->setex($cacheKey, 300, $json);
                } catch (\Exception $e) {
                    error_log('Valkey write error: ' . $e->getMessage());
                }
            }
            echo $json;
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to load vendor history']);
        }
    }

    public function vendorPayments(string $vendorId): void
    {
        header('Content-Type: application/json');
        $user = AuthMiddleware::authenticate();
        $userId = $user->data->user_id ?? null;

        $month = $_GET['month'] ?? '';
        $year = $_GET['year'] ?? '';
        $date = $_GET['date'] ?? '';
        $filters = [];
        if (!empty($date)) {
            $filters['date'] = $date;
        } elseif (!empty($month) && !empty($year)) {
            $filters['month'] = $month;
            $filters['year'] = $year;
        }

        $cacheVersion = $this->getVendorCacheVersion();
        $cacheKey = sprintf(
            'vendors:payments:v%d:%s:date:%s:month:%s:year:%s:user:%s',
            $cacheVersion,
            $vendorId,
            md5($date),
            md5($month),
            md5($year),
            $userId ?: 'guest'
        );

        $valkey = null;
        try {
            $valkey = ValkeyCache::getClient();
            $cached = $valkey->get($cacheKey);
            if ($cached !== false && $cached !== null) {
                echo $cached;
                return;
            }
        } catch (\Exception $e) {
            error_log('Valkey read error: ' . $e->getMessage());
        }

        try {
            $payments = $this->service->getVendorPayments($vendorId, $filters);
            $json = json_encode($payments);
            if ($valkey) {
                try {
                    $valkey->setex($cacheKey, 300, $json);
                } catch (\Exception $e) {
                    error_log('Valkey write error: ' . $e->getMessage());
                }
            }
            echo $json;
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to load vendor payments']);
        }
    }

    public function allPayments(): void
    {
        header('Content-Type: application/json');
        $user = AuthMiddleware::authenticate();
        $userId = $user->data->user_id ?? null;

        $month = $_GET['month'] ?? '';
        $year = $_GET['year'] ?? '';
        $date = $_GET['date'] ?? '';
        $filters = [];
        if (!empty($date)) {
            $filters['date'] = $date;
        } elseif (!empty($month) && !empty($year)) {
            $filters['month'] = $month;
            $filters['year'] = $year;
        }

        $cacheVersion = $this->getVendorCacheVersion();
        $cacheKey = sprintf(
            'vendors:payments:all:v%d:date:%s:month:%s:year:%s:user:%s',
            $cacheVersion,
            md5($date),
            md5($month),
            md5($year),
            $userId ?: 'guest'
        );

        $valkey = null;
        try {
            $valkey = ValkeyCache::getClient();
            $cached = $valkey->get($cacheKey);
            if ($cached !== false && $cached !== null) {
                echo $cached;
                return;
            }
        } catch (\Exception $e) {
            error_log('Valkey read error: ' . $e->getMessage());
        }

        try {
            $payments = $this->service->getAllPayments($filters);
            $json = json_encode($payments);
            if ($valkey) {
                try {
                    $valkey->setex($cacheKey, 300, $json);
                } catch (\Exception $e) {
                    error_log('Valkey write error: ' . $e->getMessage());
                }
            }
            echo $json;
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to load payments']);
        }
    }

    public function vendorList(): void
    {
        header('Content-Type: application/json');
        AuthMiddleware::authenticate();

        try {
            $vendors = $this->service->getAllVendors();
            $result = array_map(fn($v) => [
                'id' => $v->id,
                'name' => $v->name,
                'contact_info' => $v->phone
            ], $vendors);
            echo json_encode($result);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to load vendors']);
        }
    }

    private function getVendorCacheVersion(): int
    {
        try {
            $valkey = ValkeyCache::getClient();
            return (int)($valkey->get('vendors:cache:version') ?: 0);
        } catch (\Exception $e) {
            return 0;
        }
    }
}