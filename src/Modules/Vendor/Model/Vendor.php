<?php

namespace Modules\Vendor\Model;

class Vendor {
    public function __construct(
        public ?string $id,
        public string $name,
        public string $phone,
        public ?string $createdAt = null,
        public ?string $updatedAt = null
    ) {}
}