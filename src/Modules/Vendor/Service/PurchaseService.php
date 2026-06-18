<?php
namespace Modules\Vendor\Service;

use Modules\Vendor\DTO\PurchaseDTO;
use Modules\Vendor\DTO\PurchaseItemDTO;
use Modules\Vendor\Model\Purchase;
use Modules\Vendor\Model\PurchaseItem;
use Modules\Vendor\Model\Vendor;
use Modules\Vendor\Repository\Contract\PurchaseRepositoryInterface;
use Modules\Auth\Validation\ValidationException;

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
        if ($dto->amountPaid > $dto->baseAmount) {
            throw new ValidationException("Amount paid cannot exceed base amount");
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

        // 5. Save purchase header
        $purchase = $this->repo->createPurchase($purchase);

        // 6. Create purchase items
        $items = [];
        foreach ($dto->items as $itemDTO) {
            $items[] = new PurchaseItem(
                id: null,
                purchaseId: $purchase->id,
                productId: $itemDTO->productId,
                quantity: $itemDTO->quantity,
                unitPrice: $itemDTO->unitPrice,
                totalPrice: $itemDTO->quantity * $itemDTO->unitPrice
            );
        }
        $this->repo->createPurchaseItems($items, $purchase->id);

        // 7. Load items back into purchase for response
        $purchase->items = $items;

        return $purchase;
    }

    /**
     * Record a payment against a purchase
     */
    public function recordPayment(string $purchaseId, float $amount): Purchase
    {
        if ($amount <= 0) {
            throw new ValidationException("Payment amount must be positive");
        }

        $purchase = $this->repo->findPurchaseById($purchaseId);
        if (!$purchase) {
            throw new ValidationException("Purchase not found");
        }

        if ($purchase->status === 'paid') {
            throw new ValidationException("Purchase is already fully paid");
        }

        $newPaid = $purchase->amountPaid + $amount;
        if ($newPaid > $purchase->baseAmount) {
            throw new ValidationException("Payment would exceed total amount");
        }

        $success = $this->repo->recordPayment($purchaseId, $amount);
        if (!$success) {
            throw new ValidationException("Failed to record payment");
        }

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
        $totalPages = ceil($result['total'] / $limit);
        $stats = $this->repo->getGlobalPurchaseStats();
        
        return [
            'data' => $result['data'],
            'stats' => $stats,
            'pagination' => [
                'current_page' => $page,
                'total_pages'  => max(1, (int)$totalPages),
                'limit'        => $limit,
                'total_records'=> $result['total'],
                'has_next'     => $page < $totalPages,
                'has_prev'     => $page > 1
            ]
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
}