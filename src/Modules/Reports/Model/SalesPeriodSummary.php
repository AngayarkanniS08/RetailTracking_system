<?php
namespace Modules\Reports\Model;

class SalesPeriodSummary
{
    public function __construct(
        public float $revenue,
        public int   $bills,
        public float $avg
    ) {}
}
