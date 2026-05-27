<?php
namespace Modules\Product\Repository\Contract;

interface SubcategoryRepositoryInterface {
    public function findByCategory(string $categoryId): array;
    public function findByNameInCategory(string $categoryId, string $name): ?array;
    public function create(string $categoryId, string $name): array;
}