<?php
namespace Modules\Billing\Service;

use Modules\Billing\DTO\InvoiceDTO;
use Modules\Billing\Model\Invoice;
use Modules\Billing\Model\InvoiceItem;
use Modules\Billing\Model\InvoiceReturn;
use Modules\Billing\Model\StockMovement;
use Modules\Billing\Model\CustomerLedger;
use Modules\Billing\Repository\Contract\InvoiceRepositoryInterface;
use Modules\Auth\Validation\ValidationException;
use Core\Cache\ValkeyCache;

class InvoiceService
{
    private InvoiceRepositoryInterface $repo;

    public function __construct(InvoiceRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    /**
     * @throws ValidationException
     */
    public function createInvoice(InvoiceDTO $dto, string $userId): Invoice
    {
        // ── 1.1 Validate DTO fields ───────────────────────

        if (empty($dto->items)) {
            throw new ValidationException("At least one item is required");
        }
        if ($dto->discountAmount < 0) {
            throw new ValidationException("Discount amount cannot be negative");
        }
        if ($dto->amountPaid < 0) {
            throw new ValidationException("Amount paid cannot be negative");
        }
        if ($dto->expectedGrandTotal <= 0) {
            throw new ValidationException("Invalid expected grand total");
        }
        if ($dto->customerId === null && empty(trim($dto->customerName ?? ''))) {
            throw new ValidationException("Customer name is required for walk-in customers");
        }

        foreach ($dto->items as $itemDTO) {
            if (empty($itemDTO->productId)) {
                throw new ValidationException("Product ID is required for each item");
            }
            if ($itemDTO->quantity <= 0) {
                throw new ValidationException("Quantity must be positive for each item");
            }
            if ($itemDTO->unitPrice < 0) {
                throw new ValidationException("Unit price cannot be negative");
            }
            if ($itemDTO->discountAmount < 0) {
                throw new ValidationException("Item discount amount cannot be negative");
            }
        }

        // ── 1.2 Resolve customer ──────────────────────────

        if ($dto->customerId) {
            $customer = $this->repo->findCustomerById($dto->customerId);
            if (!$customer) {
                throw new ValidationException("Selected customer not found");
            }
            if (($customer['status'] ?? 'active') === 'inactive') {
                throw new ValidationException("Selected customer is inactive");
            }
            $customerNameSnapshot = $customer['name'];
            $customerPhoneSnapshot = $customer['phone'];
            $customerGstinSnapshot = $customer['gstin'];
        } else {
            $customerNameSnapshot = trim($dto->customerName ?? '');
            $customerPhoneSnapshot = trim($dto->customerPhone ?? '') ?: null;
            $customerGstinSnapshot = null;
        }

        // ── 1.3-1.4 Lookup products & batches, validate stock ──

        $itemModels = [];
        $subtotal = 0;
        $totalGst = 0;
        $totalItemDiscount = 0;

        foreach ($dto->items as $itemDTO) {
            $product = $this->repo->findProductById($itemDTO->productId);
            if (!$product) {
                throw new ValidationException("Product not found: {$itemDTO->productId}");
            }

            $batchId = $itemDTO->batchId;
            if ($batchId) {
                $batch = $this->repo->findBatchById($batchId);
                if (!$batch) {
                    throw new ValidationException("Batch not found: {$batchId}");
                }
            } else {
                $batch = $this->repo->findAvailableBatch($itemDTO->productId);
                if (!$batch) {
                    throw new ValidationException("No available stock for product: {$product['name']}");
                }
                $batchId = $batch['id'];
            }

            if ((float)$batch['remaining_qty'] < $itemDTO->quantity) {
                throw new ValidationException(
                    "Insufficient stock for {$product['name']}. Available: {$batch['remaining_qty']}, requested: {$itemDTO->quantity}"
                );
            }

            $gstRate = $dto->applyGst ? (float)($product['gst_rate'] ?? 0) : 0;
            $lineSubtotal = $itemDTO->quantity * $itemDTO->unitPrice;
            $gstAmount = $lineSubtotal * ($gstRate / 100);
            $lineDiscount = $itemDTO->discountAmount;
            $lineTotal = $lineSubtotal - $lineDiscount + $gstAmount;

            $subtotal += $lineSubtotal;
            $totalGst += $gstAmount;
            $totalItemDiscount += $lineDiscount;

            $itemModels[] = new InvoiceItem(
                id: null,
                invoiceId: '',
                productId: $itemDTO->productId,
                quantity: $itemDTO->quantity,
                unitPrice: $itemDTO->unitPrice,
                batchId: $batchId,
                productNameSnapshot: $product['name'],
                hsnCodeSnapshot: $product['hsn_code'],
                unitSnapshot: $product['unit'],
                costPriceSnapshot: (float)($batch['cost_price'] ?? 0),
                gstRateSnapshot: $gstRate,
                gstAmount: $gstAmount,
                discountAmount: $lineDiscount,
                lineTotal: $lineTotal,
                createdAt: null
            );
        }

        // ── 1.5 Calculate financials ──────────────────────

        $totalDiscount = $dto->discountAmount + $totalItemDiscount;
        $beforeRound = $subtotal - $totalDiscount + $totalGst;
        $grandTotal = round($beforeRound);
        $roundOff = $grandTotal - $beforeRound;
        $balanceDue = $grandTotal - $dto->amountPaid;
        if ($balanceDue < 0) {
            $balanceDue = 0;
        }

        // ── 1.6 Validate expectedGrandTotal ───────────────

        if (abs($grandTotal - $dto->expectedGrandTotal) > 0.01) {
            throw new ValidationException(
                "Total mismatch: calculated ₹{$grandTotal}, expected ₹{$dto->expectedGrandTotal}"
            );
        }

        // ── 1.7-1.8 Invoice number & status ───────────────

        $year = date('Y');
        $invoiceNumber = $this->repo->getNextInvoiceNumber($userId, $year);

        $paymentStatus = $balanceDue <= 0 ? 'paid' : ($dto->amountPaid > 0 ? 'partial' : 'pending');

        // ── 1.9 Build Invoice model ───────────────────────

        $invoice = new Invoice(
            id: null,
            userId: $userId,
            invoiceNumber: $invoiceNumber,
            customerId: $dto->customerId,
            customerNameSnapshot: $customerNameSnapshot,
            customerPhoneSnapshot: $customerPhoneSnapshot,
            customerGstinSnapshot: $customerGstinSnapshot,
            subtotal: $subtotal,
            discountAmount: $dto->discountAmount,
            totalGst: $totalGst,
            roundOff: $roundOff,
            grandTotal: $grandTotal,
            amountPaid: $dto->amountPaid,
            balanceDue: $balanceDue,
            invoiceStatus: 'completed',
            paymentStatus: $paymentStatus,
            notes: $dto->notes,
            billedAt: date('Y-m-d H:i:s'),
            createdAt: null,
            updatedAt: null,
            items: null
        );

        // ── 1.10 Execute in transaction ───────────────────

        $this->repo->beginTransaction();
        try {
            $saved = $this->repo->createInvoice($invoice);

            foreach ($itemModels as $itemModel) {
                $itemModel->invoiceId = $saved->id;
            }
            $this->repo->createInvoiceItems($itemModels, $saved->id);

            foreach ($itemModels as $itemModel) {
                $this->repo->decrementBatchStock($itemModel->batchId, $itemModel->quantity);

                $this->repo->createStockMovement(new StockMovement(
                    id: null,
                    userId: $userId,
                    productId: $itemModel->productId,
                    referenceType: 'SALE',
                    movementType: 'OUT',
                    qty: $itemModel->quantity,
                    batchId: $itemModel->batchId,
                    referenceId: $saved->id,
                    createdAt: null
                ));
            }

            $this->repo->refreshStockList();

            if ($dto->customerId) {
                $currentBalance = $this->repo->getCustomerBalance($dto->customerId);

                // Record full invoice amount as debit
                $invoiceBalance = $currentBalance + $grandTotal;
                $this->repo->addLedgerEntry(new CustomerLedger(
                    id: null,
                    userId: $userId,
                    customerId: $dto->customerId,
                    entryType: 'invoice',
                    debit: $grandTotal,
                    credit: 0,
                    balance: $invoiceBalance,
                    invoiceId: $saved->id,
                    notes: "Invoice {$invoiceNumber}",
                    createdAt: null
                ));

                // Record checkout payment as credit so credit page shows total_paid correctly
                if ($dto->amountPaid > 0) {
                    $balanceAfterPayment = $invoiceBalance - $dto->amountPaid;
                    $this->repo->addLedgerEntry(new CustomerLedger(
                        id: null,
                        userId: $userId,
                        customerId: $dto->customerId,
                        entryType: 'payment',
                        debit: 0,
                        credit: $dto->amountPaid,
                        balance: max($balanceAfterPayment, 0),
                        invoiceId: $saved->id,
                        notes: "Checkout payment - Invoice {$invoiceNumber}",
                        createdAt: null
                    ));
                }
            }

            $this->repo->commit();
            $this->invalidateCaches();
        } catch (\Exception $e) {
            $this->repo->rollback();
            throw $e;
        }

        $saved->items = $itemModels;
        return $saved;
    }

    /**
     * @throws ValidationException
     */
    public function cancelInvoice(string $id): Invoice
    {
        $invoice = $this->repo->findInvoiceById($id, true);
        if (!$invoice) {
            throw new ValidationException("Invoice not found");
        }
        if ($invoice->invoiceStatus !== 'completed') {
            throw new ValidationException("Only completed invoices can be cancelled");
        }

        $this->repo->beginTransaction();
        try {
            $this->repo->updateInvoiceStatus($id, 'cancelled');

            if ($invoice->items) {
                foreach ($invoice->items as $item) {
                    if ($item->batchId) {
                        $this->repo->incrementBatchStock($item->batchId, $item->quantity);
                    }

                    $this->repo->createStockMovement(new StockMovement(
                        id: null,
                        userId: $invoice->userId,
                        productId: $item->productId,
                        referenceType: 'SALE',
                        movementType: 'IN',
                        qty: $item->quantity,
                        batchId: $item->batchId,
                        referenceId: $invoice->id,
                        createdAt: null
                    ));
                }
            }

            $this->repo->refreshStockList();

            if ($invoice->balanceDue > 0 && $invoice->customerId) {
                $currentBalance = $this->repo->getCustomerBalance($invoice->customerId);
                $newBalance = $currentBalance - $invoice->balanceDue;

                $this->repo->addLedgerEntry(new CustomerLedger(
                    id: null,
                    userId: $invoice->userId,
                    customerId: $invoice->customerId,
                    entryType: 'return',
                    debit: 0,
                    credit: $invoice->balanceDue,
                    balance: max($newBalance, 0),
                    invoiceId: $invoice->id,
                    notes: "Cancelled invoice {$invoice->invoiceNumber}",
                    createdAt: null
                ));
            }

            $this->repo->commit();
            $this->invalidateCaches();
        } catch (\Exception $e) {
            $this->repo->rollback();
            throw $e;
        }

        $invoice->invoiceStatus = 'cancelled';
        return $invoice;
    }

    /**
     * @throws ValidationException
     */
    public function returnItems(
        string $invoiceId,
        array $items,
        ?string $reason = null
    ): array {
        if (empty($items)) {
            throw new ValidationException("No return items provided");
        }

        $invoice = $this->repo->findInvoiceById($invoiceId, true);
        if (!$invoice) {
            throw new ValidationException("Invoice not found");
        }
        if ($invoice->invoiceStatus !== 'completed') {
            throw new ValidationException("Cannot return items from a {$invoice->invoiceStatus} invoice");
        }

        $this->repo->beginTransaction();
        try {
            $this->repo->lockInvoiceRow($invoiceId);

            $savedReturns = [];
            $totalRefund = 0;
            $stockWarnings = [];

            foreach ($items as $itemData) {
                $invoiceItemId = $itemData['invoice_item_id'];
                $qtyReturned = (float)$itemData['qty_returned'];
                $refundAmount = (float)$itemData['refund_amount'];

                $invoiceItem = null;
                foreach ($invoice->items ?? [] as $item) {
                    if ($item->id === $invoiceItemId) {
                        $invoiceItem = $item;
                        break;
                    }
                }
                if (!$invoiceItem) {
                    throw new ValidationException("Invoice item {$invoiceItemId} not found");
                }

                $alreadyReturned = $this->repo->getTotalReturnedQty($invoiceItemId);
                $returnableQty = $invoiceItem->quantity - $alreadyReturned;
                if ($qtyReturned > $returnableQty) {
                    throw new ValidationException(
                        "Cannot return {$qtyReturned} items. Only {$returnableQty} remaining to return"
                    );
                }
                if ($qtyReturned <= 0) {
                    throw new ValidationException("Return quantity must be positive");
                }
                if ($refundAmount < 0) {
                    throw new ValidationException("Refund amount cannot be negative");
                }

                $invoiceReturn = new InvoiceReturn(
                    id: null,
                    invoiceId: $invoiceId,
                    productId: $invoiceItem->productId,
                    qtyReturned: $qtyReturned,
                    refundAmount: $refundAmount,
                    invoiceItemId: $invoiceItemId,
                    batchId: $invoiceItem->batchId,
                    restockQty: $qtyReturned,
                    reason: $reason,
                    createdAt: null
                );
                $saved = $this->repo->createReturn($invoiceReturn);
                $savedReturns[] = $saved;

                if ($invoiceItem->batchId) {
                    $batch = $this->repo->findBatchById($invoiceItem->batchId);
                    if ($batch) {
                        $this->repo->incrementBatchStock($invoiceItem->batchId, $qtyReturned);
                    } else {
                        $stockWarnings[] = "{$invoiceItem->productNameSnapshot}: batch not found, stock not restocked";
                    }
                }

                $this->repo->createStockMovement(new StockMovement(
                    id: null,
                    userId: $invoice->userId,
                    productId: $invoiceItem->productId,
                    referenceType: 'RETURN',
                    movementType: 'IN',
                    qty: $qtyReturned,
                    batchId: $invoiceItem->batchId,
                    referenceId: $invoiceId,
                    createdAt: null
                ));

                $totalRefund += $refundAmount;
            }

            $this->repo->refreshStockList();

            $excessRefund = 0;
            if ($totalRefund > 0 && $invoice->customerId) {
                $currentBalance = $this->repo->getCustomerBalance($invoice->customerId);
                $newBalance = $currentBalance - $totalRefund;
                if ($newBalance < 0) {
                    $excessRefund = abs($newBalance);
                }

                $this->repo->addLedgerEntry(new CustomerLedger(
                    id: null,
                    userId: $invoice->userId,
                    customerId: $invoice->customerId,
                    entryType: 'return',
                    debit: 0,
                    credit: $totalRefund,
                    balance: max($newBalance, 0),
                    invoiceId: $invoiceId,
                    notes: "Return on invoice {$invoice->invoiceNumber}: {$reason}",
                    createdAt: null
                ));
            }

            if ($this->repo->areAllItemsReturned($invoiceId)) {
                $this->repo->updateInvoiceStatus($invoiceId, 'returned');
            }

            $this->repo->commit();
            $this->invalidateCaches();
        } catch (\Exception $e) {
            $this->repo->rollback();
            throw $e;
        }

        return [
            'returns' => $savedReturns,
            'excess_refund' => $excessRefund,
            'stock_warning' => !empty($stockWarnings)
                ? implode('; ', $stockWarnings)
                : null
        ];
    }

    /**
     * @throws ValidationException
     */
    public function getInvoice(string $id): ?Invoice
    {
        return $this->repo->findInvoiceById($id, true);
    }

    public function getInvoices(int $page = 1, int $limit = 10, array $filters = []): array
    {
        $result = $this->repo->findAllInvoices($page, $limit, $filters);
        return [
            'data' => $result['data'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit
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

    public function getReturnsByInvoice(string $invoiceId): array
    {
        return $this->repo->findReturnsByInvoice($invoiceId);
    }

    private function invalidateCaches(): void
    {
        try {
            $valkey = ValkeyCache::getClient();
            foreach (['billing:invoices:*', 'credit:*', 'reports:*', 'pos:search:*', 'inventory:batches:*'] as $pattern) {
                $keys = $valkey->keys($pattern);
                if ($keys) {
                    $valkey->del($keys);
                }
            }
        } catch (\Exception $e) {
            error_log('Valkey billing cache invalidation failed: ' . $e->getMessage());
        }
    }
}
