<?php
namespace Modules\Customer\Controller\Api;

use Modules\Customer\Repository\CreditLedgerRepository;
use Modules\Customer\Repository\CustomerRepository;
use Core\Middlewares\AuthMiddleware;

/**
 * LedgerController — standalone ledger-level endpoints.
 *
 * Routes:
 *   GET  /api/customers/{id}/ledger/entries   → entries() paginated
 *   GET  /api/customers/{id}/ledger/summary   → summary()
 *   GET  /api/customers/{id}/ledger/balance   → balance()
 */
class LedgerController
{
    private CreditLedgerRepository $ledgerRepo;
    private CustomerRepository     $customerRepo;

    public function __construct()
    {
        $this->ledgerRepo   = new CreditLedgerRepository();
        $this->customerRepo = new CustomerRepository();
    }

    /**
     * GET /api/customers/{id}/ledger/entries
     * Query params: limit, offset, type (invoice|payment|return|opening)
     */
    public function entries(string $customerId): void
    {
        header('Content-Type: application/json');
        AuthMiddleware::authenticate();

        $limit  = min((int)($_GET['limit']  ?? 50), 200);
        $offset = (int)($_GET['offset'] ?? 0);
        $type   = $_GET['type'] ?? null;

        try {
            $entries = $this->ledgerRepo->findByCustomer($customerId, $limit, $offset, $type);
            $total   = $this->ledgerRepo->countByCustomer($customerId, $type);
            $balance = $this->ledgerRepo->getLatestBalance($customerId);

            $entriesArr = array_map(fn($e) => [
                'id'             => $e->id,
                'entry_type'     => $e->entryType,
                'debit'          => $e->debit,
                'credit'         => $e->credit,
                'balance'        => $e->balance,
                'notes'          => $e->notes,
                'invoice_id'     => $e->invoiceId,
                'invoice_status' => $e->invoiceStatus,
                'created_at'     => $e->createdAt,
                'payment_receipt' => $e->entryType === 'payment' && $e->notes
                    && preg_match('/\[(PAY-\d+-\d+)\]/', $e->notes, $m) ? $m[1] : null
            ], $entries);

            echo json_encode([
                'customer_id' => $customerId,
                'balance'     => $balance,
                'total'       => $total,
                'limit'       => $limit,
                'offset'      => $offset,
                'entries'     => $entriesArr
            ]);
        } catch (\Exception $e) {
            error_log('LedgerController entries error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to load ledger entries: ' . $e->getMessage()]);
        }
    }

    /**
     * GET /api/customers/{id}/ledger/summary
     * Returns aggregated totals from the ledger.
     */
    public function summary(string $customerId): void
    {
        header('Content-Type: application/json');
        AuthMiddleware::authenticate();

        try {
            $summary = $this->ledgerRepo->getSummary($customerId);
            $balance = $this->ledgerRepo->getLatestBalance($customerId);

            $customer = $this->customerRepo->findCustomerById($customerId);
            $creditLimit = (float)($customer['credit_limit'] ?? 0);
            $available   = $creditLimit > 0 ? max(0, $creditLimit - $balance) : null;

            echo json_encode(array_merge($summary, [
                'current_balance' => $balance,
                'credit_limit'    => $creditLimit,
                'available_credit' => $available,
            ]));
        } catch (\Exception $e) {
            error_log('LedgerController summary error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to load ledger summary: ' . $e->getMessage()]);
        }
    }

    /**
     * GET /api/customers/{id}/ledger/balance
     * Returns just the current balance — lightweight polling endpoint.
     */
    public function balance(string $customerId): void
    {
        header('Content-Type: application/json');
        AuthMiddleware::authenticate();

        try {
            $balance  = $this->ledgerRepo->getLatestBalance($customerId);
            $customer = $this->customerRepo->findCustomerById($customerId);
            $limit    = (float)($customer['credit_limit'] ?? 0);

            echo json_encode([
                'customer_id'     => $customerId,
                'balance'         => $balance,
                'credit_limit'    => $limit,
                'available_credit' => $limit > 0 ? max(0, $limit - $balance) : null,
            ]);
        } catch (\Exception $e) {
            error_log('LedgerController balance error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to load ledger balance: ' . $e->getMessage()]);
        }
    }
}
