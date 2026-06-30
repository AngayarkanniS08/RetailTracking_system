<?php
namespace Modules\Customer\Controller\Api;

use Modules\Customer\DTO\CustomerDTO;
use Modules\Customer\DTO\CreditPaymentDTO;
use Modules\Customer\Service\CustomerService;
use Modules\Customer\Repository\CustomerRepository;
use Core\Middlewares\AuthMiddleware;
use Modules\Auth\Validation\ValidationException;
use Exception;

class CustomerController
{
    private CustomerService $service;

    public function __construct()
    {
        $repo = new CustomerRepository();
        $this->service = new CustomerService($repo);
    }

    /**
     * GET /api/customers
     */
    public function index(): void
    {
        header('Content-Type: application/json');
        $user = AuthMiddleware::authenticate();

        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 20);
        $search = $_GET['search'] ?? null;

        try {
            $result = $this->service->getCustomers($page, $limit, $search);
            echo json_encode($result);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to load customers: ' . $e->getMessage()]);
        }
    }

    /**
     * POST /api/customers
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

        $dto = CustomerDTO::fromRequest($input);

        try {
            $userId = $user->data->user_id ?? '';
            $customer = $this->service->createCustomer($dto, $userId);
            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Customer created successfully',
                'customer' => $customer
            ]);
        } catch (ValidationException $e) {
            http_response_code(422);
            echo json_encode(['error' => $e->getMessage()]);
        } catch (Exception $e) {
            error_log('Customer creation error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }

    /**
     * GET /api/customers/{id}
     */
    public function show(string $id): void
    {
        header('Content-Type: application/json');
        AuthMiddleware::authenticate();

        try {
            $customer = $this->service->getCustomer($id);
            if (!$customer) {
                http_response_code(404);
                echo json_encode(['error' => 'Customer not found']);
                return;
            }

            $summary = $this->service->getCustomerSummary($id);
            $customer['summary'] = $summary;

            echo json_encode($customer);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to load customer']);
        }
    }

    /**
     * PUT /api/customers/{id}
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

        $dto = CustomerDTO::fromRequest($input);

        try {
            $result = $this->service->updateCustomer($id, $dto);
            echo json_encode([
                'success' => true,
                'message' => 'Customer updated successfully',
                'customer' => $result
            ]);
        } catch (ValidationException $e) {
            http_response_code(422);
            echo json_encode(['error' => $e->getMessage()]);
        } catch (Exception $e) {
            error_log('Customer update error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }

    /**
     * POST /api/customers/{id}/pay
     */
    public function pay(string $id): void
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        $user = AuthMiddleware::authenticate();
        $input = json_decode(file_get_contents('php://input'), true);
        $input['customer_id'] = $id;

        $dto = CreditPaymentDTO::fromRequest($input);

        try {
            $userId = $user->data->user_id ?? '';
            $result = $this->service->recordPayment($dto, $userId);
            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Payment recorded successfully',
                'data' => $result
            ]);
        } catch (ValidationException $e) {
            http_response_code(422);
            echo json_encode(['error' => $e->getMessage()]);
        } catch (Exception $e) {
            error_log('Payment recording error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }

    /**
     * GET /api/customers/{id}/ledger
     */
    public function ledger(string $id): void
    {
        header('Content-Type: application/json');
        AuthMiddleware::authenticate();

        $limit = (int)($_GET['limit'] ?? 50);

        try {
            $balance = $this->service->getCustomerBalance($id);
            $entries = $this->service->getCustomerLedger($id, $limit);
            $entriesArr = array_map(fn($e) => [
                'id' => $e->id,
                'entry_type' => $e->entryType,
                'debit' => $e->debit,
                'credit' => $e->credit,
                'balance' => $e->balance,
                'notes' => $e->notes,
                'invoice_id' => $e->invoiceId,
                'created_at' => $e->createdAt,
                'payment_receipt' => $e->entryType === 'payment' && $e->notes && preg_match('/\[(PAY-\d+-\d+)\]/', $e->notes, $m) ? $m[1] : null
            ], $entries);

            echo json_encode([
                'customerId' => $id,
                'balance' => $balance,
                'entries' => $entriesArr
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to load ledger']);
        }
    }
}
