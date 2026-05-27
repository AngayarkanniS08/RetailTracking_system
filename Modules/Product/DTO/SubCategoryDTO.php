<?php
namespace Modules\Product\DTO;

class SubcategoryDTO {
    public function __construct(
        public readonly string $categoryId,
        public readonly string $name
    ) {}
}