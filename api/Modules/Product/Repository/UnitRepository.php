<?php
namespace Modules\Product\Repository;

use Modules\Product\Model\Unit;
use Modules\Product\Repository\Contract\UnitRepositoryInterface;

class UnitRepository implements UnitRepositoryInterface {
    /**
     * Returns static unit list as plain arrays to stay consistent
     * with the rest of the codebase (PDO::FETCH_ASSOC pattern).
     */
    public function findAll(): array {
        return array_map(
            fn(Unit $u) => ['value' => $u->value, 'label' => $u->label],
            Unit::all()
        );
    }
}
