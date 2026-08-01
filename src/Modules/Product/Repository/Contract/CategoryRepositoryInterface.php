<?php
namespace Modules\Product\Repository\Contract;

interface CategoryRepositoryInterface {
    public function findAll(): array;
    public function findById(string $id): ?array;
    public function findByName(string $name): ?array;
    public function create(string $name): array;
    public function update(string $id, string $name): array;
    public function countProductsUsingCategory(string $id): int;
    public function findDependentCounts(string $id): array;
    public function deleteProductImagesByCategory(string $id): int;
    public function deleteInventoryByCategory(string $id): int;
    public function deleteProductVariantsByCategory(string $id): int;
    public function deleteProductsByCategory(string $id): int;
    public function delete(string $id): bool;
    public function beginTransaction(): void;
    public function commit(): void;
    public function rollback(): void;
}
