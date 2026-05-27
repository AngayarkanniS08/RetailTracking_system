<?php
namespace Modules\Product\DTO;

class ProductDTO {
    public function __construct(
        public readonly string  $name,
        public readonly string  $categoryId,
        public readonly ?string $subcategoryId,
        public readonly string  $unit,
        public readonly ?string $hsnCode,
        public readonly float   $gstRate
    ) {}
}
