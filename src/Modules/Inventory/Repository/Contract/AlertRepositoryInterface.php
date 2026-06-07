<?php

namespace Modules\Inventory\Repository\Contract;

use Modules\Inventory\Model\LowStockAlert;

interface AlertRepositoryInterface
{
    public function findAllActive(): array;
    public function findByProductId(string $productId): ?array;  // FIX: added for ROP comparison
    public function save(LowStockAlert $model): array;
    public function disable(string $productId): bool;            // FIX: renamed from delete()
}
