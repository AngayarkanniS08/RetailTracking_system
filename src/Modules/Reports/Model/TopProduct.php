<?php
namespace Modules\Reports\Model;

class TopProduct
{
    public function __construct(
        public string $productId,
        public string $name,
        public int    $qtySold,
        public float  $revenue,
        public float  $velocity
    ) {}
}
