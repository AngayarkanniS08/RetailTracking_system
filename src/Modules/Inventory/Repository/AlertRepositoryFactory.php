<?php

namespace Modules\Inventory\Repository;

use Modules\Inventory\Repository\Contract\AlertRepositoryInterface;

class AlertRepositoryFactory
{
    public static function create(): AlertRepositoryInterface
    {
        return new AlertRepository();
    }
}
