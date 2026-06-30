<?php
namespace Modules\Customer\Repository\Contract;

use Modules\Customer\Model\Customer;
use Modules\Customer\Model\CreditPayment;

interface CustomerRepositoryInterface
{
    public function beginTransaction(): void;
    public function commit(): void;
    public function rollback(): void;

    // ── Customer CRUD ────────────────────────────────────────
    public function createCustomer(Customer $customer): Customer;
    public function findCustomerById(string $id): ?array;
    public function findAllCustomers(int $page, int $limit, ?string $search = null): array;
    public function updateCustomer(string $id, array $data): bool;

    // ── Ledger ─────────────────────────────────────────────
    public function getCustomerBalance(string $customerId): float;
    public function getCustomerLedger(string $customerId, int $limit = 20, int $offset = 0): array;
    public function getCustomerSummary(string $customerId): array;

    // ── Invoice items for receipts ──────────────────────
    public function getOutstandingInvoiceItems(string $customerId): array;

    // ── Payments ───────────────────────────────────────────
    public function createPayment(CreditPayment $payment): CreditPayment;
    public function getNextReceiptNumber(string $userId, string $year): string;
    public function findPaymentByLedgerId(string $ledgerId): ?array;
}
