<?php
namespace Modules\Reports\Model;

class OldStockItem
{
    public function __construct(
        public string $productId,
        public string $name,
        public string $batch,
        public int    $ageDays,
        public int    $qty,
        public int    $originalQty,
        public float  $remainingPct,
        public float  $velocity
    ) {}
}
