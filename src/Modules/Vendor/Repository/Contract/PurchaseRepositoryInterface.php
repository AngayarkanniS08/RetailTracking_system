<?php 
namespace Modules\Vendor\Repository\Contract;

use Modules\Vendor\Model\Vendor;
use Modules\Vendor\Model\Purchase;
use Modules\Vendor\Model\PurchaseItem;

interface PurchaseRepositoryInterface {
    public function beginTransaction(): void;
    public function commit(): void;
    public function rollback(): void;

    //vendor methods
    public function findOrCreateVendor(string $name, string $phone): Vendor;
    public function findVendorById(string $id, bool $withPurchases = false): ?Vendor;
    public function findAllVendors(): array;
    public function updateVendor(Vendor $vendor): Vendor;
    public function deleteVendor(string $id): bool;
    //purchase methods
    public function createPurchase(Purchase $purchase): Purchase;
    public function findPurchaseById(string $id, bool $withItems = false): ?Purchase;
    public function findAllPurchases(int $page, int $limit, array $filters):array;
    public function updatePurchase(Purchase $purchase): Purchase;
    public function replacePurchaseItems(string $purchaseId, array $items): void;
    public function findAllVendorHistory(): array;
    //purchase item methods
    public function createPurchaseItems(array $items, string $purchaseId): void;

    //payment methods
    public function recordPayment(string $purchaseId, float $amount): bool;

    //vendor history methods
    public function getVendorHistory(string $vendorId): array;
    public function getVendorBalance(string $vendorId): array;
    public function getGlobalPurchaseStats(): array;
}