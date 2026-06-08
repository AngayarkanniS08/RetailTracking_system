<?php
namespace Modules\Inventory\Repository\Contract;

interface BatchRepositoryInterface
{
    public function findAll(): array;
    public function findById(string $id): ?array;
    public function create(array $data): array;
    public function updateall(string $id, array $data): bool;
    public function findPaginated(int $page, int $limit, string $search = '', string $categoryId = '', string $subcategoryId = ''): array;
    public function getStats(string $search = '', string $categoryId = '', string $subcategoryId = ''): array;
}
