<?php
namespace Modules\Product\DTO;

class UnitDTO {
    public function __construct(
        public readonly string $value,
        public readonly string $label
    ) {}
}
