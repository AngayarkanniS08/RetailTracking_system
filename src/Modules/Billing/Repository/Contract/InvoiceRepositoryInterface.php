<?php
namespace Modules\Billing\Repository\Contract;

use Modules\Billing\Model\Invoice;
use Modules\Billing\Model\InvoiceItem;
use Modules\Billing\Model\InvoiceReturn;
use Modules\Billing\Model\StockMovement;
use Modules\Billing\Model\CustomerLedger;

interface InvoiceRepositoryInterface {
    public function beginTransaction(): void;
    public function commit(): void;
    public function rollback(): void;

    // ── Customer ───────────────────────────────────────────
    public function findCustomerById(string $id): ?array;

    // ── Product ────────────────────────────────────────────
    public function findProductById(string $id): ?array;

    // ── Batch / Stock ──────────────────────────────────────
    public function findBatchById(string $id): ?array;
    public function findAvailableBatch(string $productId, float $requestedQty = 0): ?array;

    // ── Invoice ────────────────────────────────────────────
    public function createInvoice(Invoice $invoice): Invoice;
    public function findInvoiceById(string $id, bool $withItems = false): ?Invoice;
    public function findAllInvoices(int $page, int $limit, array $filters = []): array;
    public function updateInvoiceStatus(string $id, string $status): bool;

    // ── Invoice items ──────────────────────────────────────
    public function createInvoiceItems(array $items, string $invoiceId): void;

    // ── Sequence ───────────────────────────────────────────
    public function getNextInvoiceNumber(string $userId, string $year): string;

    // ── Returns ────────────────────────────────────────────
    public function createReturn(InvoiceReturn $return): InvoiceReturn;
    public function findReturnsByInvoice(string $invoiceId): array;
    public function getTotalReturnedQty(string $invoiceItemId): float;
    public function lockInvoiceRow(string $id): void;
    public function areAllItemsReturned(string $invoiceId): bool;

    // ── Stock ──────────────────────────────────────────────
    public function decrementBatchStock(string $batchId, float $qty): void;
    public function incrementBatchStock(string $batchId, float $qty): void;
    public function refreshStockList(): void;
    public function createStockMovement(StockMovement $movement): void;

    // ── Customer ledger ────────────────────────────────────
    public function addLedgerEntry(CustomerLedger $entry): CustomerLedger;
    public function getCustomerBalance(string $customerId): float;
    public function getCustomerLedger(string $customerId, int $limit = 20): array;
}
