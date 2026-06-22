<?php
namespace Modules\Vendor\Service;

use Modules\Vendor\DTO\PurchaseDTO;
use Modules\Vendor\DTO\PurchaseItemDTO;
use Modules\Vendor\Model\Purchase;
use Modules\Vendor\Model\PurchaseItem;
use Modules\Vendor\Model\Vendor;
use Modules\Vendor\Repository\Contract\PurchaseRepositoryInterface;
use Modules\Auth\Validation\ValidationException;
use App\Common\Helpers\ArrayHelper;
use Core\Cache\ValkeyCache;

class PurchaseService
{
    private PurchaseRepositoryInterface $repo;

    public function __construct(
        PurchaseRepositoryInterface $repo,
        $productService = null,
        $batchService = null
    ) {
        $this->repo = $repo;
    }

    /**
     * Create a new purchase (vendor + header + items)
     * @throws ValidationException
     */
    public function createPurchase(PurchaseDTO $dto, string $userId): Purchase
    {
        // 1. Validate input
        if (empty(trim($dto->vendorName))) {
            throw new ValidationException("Vendor name is required");
        }
        if (empty(trim($dto->phone))) {
            throw new ValidationException("Vendor contact number is required");
        }
        if (!preg_match('/^[0-9]{10,15}$/', $dto->phone)) {
            throw new ValidationException("Vendor contact number must contain only digits and be between 10 and 15 digits long");
        }
        if (empty($dto->items)) {
            throw new ValidationException("At least one item is required");
        }
        if ($dto->baseAmount <= 0) {
            throw new ValidationException("Base amount must be positive");
        }
        if ($dto->amountPaid < 0) {
            throw new ValidationException("Amount paid cannot be negative");
        }
        // Calculate total (base + GST) from items
        $totalAmount = 0;
        foreach ($dto->items as $item) {
            $itemTotal = $item->quantity * $item->unitPrice;
            $gstAmount = $itemTotal * ($item->gstRate / 100);
            $totalAmount += $itemTotal + $gstAmount;
        }
        if ($dto->amountPaid > $totalAmount) {
            throw new ValidationException("Amount paid cannot exceed total amount");
        }

        // 2. Validate each item
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
        }

        // 3. Find or create vendor
        $vendor = $this->repo->findOrCreateVendor($dto->vendorName, $dto->phone);

        // 4. Create purchase header
        $purchase = new Purchase(
            id: null,
            vendorId: $vendor->id,
            purchaseDate: $dto->purchaseDate,
            baseAmount: $dto->baseAmount,
            amountPaid: $dto->amountPaid,
            status: $this->determineStatus($dto->amountPaid, $dto->baseAmount),
            userId: $userId,
            createdAt: null,
            updatedAt: null,
            items: null
        );

        // 5. Save purchase header + items in a single transaction
        $this->repo->beginTransaction();
        try {
            $purchase = $this->repo->createPurchase($purchase);

            $items = [];
            foreach ($dto->items as $itemDTO) {
                $items[] = new PurchaseItem(
                    id: null,
                    purchaseId: $purchase->id,
                    productId: $itemDTO->productId,
                    quantity: $itemDTO->quantity,
                    unitPrice: $itemDTO->unitPrice,
                    totalPrice: $itemDTO->quantity * $itemDTO->unitPrice,
                    gstRate: $itemDTO->gstRate
                );
            }
            $this->repo->createPurchaseItems($items, $purchase->id);

            $this->repo->commit();
            $this->invalidateVendorCaches();
        } catch (\Exception $e) {
            $this->repo->rollback();
            throw $e;
        }

        // 6. Load items back into purchase for response
        $purchase->items = $items;

        return $purchase;
    }

    /**
     * Record a payment against a purchase
     */
    public function recordPayment(string $purchaseId, float $amount, string $paymentDate = null): Purchase
    {
        if ($amount <= 0) {
            throw new ValidationException("Payment amount must be positive");
        }

        $purchase = $this->repo->findPurchaseById($purchaseId, true);
        if (!$purchase) {
            throw new ValidationException("Purchase not found");
        }

        if ($purchase->status === 'paid') {
            throw new ValidationException("Purchase is already fully paid");
        }

        // Calculate total with GST
        $totalAmount = $purchase->baseAmount;
        if ($purchase->items) {
            $gstSum = 0;
            foreach ($purchase->items as $item) {
                $gstSum += $item->quantity * $item->unitPrice * ($item->gstRate / 100);
            }
            $totalAmount += $gstSum;
        }

        $newPaid = $purchase->amountPaid + $amount;
        if ($newPaid > $totalAmount) {
            throw new ValidationException("Payment would exceed total amount");
        }

        $success = $this->repo->recordPayment($purchaseId, $amount, $paymentDate);
        if (!$success) {
            throw new ValidationException("Failed to record payment");
        }

        $this->invalidateVendorCaches();

        // Refresh the purchase
        return $this->repo->findPurchaseById($purchaseId);
    }

    /**
     * Get a single purchase with items
     */
    public function getPurchase(string $id, bool $withItems = false): ?Purchase
    {
        return $this->repo->findPurchaseById($id, $withItems);
    }

    /**
     * Get paginated list of purchases
     */
    public function getPurchases(int $page = 1, int $limit = 10, array $filters = []): array
    {
        $result = $this->repo->findAllPurchases($page, $limit, $filters);
        $stats = $this->repo->getGlobalPurchaseStats();
        $pagination = ArrayHelper::getPaginationMeta($page, $limit, $result['total']);
        
        return [
            'data' => $result['data'],
            'pagination' => $pagination,
            'stats' => $stats
        ];
    }

    /**
     * Determine the purchase status based on amount paid
     */
    private function determineStatus(float $amountPaid, float $baseAmount): string
    {
        if ($amountPaid <= 0) {
            return 'pending';
        }
        if ($amountPaid >= $baseAmount) {
            return 'paid';
        }
        return 'partial';
    }

    /**
 * Update an existing purchase (header + items)
 * @throws ValidationException
 */
    public function updatePurchase(string $purchaseId, PurchaseDTO $dto, string $userId): Purchase
    {
        // 1. Validate
        if (empty($dto->items)) {
            throw new ValidationException("At least one item is required");
        }
        if ($dto->baseAmount <= 0) {
            throw new ValidationException("Base amount must be positive");
        }
        if ($dto->amountPaid < 0) {
            throw new ValidationException("Amount paid cannot be negative");
        }
        // Calculate total (base + GST) from items
        $totalAmount = 0;
        foreach ($dto->items as $item) {
            $itemTotal = $item->quantity * $item->unitPrice;
            $gstAmount = $itemTotal * ($item->gstRate / 100);
            $totalAmount += $itemTotal + $gstAmount;
        }

        if ($dto->amountPaid > $totalAmount) {
            throw new ValidationException("Amount paid cannot exceed total amount (₹" . number_format($totalAmount, 2) . ")");
        }

        // 2. Validate each item
        foreach ($dto->items as $itemDTO) {
            if (empty($itemDTO->productId)) {
                throw new ValidationException("Product ID is required for each item");
            }
            if ($itemDTO->quantity <= 0) {
                throw new ValidationException("Quantity must be positive");
            }
            if ($itemDTO->unitPrice < 0) {
                throw new ValidationException("Unit price cannot be negative");
            }
        }

        // 3. Check if purchase exists
        $existing = $this->repo->findPurchaseById($purchaseId);
        if (!$existing) {
            throw new ValidationException("Purchase not found");
        }

        // 4. Update header + replace items in a single transaction
        $this->repo->beginTransaction();
        try {
            $purchase = new Purchase(
                id: $purchaseId,
                vendorId: $existing->vendorId,
                purchaseDate: $dto->purchaseDate,
                baseAmount: $dto->baseAmount,
                amountPaid: $dto->amountPaid,
                status: $this->determineStatus($dto->amountPaid, $dto->baseAmount),
                userId: $userId,
                createdAt: $existing->createdAt,
                updatedAt: null,
                items: null
            );
            $purchase = $this->repo->updatePurchase($purchase);

            $items = [];
            foreach ($dto->items as $itemDTO) {
                $items[] = new PurchaseItem(
                    id: null,
                    purchaseId: $purchaseId,
                    productId: $itemDTO->productId,
                    quantity: $itemDTO->quantity,
                    unitPrice: $itemDTO->unitPrice,
                    totalPrice: $itemDTO->quantity * $itemDTO->unitPrice,
                    gstRate: $itemDTO->gstRate
                );
            }
            $this->repo->replacePurchaseItems($purchaseId, $items);

            $this->repo->commit();
            $this->invalidateVendorCaches();
        } catch (\Exception $e) {
            $this->repo->rollback();
            throw $e;
        }

        // 5. Reload with items
        return $this->repo->findPurchaseById($purchaseId, true);
    }

    public function getVendorHistory(string $vendorId, array $filters = []): array
    {
        return $this->repo->getVendorHistory($vendorId, $filters);
    }

    public function getAllVendorHistory(array $filters = []): array
    {
        return $this->repo->findAllVendorHistory($filters);
    }

    public function getAllVendors(): array
    {
        return $this->repo->findAllVendors();
    }

    public function getVendorPayments(string $vendorId, array $filters = []): array
    {
        return $this->repo->getVendorPayments($vendorId, $filters);
    }

    public function getAllPayments(array $filters = []): array
    {
        return $this->repo->findAllPayments($filters);
    }

    private function invalidateVendorCaches(): void
    {
        try {
            $valkey = ValkeyCache::getClient();
            foreach (['vendors:list:*', 'vendors:history:*', 'vendors:payments:*'] as $pattern) {
                $keys = $valkey->keys($pattern);
                if ($keys) {
                    $valkey->del($keys);
                }
            }
        } catch (\Exception $e) {
            error_log('Valkey vendor cache invalidation failed: ' . $e->getMessage());
        }
    }
}