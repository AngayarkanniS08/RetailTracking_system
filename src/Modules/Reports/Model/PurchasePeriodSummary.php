<?php
namespace Modules\Reports\Model;

class PurchasePeriodSummary
{
    public function __construct(
        public float $amount,
        public int   $count,
        public float $paid
    ) {}
}
