<?php
namespace Modules\Customer\Service;

use Modules\Customer\DTO\CreditSaleDTO;
use Modules\Customer\Model\CreditLedger;
use Modules\Customer\Repository\CustomerRepository;
use Modules\Customer\Repository\CreditLedgerRepository;
use Modules\Auth\Validation\ValidationException;

/**
 * CreditService — manages the full credit sale lifecycle:
 *   1. Load customer
 *   2. Validate credit limit (outstanding + total <= credit_limit)
 *   3. Insert invoice via Billing module
 *   4. Insert ledger debit entry
 *   5. Commit atomically
 *
 * Return lifecycle:
 *   When a return is processed via the Billing module it fires
 *   recordReturn() here so the customer ledger gets a credit entry.
 */
class CreditService
{
    private CustomerRepository    $customerRepo;
    private CreditLedgerRepository $ledgerRepo;

    public function __construct()
    {
        $this->customerRepo = new CustomerRepository();
        $this->ledgerRepo   = new CreditLedgerRepository();
    }

    // ── Credit Sale ───────────────────────────────────────────

    /**
     * Creates a credit sale for the customer.
     * Validates credit limit, inserts a ledger debit, and returns result.
     *
     * This method does NOT directly insert into the invoices table —
     * that is handled by the Billing module's InvoiceController.
     * Call this method AFTER the invoice has been committed so
     * the invoice_id can be linked to the ledger entry.
     *
     * @throws ValidationException
     */
    public function recordCreditSale(
        string  $customerId,
        float   $amount,
        string  $invoiceId,
        string  $userId,
        ?string $notes = null
    ): CreditLedger {
        if ($amount <= 0) {
            throw new ValidationException("Credit sale amount must be greater than zero.");
        }

        $customer = $this->customerRepo->findCustomerById($customerId);
        if (!$customer) {
            throw new ValidationException("Customer not found.");
        }
        if (($customer['status'] ?? 'active') === 'inactive') {
            throw new ValidationException("Cannot record credit sale for an inactive customer.");
        }

        $this->customerRepo->beginTransaction();
        try {
            // Lock the latest ledger row to prevent race conditions
            $currentBalance = $this->ledgerRepo->getLatestBalanceForUpdate($customerId);
            $newBalance      = $currentBalance + $amount;

            // ── Credit limit check ────────────────────────────────
            $creditLimit = (float)($customer['credit_limit'] ?? 0);
            if ($creditLimit > 0 && $newBalance > $creditLimit) {
                $available = max(0, $creditLimit - $currentBalance);
                throw new ValidationException(
                    "Credit limit exceeded. Limit: ₹{$creditLimit}, "
                    . "Current balance: ₹{$currentBalance}, "
                    . "Requested: ₹{$amount}, "
                    . "Available: ₹{$available}"
                );
            }

            // ── Ledger debit entry ────────────────────────────────
            $ledgerEntry = new CreditLedger(
                id: null,
                userId: $userId,
                customerId: $customerId,
                entryType: 'invoice',
                debit: $amount,
                credit: 0,
                balance: $newBalance,
                invoiceId: $invoiceId,
                notes: $notes ?? "Credit sale – Invoice #{$invoiceId}"
            );

            $saved = $this->ledgerRepo->insert($ledgerEntry);

            $this->customerRepo->commit();
            $this->invalidateCache();

            return $saved;
        } catch (ValidationException $e) {
            $this->customerRepo->rollback();
            throw $e;
        } catch (\Exception $e) {
            $this->customerRepo->rollback();
            throw $e;
        }
    }

    // ── Return Lifecycle ──────────────────────────────────────

    /**
     * Records a return credit entry against the customer's ledger.
     * Called by the Billing module after a return is processed.
     *
     * @throws ValidationException
     */
    public function recordReturn(
        string  $customerId,
        float   $returnAmount,
        string  $invoiceId,
        string  $userId,
        ?string $notes = null
    ): CreditLedger {
        if ($returnAmount <= 0) {
            throw new ValidationException("Return amount must be greater than zero.");
        }

        $customer = $this->customerRepo->findCustomerById($customerId);
        if (!$customer) {
            throw new ValidationException("Customer not found.");
        }

        $this->customerRepo->beginTransaction();
        try {
            $currentBalance = $this->ledgerRepo->getLatestBalanceForUpdate($customerId);
            $newBalance      = max(0, $currentBalance - $returnAmount);

            $ledgerEntry = new CreditLedger(
                id: null,
                userId: $userId,
                customerId: $customerId,
                entryType: 'return',
                debit: 0,
                credit: $returnAmount,
                balance: $newBalance,
                invoiceId: $invoiceId,
                notes: $notes ?? "Return credit – Invoice #{$invoiceId}"
            );

            $saved = $this->ledgerRepo->insert($ledgerEntry);

            $this->customerRepo->commit();
            $this->invalidateCache();

            return $saved;
        } catch (\Exception $e) {
            $this->customerRepo->rollback();
            throw $e;
        }
    }

    // ── Credit Limit Check (read-only) ────────────────────────

    /**
     * Returns whether the given amount would exceed the credit limit.
     * Useful for pre-validation before opening the sale form.
     */
    public function checkCreditLimit(string $customerId, float $amount): array
    {
        $customer = $this->customerRepo->findCustomerById($customerId);
        if (!$customer) {
            return ['allowed' => false, 'reason' => 'Customer not found'];
        }

        $creditLimit = (float)($customer['credit_limit'] ?? 0);
        if ($creditLimit <= 0) {
            return ['allowed' => true, 'reason' => 'No credit limit set'];
        }

        $currentBalance = $this->ledgerRepo->getLatestBalance($customerId);
        $projected      = $currentBalance + $amount;
        $available      = max(0, $creditLimit - $currentBalance);

        return [
            'allowed'         => $projected <= $creditLimit,
            'credit_limit'    => $creditLimit,
            'current_balance' => $currentBalance,
            'requested'       => $amount,
            'projected'       => $projected,
            'available'       => $available,
            'reason'          => $projected <= $creditLimit
                ? 'Within credit limit'
                : "Exceeds limit by ₹" . round($projected - $creditLimit, 2)
        ];
    }

    // ── Private ───────────────────────────────────────────────

    private function invalidateCache(): void
    {
        $service = new \Core\Cache\CacheInvalidationService();
        $service->invalidatePatterns(
            ['credit:*', 'billing:invoices:*'],
            ['operation' => 'creditMutation', 'source' => 'CreditService']
        );
    }
}
