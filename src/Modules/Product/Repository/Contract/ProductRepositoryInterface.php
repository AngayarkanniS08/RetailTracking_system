<?php
namespace Modules\Product\Repository\Contract;

interface ProductRepositoryInterface {
    public function findPaginated(int $page, int $limit, string $search = '', string $categoryId = '', string $subcategoryId = ''): array;
    public function findAll(): array;
    public function findById(string $id): ?array;
    public function findByName(string $name): ?array;
    public function create(string $name, string $categoryId, ?string $subcategoryId, string $unit, ?string $hsnCode, float $gstRate): array;
    public function update(string $id, string $name, string $categoryId, ?string $subcategoryId, string $unit, ?string $hsnCode, float $gstRate): array;
    public function delete(string $id): bool;
}
