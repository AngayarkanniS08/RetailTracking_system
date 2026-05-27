<?php
namespace Modules\Product\Service;

use Modules\Product\DTO\SubcategoryDTO;
use Modules\Product\Repository\Contract\SubcategoryRepositoryInterface;
use Modules\Auth\validation\ValidationException;

class SubcategoryService {
    private SubcategoryRepositoryInterface $repo;

    public function __construct(SubcategoryRepositoryInterface $repo) {
        $this->repo = $repo;
    }

    /**
     * @throws ValidationException
     */
    public function getAllSubcategories(): array {
        return $this->repo->findAll();
    }

    /**
     * @throws ValidationException
     */
    public function getSubcategoriesByCategory(string $categoryId): array {
        if (empty($categoryId)) {
            throw new ValidationException("Category ID is required");
        }
        return $this->repo->findByCategory($categoryId);
    }

    /**
     * @throws ValidationException
     */
    public function createSubcategory(SubcategoryDTO $dto): array {
        if (empty(trim($dto->categoryId)) || empty(trim($dto->name))) {
            throw new ValidationException("Category and subcategory name are required");
        }

        if ($this->repo->findByNameInCategory($dto->categoryId, $dto->name)) {
            throw new ValidationException("Subcategory already exists under this category");
        }

        return $this->repo->create($dto->categoryId, trim($dto->name));
    }

    /**
     * @throws ValidationException
     */
    public function updateSubcategory(string $id, SubcategoryDTO $dto): array {
        if (empty(trim($dto->name))) {
            throw new ValidationException("Subcategory name is required");
        }

        if (!$this->repo->findById($id)) {
            throw new ValidationException("Subcategory not found");
        }

        $existing = $this->repo->findByNameInCategory($dto->categoryId, $dto->name);
        if ($existing && $existing['id'] !== $id) {
            throw new ValidationException("Subcategory name already exists in this category");
        }

        return $this->repo->update($id, trim($dto->name));
    }

    /**
     * @throws ValidationException
     */
    public function deleteSubcategory(string $id): bool {
        if (!$this->repo->findById($id)) {
            throw new ValidationException("Subcategory not found");
        }

        return $this->repo->delete($id);
    }
}