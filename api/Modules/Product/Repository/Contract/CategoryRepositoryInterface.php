<?php
namespace Modules\Product\Repository\Contract;

interface CategoryRepositoryInterface {
    public function findAll(): array;
    public function findById(string $id): ?array;
    public function findByName(string $name): ?array;
    public function create(string $name): array;
    public function update(string $id, string $name): array;
    public function delete(string $id): bool;
}
