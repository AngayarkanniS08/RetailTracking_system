<?php
namespace Modules\Billing\Controller;

use Modules\Billing\DTO\InvoiceDTO;
use Modules\Billing\DTO\InvoiceItemDTO;
use Modules\Billing\Service\InvoiceService;
use Modules\Billing\Repository\InvoiceRepository;
use Core\Middlewares\AuthMiddleware;
use Core\Cache\ValkeyCache;
use Modules\Auth\Validation\ValidationException;
use Exception;

class InvoiceController
{
    private InvoiceService $service;

    public function __construct()
    {
        $repo = new InvoiceRepository();
        $this->service = new InvoiceService($repo);
    }

    /**
     * GET /api/invoices
     */
    public function index(): void
    {
        header('Content-Type: application/json');
        $user = AuthMiddleware::authenticate();
        $userId = $user->data->user_id ?? null;

        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 10);
        $filters = [];

        if (!empty($_GET['search'])) {
            $filters['search'] = $_GET['search'];
        }
        if (!empty($_GET['date_from'])) {
            $filters['date_from'] = $_GET['date_from'];
        }
        if (!empty($_GET['date_to'])) {
            $filters['date_to'] = $_GET['date_to'];
        }
        if (!empty($_GET['invoice_status'])) {
            $filters['invoice_status'] = $_GET['invoice_status'];
        }
        if (!empty($_GET['payment_status'])) {
            $filters['payment_status'] = $_GET['payment_status'];
        }

        $searchTerm = $_GET['search'] ?? '';
        $dateFrom = $_GET['date_from'] ?? '';
        $dateTo = $_GET['date_to'] ?? '';
        $invStatus = $_GET['invoice_status'] ?? '';
        $payStatus = $_GET['payment_status'] ?? '';

        $cacheKey = sprintf(
            'billing:invoices:list:search:%s:df:%s:dt:%s:is:%s:ps:%s:page:%d:limit:%d:user:%s',
            md5($searchTerm),
            md5($dateFrom),
            md5($dateTo),
            $invStatus ?: 'all',
            $payStatus ?: 'all',
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
            $result = $this->service->getInvoices($page, $limit, $filters);
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
            echo json_encode(['error' => 'Failed to load invoices: ' . $e->getMessage()]);
        }
    }

    /**
     * POST /api/invoices
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

        $items = [];
        foreach ($input['items'] ?? [] as $itemData) {
            $items[] = new InvoiceItemDTO(
                productId: $itemData['product_id'] ?? '',
                quantity: (float)($itemData['quantity'] ?? 0),
                unitPrice: (float)($itemData['unit_price'] ?? 0),
                batchId: $itemData['batch_id'] ?? null,
                discountAmount: (float)($itemData['discount_amount'] ?? 0)
            );
        }

        $dto = new InvoiceDTO(
            customerId: $input['customer_id'] ?? null,
            customerName: $input['customer_name'] ?? null,
            customerPhone: $input['customer_phone'] ?? null,
            applyGst: (bool)($input['apply_gst'] ?? true),
            discountAmount: (float)($input['discount_amount'] ?? 0),
            amountPaid: (float)($input['amount_paid'] ?? 0),
            expectedGrandTotal: (float)($input['expected_grand_total'] ?? 0),
            items: $items,
            notes: $input['notes'] ?? null
        );

        try {
            $userId = $user->data->user_id ?? '';
            $invoice = $this->service->createInvoice($dto, $userId);
            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Invoice created successfully',
                'invoice' => $invoice
            ]);
        } catch (ValidationException $e) {
            http_response_code(422);
            echo json_encode(['error' => $e->getMessage()]);
        } catch (Exception $e) {
            error_log('Invoice creation error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }

    /**
     * GET /api/invoices/{id}
     */
    public function show(string $id): void
    {
        header('Content-Type: application/json');
        AuthMiddleware::authenticate();

        try {
            $invoice = $this->service->getInvoice($id);
            if (!$invoice) {
                http_response_code(404);
                echo json_encode(['error' => 'Invoice not found']);
                return;
            }
            if ($invoice->invoiceStatus === 'deleted') {
                http_response_code(410);
                echo json_encode(['error' => 'This bill has been deleted']);
                return;
            }
            echo json_encode($invoice);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to load invoice']);
        }
    }

    /**
     * POST /api/invoices/{id}/cancel
     */
    public function cancel(string $id): void
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        AuthMiddleware::authenticate();

        try {
            $invoice = $this->service->cancelInvoice($id);
            echo json_encode([
                'success' => true,
                'message' => 'Invoice cancelled successfully',
                'invoice' => $invoice
            ]);
        } catch (ValidationException $e) {
            http_response_code(422);
            echo json_encode(['error' => $e->getMessage()]);
        } catch (Exception $e) {
            error_log('Invoice cancel error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to cancel invoice']);
        }
    }

    /**
     * POST /api/invoices/{id}/return
     */
    public function returnItems(string $id): void
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
        $reason = $input['reason'] ?? null;

        if (empty($items)) {
            http_response_code(422);
            echo json_encode(['error' => 'No return items provided']);
            return;
        }

        try {
            $result = $this->service->returnItems($id, $items, $reason);
            $response = [
                'success' => true,
                'message' => 'Return processed successfully',
                'returns' => $result['returns']
            ];
            if (!empty($result['excess_refund'])) {
                $response['warning'] = "Refund ₹" . number_format($result['excess_refund'], 2) . " more than outstanding balance. ₹" . number_format($result['excess_refund'], 2) . " cash to be returned to customer.";
            }
            if (!empty($result['stock_warning'])) {
                $response['stock_warning'] = $result['stock_warning'];
            }
            echo json_encode($response);
        } catch (ValidationException $e) {
            http_response_code(422);
            echo json_encode(['error' => $e->getMessage()]);
        } catch (Exception $e) {
            error_log('Return error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to process return']);
        }
    }

    /**
     * GET /api/customers/{id}/ledger
     */
    public function customerLedger(string $id): void
    {
        header('Content-Type: application/json');
        AuthMiddleware::authenticate();

        $limit = (int)($_GET['limit'] ?? 20);

        try {
            $balance = $this->service->getCustomerBalance($id);
            $entries = $this->service->getCustomerLedger($id, $limit);

            echo json_encode([
                'customerId' => $id,
                'balance' => $balance,
                'entries' => $entries
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to load customer ledger']);
        }
    }

    /**
     * GET /api/invoices/{id}/receipt
     */
    public function receipt(string $id): void
    {
        AuthMiddleware::authenticate();

        try {
            $invoice = $this->service->getInvoice($id);
            if (!$invoice) {
                http_response_code(404);
                echo 'Invoice not found';
                return;
            }
            if ($invoice->invoiceStatus === 'deleted') {
                http_response_code(410);
                echo 'This bill has been deleted';
                return;
            }

            header('Content-Type: text/html; charset=utf-8');
            require __DIR__ . '/../../../../views/billing/receipt.php';
        } catch (\Throwable $e) {
            http_response_code(500);
            echo 'Failed to load receipt';
        }
    }
}
