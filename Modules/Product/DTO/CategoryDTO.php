<?php
namespace Modules\Product\DTO;

class CategoryDTO {
    public function __construct(
        public readonly string $name
    ) {}
}
