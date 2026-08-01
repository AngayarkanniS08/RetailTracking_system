<?php
namespace Modules\Product\Service;

use Modules\Product\DTO\CategoryDTO;
use Modules\Product\Repository\Contract\CategoryRepositoryInterface;
use Modules\Auth\Validation\ValidationException;

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
     * @throws \Exception
     */
    public function deleteCategory(string $id, bool $force = false): array {
        $category = $this->repo->findById($id);
        if (!$category) {
            throw new ValidationException(json_encode([
                'code' => 'CATEGORY_NOT_FOUND',
                'message' => 'Category does not exist.'
            ]), 404);
        }

        $productCount = $this->repo->countProductsUsingCategory($id);

        if (!$force && $productCount > 0) {
            throw new ValidationException(json_encode([
                'code' => 'CATEGORY_HAS_PRODUCTS',
                'message' => 'Category contains existing products.',
                'productCount' => $productCount,
                'action' => 'Use force delete to remove the category and its products.'
            ]), 409);
        }

        $this->repo->beginTransaction();
        try {
            $deletedProductsCount = 0;
            if ($productCount > 0) {
                $deletedProductsCount = $this->repo->deleteProductsByCategory($id);
            }

            $deletedCategory = $this->repo->delete($id);
            $this->repo->commit();

            return [
                'success' => true,
                'message' => 'Category deleted successfully.',
                'deleted' => [
                    'category' => $deletedCategory ? 1 : 0,
                    'products' => $deletedProductsCount,
                    'inventory' => 0,
                    'variants' => 0,
                    'images' => 0
                ]
            ];
        } catch (\Exception $e) {
            $this->repo->rollback();
            throw $e;
        }
    }
}
