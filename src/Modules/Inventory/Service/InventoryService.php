<?php
declare(strict_types=1);

namespace Modules\Inventory\Service;

use Modules\Inventory\Cache\InventoryCacheService;
use Modules\Inventory\DTO\InventoryCreateDTO;
use Modules\Inventory\DTO\InventoryUpdateDTO;
use Modules\Inventory\DTO\InventoryRestockDTO;
use Modules\Inventory\Events\InventoryCreatedEvent;
use Modules\Inventory\Events\InventoryDeletedEvent;
use Modules\Inventory\Events\InventoryEventDispatcher;
use Modules\Inventory\Events\InventoryRestockedEvent;
use Modules\Inventory\Events\InventoryUpdatedEvent;
use Modules\Inventory\Events\Listeners\AuditEventListener;
use Modules\Inventory\Events\Listeners\CacheEventListener;
use Modules\Inventory\Events\Listeners\NotificationEventListener;
use Modules\Inventory\Mapper\InventoryMapper;
use Modules\Inventory\Policy\InventoryPolicy;
use Modules\Inventory\Repository\Contract\InventoryRepositoryInterface;
use Modules\Inventory\Validator\InventoryValidator;
use Modules\Auth\Validation\ValidationException;

/**
 * InventoryService — the heart of the Inventory module.
 *
 * Owns every inventory workflow: list, details, create, edit, restock, stock
 * status, inventory value and restock recommendations. It composes the
 * repository (data), mapper (hydration), policy (ownership) and an event
 * dispatcher (audit + cache + notifications) — keeping controllers thin,
 * repositories dumb and business rules out of the frontend.
 */
final class InventoryService
{
    public const DEFAULT_LOW_STOCK_THRESHOLD = 10;
    public const MAX_QUANTITY = 1000000000;

    private const CACHE_TTL_SECONDS = 60;

    private InventoryRepositoryInterface $repo;
    private InventoryMapper $mapper;
    private InventoryEventDispatcher $dispatcher;
    private InventorySearchService $searchService;

    public function __construct(
        ?InventoryRepositoryInterface $repo = null,
        ?InventoryMapper $mapper = null,
        ?InventoryEventDispatcher $dispatcher = null,
        ?InventorySearchService $searchService = null,
    ) {
        $this->repo = $repo ?? new \Modules\Inventory\Repository\InventoryRepository();
        $this->mapper = $mapper ?? new InventoryMapper();
        $this->dispatcher = $dispatcher ?? new InventoryEventDispatcher([
            new AuditEventListener(),
            new CacheEventListener(),
            new NotificationEventListener(),
        ]);
        $this->searchService = $searchService ?? new InventorySearchService();
    }

    // ────────────────────────────────────────────────────────────
    // Read workflows
    // ────────────────────────────────────────────────────────────

    /**
     * Paginated inventory list with filters + summary.
     *
     * @return array{data: array<int, array<string, mixed>>, pagination: array<string, mixed>, summary: array<string, mixed>}
     */
    public function listInventory(
        int $page,
        int $limit,
        string $search,
        string $categoryId,
        string $subcategoryId,
        string $userId,
    ): array {
        $search = $this->searchService->normalizeSearch($search);

        $cacheKey = sprintf(
            'inventory:list:page:%d:limit:%d:search:%s:cat:%s:subcat:%s:user:%s',
            $page,
            $limit,
            md5($search),
            $categoryId ?: 'all',
            $subcategoryId ?: 'all',
            $userId
        );

        $cached = $this->readCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $result = $this->repo->paginate($page, $limit, $search, $categoryId, $subcategoryId);
        $stats  = $this->repo->getStats($search, $categoryId, $subcategoryId);

        $data = array_map(function (array $row): array {
            $status = $this->calculateStockStatus(
                (int)($row['quantity'] ?? 0),
                (int)($row['rop'] ?? 0),
                (int)($row['emergency_stock'] ?? 0)
            );
            return $this->mapper->toBatchDTO($row, $status)->toArray();
        }, $result['data']);

        $totalPages = (int)max(1, ceil($result['total'] / $limit));

        $payload = [
            'data'       => $data,
            'pagination' => [
                'current_page'  => $page,
                'total_pages'   => $totalPages,
                'limit'         => $limit,
                'total_records' => $result['total'],
                'has_next'      => $page < $totalPages,
                'has_prev'      => $page > 1,
            ],
            'summary'    => $this->mapper->toSummaryDTO($stats)->toArray(),
            'filters'    => [
                'search'         => $search,
                'category_id'    => $categoryId,
                'subcategory_id' => $subcategoryId,
            ],
        ];

        $this->writeCache($cacheKey, $payload);
        return $payload;
    }

    /**
     * Full details for a single batch incl. computed value + recommendation.
     *
     * @return array<string, mixed>
     */
    public function getDetails(string $id, string $userId): array
    {
        InventoryValidator::validateUuid($id, 'Batch ID');

        $cacheKey = 'inventory:details:' . $id . ':user:' . $userId;
        $cached = $this->readCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $row = InventoryPolicy::assertOwned($this->repo->findById($id), $userId);
        $details = $this->buildDetails($row);
        $this->writeCache($cacheKey, $details->toArray());
        return $details->toArray();
    }

    // ────────────────────────────────────────────────────────────
    // Mutation workflows
    // ────────────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    public function createBatch(InventoryCreateDTO $dto, string $userId): array
    {
        if ($this->repo->batchNumberExists($dto->productId, $dto->batchNumber)) {
            throw new ValidationException(
                "A batch with number '{$dto->batchNumber}' already exists for this product"
            );
        }

        $row = $this->repo->create([
            'product_id'   => $dto->productId,
            'batch_number' => $dto->batchNumber,
            'vendor_name'  => $dto->vendorName,
            'initial_qty'  => $dto->initialQty,
            'cost_price'   => $dto->costPrice,
            'selling_price'=> $dto->sellingPrice,
            'retail_price' => $dto->retailPrice,
            'created_at'   => $dto->createdAt,
        ]);

        $this->dispatcher->dispatch(new InventoryCreatedEvent($userId, [], $row));

        return $this->toBatchArray($row);
    }

    /**
     * @return array<string, mixed>
     */
    public function updateBatch(InventoryUpdateDTO $dto, string $userId): array
    {
        $current = InventoryPolicy::assertOwned($this->repo->findById($dto->batchId), $userId);

        if ($this->repo->batchNumberExists(
            (string)$current['product_id'],
            $dto->batchNumber,
            $dto->batchId
        )) {
            throw new ValidationException(
                "A batch with number '{$dto->batchNumber}' already exists for this product"
            );
        }

        if ($dto->expectedUpdatedAt !== null) {
            $storedUpdatedAt = (string)($current['updated_at'] ?? '');
            if ($this->normalizeTimestamp($storedUpdatedAt) !== $this->normalizeTimestamp($dto->expectedUpdatedAt)) {
                throw new ValidationException(
                    'This batch was modified by another user. Refresh and try again.'
                );
            }
        }

        $this->repo->update($dto->batchId, [
            'batch_number'  => $dto->batchNumber,
            'vendor_name'   => $dto->vendorName,
            'cost_price'    => $dto->costPrice,
            'selling_price' => $dto->sellingPrice,
            'retail_price'  => $dto->retailPrice,
            'quantity'      => $dto->quantity,
            'created_at'    => $dto->createdAt,
        ]);

        $after = InventoryPolicy::assertOwned($this->repo->findById($dto->batchId), $userId);
        $this->dispatcher->dispatch(new InventoryUpdatedEvent($userId, $current, $after));

        return $this->toBatchArray($after);
    }

    /**
     * Restock — additive stock adjustment. Total quantity is computed here,
     * never by the client.
     *
     * @return array<string, mixed>
     */
    public function restockBatch(InventoryRestockDTO $dto, string $userId): array
    {
        $current = InventoryPolicy::assertOwned($this->repo->findById($dto->batchId), $userId);

        $currentQty = (int)($current['quantity'] ?? 0);
        $newQuantity = $this->calculateNewQuantity($currentQty, $dto->addQuantity);

        // Overflow guard — never let a batch exceed INT range.
        InventoryPolicy::canRestock($currentQty, $dto->addQuantity, self::MAX_QUANTITY);

        $this->repo->restock($dto->batchId, $newQuantity);

        $after = InventoryPolicy::assertOwned($this->repo->findById($dto->batchId), $userId);
        $this->dispatcher->dispatch(new InventoryRestockedEvent($userId, $current, $after, $dto->reason));

        $status = $this->calculateStockStatus(
            (int)($after['quantity'] ?? 0),
            (int)($after['rop'] ?? 0),
            (int)($after['emergency_stock'] ?? 0)
        );

        return [
            'previous_quantity'   => $currentQty,
            'added_quantity'      => $dto->addQuantity,
            'new_quantity'        => $newQuantity,
            'stock_status'        => $status['stock_status'],
            'severity'            => $status['severity'],
            'status_text'         => $status['status_text'],
            'inventory_value'     => $this->calculateInventoryValue(
                $newQuantity,
                (float)($after['cost_price'] ?? 0)
            ),
            'restock_recommendation' => $this->buildRecommendation(
                $newQuantity,
                $this->maximumCapacity($after)
            ),
        ];
    }

    /**
     * Delete a batch (defensive path — currently not exposed via REST).
     */
    public function deleteBatch(string $id, string $userId): bool
    {
        InventoryValidator::validateUuid($id, 'Batch ID');
        $current = InventoryPolicy::assertOwned($this->repo->findById($id), $userId);

        $stmt = \Config\Database::getConnection()->prepare(
            "DELETE FROM public.inventory_batches
             WHERE id = ? AND user_id = current_setting('app.current_user_id', true)::uuid"
        );
        $deleted = $stmt->execute([$id]);

        $this->dispatcher->dispatch(new InventoryDeletedEvent($userId, $current, []));
        return $deleted;
    }

    // ────────────────────────────────────────────────────────────
    // Business rules (pure, unit-testable)
    // ────────────────────────────────────────────────────────────

    /**
     * @return array{stock_status:string, severity:string, status_text:string, status_badge_class:string}
     */
    public function calculateStockStatus(int $quantity, int $reorderLevel, int $emergencyStock = 0): array
    {
        if ($quantity <= 0) {
            return [
                'stock_status'       => 'OUT_OF_STOCK',
                'severity'           => 'critical',
                'status_text'        => 'Out of Stock',
                'status_badge_class' => 'bg-danger',
            ];
        }

        $threshold = $reorderLevel > 0 ? $reorderLevel : self::DEFAULT_LOW_STOCK_THRESHOLD;

        if ($emergencyStock > 0 && $quantity <= $emergencyStock) {
            return [
                'stock_status'       => 'CRITICAL_STOCK',
                'severity'           => 'critical',
                'status_text'        => 'Reorder Now',
                'status_badge_class' => 'bg-danger',
            ];
        }

        if ($quantity <= $threshold) {
            return [
                'stock_status'       => 'LOW_STOCK',
                'severity'           => 'warning',
                'status_text'        => 'Low Stock',
                'status_badge_class' => 'bg-warning text-dark',
            ];
        }

        return [
            'stock_status'       => 'IN_STOCK',
            'severity'           => 'info',
            'status_text'        => 'In Stock',
            'status_badge_class' => 'bg-success',
        ];
    }

    public function calculateInventoryValue(int $quantity, float $costPrice): float
    {
        return round($quantity * $costPrice, 2);
    }

    public function calculateNewQuantity(int $currentQuantity, int $addQuantity): int
    {
        return $currentQuantity + $addQuantity;
    }

    /**
     * @return array{recommended_target:int, maximum_capacity:int, remaining:int, deficit:int, recommended_order_quantity:int}
     */
    public function calculateRestockRecommendation(int $currentQuantity, int $maxCapacity): array
    {
        return $this->buildRecommendation($currentQuantity, $maxCapacity);
    }

    // ────────────────────────────────────────────────────────────
    // Internals
    // ────────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $row
     */
    private function buildDetails(array $row): \Modules\Inventory\DTO\InventoryDetailsDTO
    {
        $status = $this->calculateStockStatus(
            (int)($row['quantity'] ?? 0),
            (int)($row['rop'] ?? 0),
            (int)($row['emergency_stock'] ?? 0)
        );
        $batch = $this->mapper->toBatchDTO($row, $status);

        $value = $this->calculateInventoryValue($batch->quantity, $batch->costPrice);
        $recommendation = $this->buildRecommendation($batch->quantity, $this->maximumCapacity($row));

        return $this->mapper->toDetailsDTO($batch, $value, $recommendation);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function toBatchArray(array $row): array
    {
        $status = $this->calculateStockStatus(
            (int)($row['quantity'] ?? 0),
            (int)($row['rop'] ?? 0),
            (int)($row['emergency_stock'] ?? 0)
        );
        return $this->mapper->toBatchDTO($row, $status)->toArray();
    }

    /**
     * @param array<string, mixed> $row
     */
    private function maximumCapacity(array $row): int
    {
        $capacity = (int)($row['original_quantity'] ?? $row['initial_qty'] ?? 0);
        return max(0, $capacity);
    }

    /**
     * @return array{recommended_target:int, maximum_capacity:int, remaining:int, deficit:int, recommended_order_quantity:int}
     */
    private function buildRecommendation(int $currentQuantity, int $maxCapacity): array
    {
        $deficit = max(0, $maxCapacity - $currentQuantity);
        return [
            'recommended_target'          => $maxCapacity,
            'maximum_capacity'            => $maxCapacity,
            'remaining'                   => $currentQuantity,
            'deficit'                     => $deficit,
            'recommended_order_quantity'  => $deficit,
        ];
    }

    private function normalizeTimestamp(string $value): string
    {
        $ts = strtotime($value);
        return $ts === false ? $value : date('Y-m-d H:i:s', $ts);
    }

    private function readCache(string $key): ?array
    {
        try {
            $client = \Core\Cache\ValkeyCache::getClient();
            $raw = $client->get($key);
            if ($raw === false || $raw === null || $raw === '') {
                return null;
            }
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable $e) {
            error_log('InventoryService::readCache - ' . $e->getMessage());
            return null;
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function writeCache(string $key, array $payload): void
    {
        try {
            $client = \Core\Cache\ValkeyCache::getClient();
            $client->setex($key, self::CACHE_TTL_SECONDS, json_encode($payload));
        } catch (\Throwable $e) {
            error_log('InventoryService::writeCache - ' . $e->getMessage());
        }
    }
}
