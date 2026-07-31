<?php
declare(strict_types=1);

namespace Modules\Inventory\Repository\Contract;

/**
 * InventoryRepositoryInterface — data access contract for inventory batches.
 *
 * Repositories only execute SQL and return raw associative rows. They never
 * compute stock status, decide policies, emit notifications or write audit
 * logs — that is the service + event layer's responsibility.
 */
interface InventoryRepositoryInterface
{
    /**
     * @return array{data: array<int, array<string, mixed>>, total: int}
     */
    public function paginate(int $page, int $limit, string $search = '', string $categoryId = '', string $subcategoryId = ''): array;

    /**
     * @return array<string, mixed>|null
     */
    public function findById(string $id): ?array;

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(array $data): array;

    /**
     * @param array<string, mixed> $data
     */
    public function update(string $id, array $data): bool;

    /**
     * Pure additive stock adjustment (restock). No business logic.
     */
    public function restock(string $id, int $newQuantity): bool;

    /**
     * Detect duplicate batch numbers for the same product under the tenant.
     */
    public function batchNumberExists(string $productId, string $batchNumber, ?string $excludeId = null): bool;

    /**
     * @return array<string, mixed>
     */
    public function getStats(string $search = '', string $categoryId = '', string $subcategoryId = ''): array;
}
