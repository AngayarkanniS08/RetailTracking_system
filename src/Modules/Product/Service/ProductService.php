<?php
namespace Modules\Product\Service;

use Modules\Product\DTO\ProductDTO;
use Modules\Product\Repository\Contract\ProductRepositoryInterface;
use Modules\Auth\Validation\ValidationException;

use Modules\Product\Repository\Contract\CategoryRepositoryInterface;
use App\Common\Helpers\ArrayHelper;

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

    public function getProductsPaginated(int $page, int $limit, string $search = '', string $categoryId = ''): array
    {
        $result = $this->repo->findPaginated($page, $limit, $search, $categoryId);
        $meta = ArrayHelper::getPaginationMeta($page, $limit, $result['total']);
        return [
            'data'       => $result['data'],
            'pagination' => $meta
        ];
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

        return $this->repo->create(
            trim($dto->name),
            $dto->categoryId,
            $dto->subcategoryId ?: null,
            $dto->unit,
            $dto->hsnCode   ?: null,
            $dto->gstRate
        );
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

        return $this->repo->update(
            $id,
            trim($dto->name),
            $dto->categoryId,
            $dto->subcategoryId ?: null,
            $dto->unit,
            $dto->hsnCode   ?: null,
            $dto->gstRate
        );
    }

    /**
     * @throws ValidationException
     */
    public function deleteProduct(string $id): bool {
        if (!$this->repo->findById($id)) {
            throw new ValidationException("Product not found");
        }

        return $this->repo->delete($id);
    }
}
