<?php
namespace Modules\Reports\Model;

class TopProduct
{
    public function __construct(
        public string $name,
        public int    $qtySold,
        public float  $revenue
    ) {}
}
