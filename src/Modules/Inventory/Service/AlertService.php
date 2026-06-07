<?php

namespace Modules\Inventory\Service;

use Modules\Inventory\Repository\Contract\AlertRepositoryInterface;
use Modules\Inventory\DTO\AlertDTO;
use Modules\Inventory\Model\LowStockAlert;
use Exception;

class AlertService
{
    private AlertRepositoryInterface $repo;

    public function __construct(AlertRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    public function getActiveAlerts(): array
    {
        return $this->repo->findAllActive();
    }

    public function saveAlert(AlertDTO $dto): array
    {
        // 1. Business validation
        if ($dto->dailySales < 0 || $dto->leadTime < 0 || $dto->emergencyStock < 0) {
            throw new Exception("Parameters must be non-negative integers.");
        }

        // 2. ROP formula: (Daily Sales × Lead Time) + Emergency Stock
        $newRop = ($dto->dailySales * $dto->leadTime) + $dto->emergencyStock;

        // 3. FIX: fetch the existing ROP to decide whether to reset alert_triggered.
        //    Resetting on every save caused spurious re-alerts when only cosmetic
        //    config fields changed without any actual ROP movement.
        $existing        = $this->repo->findByProductId($dto->productId);
        $ropChanged      = ($existing === null) || ((int)$existing['rop'] !== $newRop);
        $alertTriggered  = $ropChanged ? false : (bool)($existing['alert_triggered'] ?? false);

        // 4. Build model entity
        $model = new LowStockAlert(
            $dto->productId,
            $dto->dailySales,
            $dto->leadTime,
            $dto->emergencyStock,
            $newRop,
            $alertTriggered
        );

        // 5. Persist via repository (wrapped in a transaction inside the repo)
        return $this->repo->save($model);
    }

    // FIX: renamed from deleteAlert() to disableAlert() for semantic clarity
    public function disableAlert(string $productId): bool
    {
        return $this->repo->disable($productId);
    }
}
