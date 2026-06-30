<?php
namespace Modules\Customer\Service;

use Modules\Customer\DTO\CustomerDTO;
use Modules\Customer\DTO\CreditPaymentDTO;
use Modules\Customer\Model\Customer;
use Modules\Customer\Model\CreditPayment;
use Modules\Customer\Model\CreditLedger;
use Modules\Customer\Repository\Contract\CustomerRepositoryInterface;
use Modules\Auth\Validation\ValidationException;
use Core\Cache\ValkeyCache;

class CustomerService
{
    private CustomerRepositoryInterface $repo;

    public function __construct(CustomerRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    /**
     * @throws ValidationException
     */
    public function createCustomer(CustomerDTO $dto, string $userId): Customer
    {
        if (empty(trim($dto->name))) {
            throw new ValidationException("Customer name is required");
        }
        if (empty(trim($dto->phone)) || !preg_match('/^[0-9]{10,15}$/', $dto->phone)) {
            throw new ValidationException("Valid 10-15 digit phone number is required");
        }
        if ($dto->creditLimit < 0) {
            throw new ValidationException("Credit limit cannot be negative");
        }
        if ($dto->openingBalance < 0) {
            throw new ValidationException("Opening balance cannot be negative");
        }

        $customer = new Customer(
            id: null,
            userId: $userId,
            name: trim($dto->name),
            phone: trim($dto->phone),
            email: $dto->email ? trim($dto->email) : null,
            gstin: $dto->gstin ? strtoupper(trim($dto->gstin)) : null,
            address: $dto->address ? trim($dto->address) : null,
            creditLimit: $dto->creditLimit,
            openingBalance: $dto->openingBalance,
            status: $dto->status ?? 'active'
        );

        $this->repo->beginTransaction();
        try {
            $saved = $this->repo->createCustomer($customer);

            if ($saved->openingBalance > 0) {
                $this->repo->getCustomerLedger($saved->id, 1);
                $ledgerEntry = new CreditLedger(
                    id: null,
                    userId: $userId,
                    customerId: $saved->id,
                    entryType: 'opening',
                    debit: $saved->openingBalance,
                    credit: 0,
                    balance: $saved->openingBalance,
                    notes: "Opening balance"
                );
                $this->addLedgerEntry($ledgerEntry);
            }

            $this->repo->commit();
            $this->invalidateCaches();
        } catch (\Exception $e) {
            $this->repo->rollback();

            if ($e->getCode() === '23505' || (strpos($e->getMessage(), 'unique') !== false)) {
                throw new ValidationException("A customer with this phone number already exists");
            }
            throw $e;
        }

        return $saved;
    }

    /**
     * @throws ValidationException
     */
    public function updateCustomer(string $id, CustomerDTO $dto): array
    {
        $existing = $this->repo->findCustomerById($id);
        if (!$existing) {
            throw new ValidationException("Customer not found");
        }

        $data = [];
        if (!empty(trim($dto->name))) $data['name'] = trim($dto->name);
        if (!empty(trim($dto->phone))) $data['phone'] = trim($dto->phone);
        if ($dto->email !== null) $data['email'] = trim($dto->email);
        if ($dto->gstin !== null) $data['gstin'] = strtoupper(trim($dto->gstin));
        if ($dto->address !== null) $data['address'] = trim($dto->address);
        if ($dto->creditLimit >= 0) $data['credit_limit'] = $dto->creditLimit;
        if ($dto->status !== null) $data['status'] = $dto->status;

        if (empty($data)) {
            throw new ValidationException("No fields to update");
        }

        $updated = $this->repo->updateCustomer($id, $data);
        if (!$updated) {
            throw new ValidationException("Failed to update customer");
        }

        $this->invalidateCaches();
        return $this->repo->findCustomerById($id) ?? [];
    }

    /**
     * @throws ValidationException
     */
    public function getCustomer(string $id): ?array
    {
        return $this->repo->findCustomerById($id);
    }

    public function getCustomers(int $page = 1, int $limit = 20, ?string $search = null): array
    {
        $result = $this->repo->findAllCustomers($page, $limit, $search);

        $dataWithSummaries = [];
        foreach ($result['data'] as $customer) {
            $summary = $this->repo->getCustomerSummary($customer['id']);
            $dataWithSummaries[] = array_merge($customer, $summary);
        }

        return [
            'data' => $dataWithSummaries,
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit
        ];
    }

    /**
     * @throws ValidationException
     */
    public function recordPayment(CreditPaymentDTO $dto, string $userId): array
    {
        if (empty($dto->customerId)) {
            throw new ValidationException("Customer ID is required");
        }
        if ($dto->amount <= 0) {
            throw new ValidationException("Payment amount must be positive");
        }

        $customer = $this->repo->findCustomerById($dto->customerId);
        if (!$customer) {
            throw new ValidationException("Customer not found");
        }
        if (($customer['status'] ?? 'active') === 'inactive') {
            throw new ValidationException("Cannot record payment for inactive customer");
        }

        $items = $this->repo->getOutstandingInvoiceItems($dto->customerId);

        $this->repo->beginTransaction();
        try {
            $currentBalance = $this->getCustomerBalanceWithLock($dto->customerId);

            if ($dto->amount > $currentBalance) {
                throw new ValidationException(
                    "Payment amount ₹{$dto->amount} exceeds outstanding balance ₹{$currentBalance}"
                );
            }

            $newBalance = $currentBalance - $dto->amount;

            $ledgerEntry = new CreditLedger(
                id: null,
                userId: $userId,
                customerId: $dto->customerId,
                entryType: 'payment',
                debit: 0,
                credit: $dto->amount,
                balance: max($newBalance, 0),
                notes: $dto->notes ?? "Payment received"
            );

            $savedLedger = $this->addLedgerEntry($ledgerEntry);

            $receiptNumber = $this->repo->getNextReceiptNumber($userId, date('Y'));

            $payment = new CreditPayment(
                id: null,
                userId: $userId,
                customerId: $dto->customerId,
                ledgerId: $savedLedger->id,
                receiptNumber: $receiptNumber,
                amount: $dto->amount,
                notes: $dto->notes
            );

            $savedPayment = $this->repo->createPayment($payment);

            $this->repo->commit();
            $this->invalidateCaches();
        } catch (ValidationException $e) {
            $this->repo->rollback();
            throw $e;
        } catch (\Exception $e) {
            $this->repo->rollback();
            throw $e;
        }

        return [
            'payment' => [
                'id' => $savedPayment->id,
                'receipt_number' => $savedPayment->receiptNumber,
                'amount' => $savedPayment->amount,
                'notes' => $savedPayment->notes,
                'created_at' => $savedPayment->createdAt
            ],
            'ledger' => [
                'id' => $savedLedger->id,
                'balance_before' => $currentBalance,
                'balance_after' => max($newBalance, 0),
                'entry_type' => $savedLedger->entryType
            ],
            'customer' => [
                'id' => $customer['id'],
                'name' => $customer['name']
            ],
            'items' => $items
        ];
    }

    public function getCustomerBalance(string $customerId): float
    {
        return $this->repo->getCustomerBalance($customerId);
    }

    public function getCustomerLedger(string $customerId, int $limit = 20): array
    {
        return $this->repo->getCustomerLedger($customerId, $limit);
    }

    public function getCustomerSummary(string $customerId): array
    {
        return $this->repo->getCustomerSummary($customerId);
    }

    // ── Private helpers ──────────────────────────────────────

    private function getCustomerBalanceWithLock(string $customerId): float
    {
        $db = \Config\Database::getConnection();
        $stmt = $db->prepare("
            SELECT balance FROM customer_ledger
            WHERE customer_id = ? AND user_id = current_setting('app.current_user_id')::uuid
            ORDER BY created_at DESC
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute([$customerId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? (float)$row['balance'] : 0;
    }

    private function addLedgerEntry(CreditLedger $entry): CreditLedger
    {
        $db = \Config\Database::getConnection();
        $stmt = $db->prepare("
            INSERT INTO customer_ledger (
                id, user_id, customer_id, invoice_id,
                entry_type, debit, credit, balance, notes, created_at
            ) VALUES (
                gen_random_uuid(),
                current_setting('app.current_user_id')::uuid, ?, ?,
                ?, ?, ?, ?, ?, clock_timestamp()
            )
            RETURNING id, user_id, customer_id, invoice_id,
                      entry_type, debit, credit, balance, notes, created_at
        ");
        $stmt->execute([
            $entry->customerId,
            $entry->invoiceId,
            $entry->entryType,
            $entry->debit,
            $entry->credit,
            $entry->balance,
            $entry->notes
        ]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return new CreditLedger(
            id: $row['id'],
            userId: $row['user_id'],
            customerId: $row['customer_id'],
            entryType: $row['entry_type'],
            debit: (float)$row['debit'],
            credit: (float)$row['credit'],
            balance: (float)$row['balance'],
            invoiceId: $row['invoice_id'],
            notes: $row['notes'],
            createdAt: $row['created_at']
        );
    }

    private function invalidateCaches(): void
    {
        try {
            $valkey = ValkeyCache::getClient();
            foreach (['credit:*', 'billing:invoices:*'] as $pattern) {
                $keys = $valkey->keys($pattern);
                if ($keys) {
                    $valkey->del($keys);
                }
            }
        } catch (\Exception $e) {
            error_log('Valkey credit cache invalidation failed: ' . $e->getMessage());
        }
    }
}
