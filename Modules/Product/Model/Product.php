<?php
namespace Modules\Product\Model;

class Product {
    public function __construct(
        public ?string $id,
        public string  $name,
        public string  $categoryId,
        public ?string $subcategoryId,
        public string  $unit,
        public ?string $hsnCode,
        public float   $gstRate,
        public ?string $createdAt
    ) {}
}
