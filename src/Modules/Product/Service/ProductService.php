<?php
namespace Modules\Product\Service;

use Modules\Product\DTO\ProductDTO;
use Modules\Product\Repository\Contract\ProductRepositoryInterface;
use Modules\Auth\Validation\ValidationException;

use Modules\Product\Repository\Contract\CategoryRepositoryInterface;
use App\Common\Helpers\ArrayHelper;
use Core\Cache\ValkeyCache;

class ProductService
{
    private ProductRepositoryInterface $repo;
    private CategoryRepositoryInterface $categoryRepo;

    public function __construct(
        ProductRepositoryInterface $repo,
        CategoryRepositoryInterface $categoryRepo
    ) {
        $this->repo = $repo;
        $this->categoryRepo = $categoryRepo;
    }

    public function getProductsPaginated(int $page, int $limit, string $search = '', string $categoryId = '', string $subcategoryId = ''): array
    {
        $result = $this->repo->findPaginated($page, $limit, $search, $categoryId, $subcategoryId);
        $meta = ArrayHelper::getPaginationMeta($page, $limit, $result['total']);
        return [
            'data'       => $result['data'],
            'pagination' => $meta
        ];
    }

    private function invalidateProductSearchCache(): void {
    try {
        $valkey = ValkeyCache::getClient();
        // Scan for keys (use a pattern; be careful with large key sets)
        $keys = $valkey->keys('products:search:*');
        if ($keys) {
            $valkey->del($keys);
        }
    } catch (\Exception $e) {
        error_log('Cache invalidation failed: ' . $e->getMessage());
    }
}


    /**
     * @throws ValidationException
     */
    public function getAllProducts(): array {
        return $this->repo->findAll();
    }

    /**
     * @throws ValidationException
     */
    public function createProduct(ProductDTO $dto): array {
        if (empty(trim($dto->name))) {
            throw new ValidationException("Product name is required");
        }

        if (empty($dto->categoryId)) {
            throw new ValidationException("Category is required");
        }

        if (empty($dto->unit)) {
            throw new ValidationException("Unit is required");
        }

        if (!$this->categoryRepo->findById($dto->categoryId)) {
            throw new ValidationException("Category not found");
        }

        if ($this->repo->findByName($dto->name)) {
            throw new ValidationException("A product with this name already exists");
        }

        if ($dto->gstRate < 0 || $dto->gstRate > 100) {
            throw new ValidationException("GST rate must be between 0% and 100%");
        }

        if ($dto->hsnCode !== null && !preg_match('/^\d{4}(\d{2})?(\d{2})?$/', $dto->hsnCode)) {
            throw new ValidationException("HSN code must be 4, 6 or 8 digits");
        }

        $product = $this->repo->create(
            trim($dto->name),
            $dto->categoryId,
            $dto->subcategoryId ?: null,
            $dto->unit,
            $dto->hsnCode   ?: null,
            $dto->gstRate
        );
        $this->invalidateProductSearchCache();
        return $product;
    }

    /**
     * @throws ValidationException
     */
    public function updateProduct(string $id, ProductDTO $dto): array {
        if (empty(trim($dto->name))) {
            throw new ValidationException("Product name is required");
        }

        if (empty($dto->categoryId)) {
            throw new ValidationException("Category is required");
        }

        if (empty($dto->unit)) {
            throw new ValidationException("Unit is required");
        }

        if (!$this->categoryRepo->findById($dto->categoryId)) {
            throw new ValidationException("Category not found");
        }

        if (!$this->repo->findById($id)) {
            throw new ValidationException("Product not found");
        }

        $existing = $this->repo->findByName($dto->name);
        if ($existing && $existing['id'] !== $id) {
            throw new ValidationException("A product with this name already exists");
        }

        if ($dto->gstRate < 0 || $dto->gstRate > 100) {
            throw new ValidationException("GST rate must be between 0% and 100%");
        }

        if ($dto->hsnCode !== null && !preg_match('/^\d{4}(\d{2})?(\d{2})?$/', $dto->hsnCode)) {
            throw new ValidationException("HSN code must be 4, 6 or 8 digits");
        }

        $product = $this->repo->update(
            $id,
            trim($dto->name),
            $dto->categoryId,
            $dto->subcategoryId ?: null,
            $dto->unit,
            $dto->hsnCode   ?: null,
            $dto->gstRate
        );
        $this->invalidateProductSearchCache();
        return $product;
    }

    /**
     * @throws ValidationException
     */
    public function deleteProduct(string $id): bool {
        if (!$this->repo->findById($id)) {
            throw new ValidationException("Product not found");
        }

        $deleted = $this->repo->delete($id);
        if ($deleted) {
            $this->invalidateProductSearchCache();
        }
        return $deleted;
    }
}
