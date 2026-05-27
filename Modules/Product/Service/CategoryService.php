<?php
namespace Modules\Product\Service;

use Modules\Product\DTO\CategoryDTO;
use Modules\Product\Repository\Contract\CategoryRepositoryInterface;
use Modules\Auth\validation\ValidationException;

class CategoryService {
    private CategoryRepositoryInterface $repo;

    public function __construct(CategoryRepositoryInterface $repo) {
        $this->repo = $repo;
    }

    /**
     * @throws ValidationException
     */
    public function getAllCategories(): array {
        return $this->repo->findAll();
    }

    /**
     * @throws ValidationException
     */
    public function createCategory(CategoryDTO $dto): array {
        if (empty(trim($dto->name))) {
            throw new ValidationException("Category name is required");
        }

        if ($this->repo->findByName($dto->name)) {
            throw new ValidationException("Category already exists");
        }

        return $this->repo->create(trim($dto->name));
    }

    /**
     * @throws ValidationException
     */
    public function updateCategory(string $id, CategoryDTO $dto): array {
        if (empty(trim($dto->name))) {
            throw new ValidationException("Category name is required");
        }

        if (!$this->repo->findById($id)) {
            throw new ValidationException("Category not found");
        }

        $existing = $this->repo->findByName($dto->name);
        if ($existing && $existing['id'] !== $id) {
            throw new ValidationException("Category name already exists");
        }

        return $this->repo->update($id, trim($dto->name));
    }

    /**
     * @throws ValidationException
     */
    public function deleteCategory(string $id): bool {
        if (!$this->repo->findById($id)) {
            throw new ValidationException("Category not found");
        }

        return $this->repo->delete($id);
    }
}
