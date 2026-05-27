<?php
namespace Modules\Product\Repository\Contract;

interface SubcategoryRepositoryInterface {
    public function findAll(): array;
    public function findByCategory(string $categoryId): array;
    public function findById(string $id): ?array;
    public function findByNameInCategory(string $categoryId, string $name): ?array;
    public function create(string $categoryId, string $name): array;
    public function update(string $id, string $name): array;
    public function delete(string $id): bool;
}