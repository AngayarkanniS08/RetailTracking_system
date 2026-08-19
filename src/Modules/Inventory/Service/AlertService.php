<?php

namespace Modules\Inventory\Service;

use Modules\Inventory\Repository\Contract\AlertRepositoryInterface;
use Modules\Inventory\DTO\AlertDTO;
use Modules\Inventory\Model\LowStockAlert;
use Core\Cache\ValkeyCache;
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
        $saved = $this->repo->save($model);

        // 6. Invalidate caches so the very next inventory refresh sees the new ROP
        //    immediately (products:* holds rop, inventory:batches:* holds product_total_stock)
        $this->invalidateUserCaches();

        return $saved;
    }

    // FIX: renamed from deleteAlert() to disableAlert() for semantic clarity
    public function disableAlert(string $productId): bool
    {
        $disabled = $this->repo->disable($productId);

        // ROP is now zero; invalidate caches so rows/badges reflect it right away
        $this->invalidateUserCaches();

        return $disabled;
    }

    // Deletes ONLY this user's cached keys. Every cache key embeds a `:user:<id>`
    // suffix, so we pattern-match per user and never touch other users' caches.
    private function invalidateUserCaches(): void
    {
        try {
            $db = \Config\Database::getConnection();
            $userId = $db->query("SELECT current_setting('app.current_user_id', true)")->fetchColumn() ?: '';
            if ($userId === '') {
                return;
            }

            $valkey = ValkeyCache::getClient();
            $patterns = [
                'products:search:*:user:' . $userId,
                'inventory:batches:*:user:' . $userId,
                'pos:search:*:user:' . $userId,
            ];
            foreach ($patterns as $pattern) {
                $keys = $valkey->keys($pattern);
                if (!empty($keys)) {
                    $valkey->del($keys);
                }
            }
        } catch (\Exception $e) {
            error_log('Valkey cache invalidation error: ' . $e->getMessage());
        }
    }
}
