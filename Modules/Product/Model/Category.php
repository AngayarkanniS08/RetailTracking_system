<?php
namespace Modules\Product\Model;

class Category {
    public function __construct(
        public ?string $id,
        public string  $name,
        public ?string $createdAt
    ) {}
}
