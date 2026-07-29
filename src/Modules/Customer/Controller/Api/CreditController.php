<?php
namespace Modules\Customer\Controller\Api;

use Modules\Customer\Service\CreditService;
use Modules\Customer\DTO\CreditSaleDTO;
use Core\Middlewares\AuthMiddleware;
use Modules\Auth\Validation\ValidationException;

/**
 * CreditController — handles credit sale and credit limit check endpoints.
 *
 * Routes:
 *   POST   /api/customers/{id}/credit-sale     → recordCreditSale()
 *   POST   /api/customers/{id}/credit-return   → recordReturn()
 *   GET    /api/customers/{id}/credit-check    → checkCreditLimit()
 */
class CreditController
{
    private CreditService $service;

    public function __construct()
    {
        $this->service = new CreditService();
    }

    /**
     * POST /api/customers/{id}/credit-sale
     *
     * Called by the Billing module AFTER an invoice is committed.
     * Links the invoice to the customer ledger with a debit entry
     * and enforces the credit limit.
     *
     * Body: { invoice_id, amount, notes? }
     */
    public function recordCreditSale(string $customerId): void
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        $user  = AuthMiddleware::authenticate(900);
        $userId = $user->data->user_id ?? '';
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $invoiceId = trim($input['invoice_id'] ?? '');
        $amount    = (float)($input['amount'] ?? 0);
        $notes     = $input['notes'] ?? null;

        if (empty($invoiceId)) {
            http_response_code(422);
            echo json_encode(['error' => 'invoice_id is required']);
            return;
        }
        if ($amount <= 0) {
            http_response_code(422);
            echo json_encode(['error' => 'amount must be greater than zero']);
            return;
        }

        try {
            $ledgerEntry = $this->service->recordCreditSale(
                $customerId,
                $amount,
                $invoiceId,
                $userId,
                $notes
            );

            http_response_code(201);
            echo json_encode([
                'success'      => true,
                'message'      => 'Credit sale recorded in ledger',
                'ledger_entry' => [
                    'id'          => $ledgerEntry->id,
                    'entry_type'  => $ledgerEntry->entryType,
                    'debit'       => $ledgerEntry->debit,
                    'balance'     => $ledgerEntry->balance,
                    'invoice_id'  => $ledgerEntry->invoiceId,
                    'created_at'  => $ledgerEntry->createdAt,
                ]
            ]);
        } catch (ValidationException $e) {
            http_response_code(422);
            echo json_encode(['error' => $e->getMessage()]);
        } catch (\Exception $e) {
            error_log('CreditController::recordCreditSale error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to record credit sale']);
        }
    }

    /**
     * POST /api/customers/{id}/credit-return
     *
     * Records a return credit entry on the customer ledger.
     * Called after a Billing return is processed.
     *
     * Body: { invoice_id, amount, notes? }
     */
    public function recordReturn(string $customerId): void
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        $user   = AuthMiddleware::authenticate(900);
        $userId = $user->data->user_id ?? '';
        $input  = json_decode(file_get_contents('php://input'), true) ?? [];

        $invoiceId = trim($input['invoice_id'] ?? '');
        $amount    = (float)($input['amount'] ?? 0);
        $notes     = $input['notes'] ?? null;

        if (empty($invoiceId)) {
            http_response_code(422);
            echo json_encode(['error' => 'invoice_id is required']);
            return;
        }
        if ($amount <= 0) {
            http_response_code(422);
            echo json_encode(['error' => 'amount must be greater than zero']);
            return;
        }

        try {
            $ledgerEntry = $this->service->recordReturn(
                $customerId,
                $amount,
                $invoiceId,
                $userId,
                $notes
            );

            http_response_code(201);
            echo json_encode([
                'success'      => true,
                'message'      => 'Return recorded in ledger',
                'ledger_entry' => [
                    'id'          => $ledgerEntry->id,
                    'entry_type'  => $ledgerEntry->entryType,
                    'credit'      => $ledgerEntry->credit,
                    'balance'     => $ledgerEntry->balance,
                    'invoice_id'  => $ledgerEntry->invoiceId,
                    'created_at'  => $ledgerEntry->createdAt,
                ]
            ]);
        } catch (ValidationException $e) {
            http_response_code(422);
            echo json_encode(['error' => $e->getMessage()]);
        } catch (\Exception $e) {
            error_log('CreditController::recordReturn error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to record return']);
        }
    }

    /**
     * GET /api/customers/{id}/credit-check?amount=5000
     *
     * Pre-validates whether a given amount would exceed the credit limit.
     * Returns allowed flag + remaining headroom.
     */
    public function checkCreditLimit(string $customerId): void
    {
        header('Content-Type: application/json');
        AuthMiddleware::authenticate();

        $amount = (float)($_GET['amount'] ?? 0);

        try {
            $result = $this->service->checkCreditLimit($customerId, $amount);
            echo json_encode($result);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to check credit limit']);
        }
    }
}
