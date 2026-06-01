<?php
namespace Modules\Product\Model;

class Subcategory {
    public function __construct(
        public ?string $id,
        public string  $categoryId,
        public string  $name,
        public ?string $createdAt
    ) {}
}
