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
    
    public function createSubcategory(SubcategoryDTO $dto): array {
        if (empty($dto->categoryId) || empty($dto->name)) {
            throw new ValidationException("Category and subcategory name are required");
        }
        
        $existing = $this->repo->findByNameInCategory($dto->categoryId, $dto->name);
        if ($existing) {
            throw new ValidationException("Subcategory already exists under this category");
        }
        
        return $this->repo->create($dto->categoryId, $dto->name);
    }
    
    public function getSubcategoriesByCategory(string $categoryId): array {
        if (empty($categoryId)) {
            throw new ValidationException("Category ID is required");
        }
        return $this->repo->findByCategory($categoryId);
    }
}