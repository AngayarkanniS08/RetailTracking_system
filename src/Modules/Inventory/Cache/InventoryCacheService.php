<?php
declare(strict_types=1);

namespace Modules\Inventory\Cache;

use Core\Cache\CacheInvalidationService;

/**
 * InventoryCacheService — the single owner of inventory read-model caching.
 *
 * Responsibilities:
 *   - invalidate inventory list/search caches       (inventory:*)
 *   - invalidate POS search caches                  (pos:search:*)
 *   - invalidate notification platform caches       (notifications:*)
 *   - invalidate dashboard aggregates               (dashboard:*)
 *
 * No other layer in the Inventory module performs cache invalidation.
 */
final class InventoryCacheService
{
    private const PATTERNS = [
        'inventory:*',
        'pos:search:*',
        'notifications:*',
        'dashboard:*',
    ];

    private CacheInvalidationService $invalidator;

    public function __construct(?CacheInvalidationService $invalidator = null)
    {
        $this->invalidator = $invalidator ?? new CacheInvalidationService();
    }

    /**
     * Invalidate every read model that depends on inventory state.
     *
     * @param array<string, mixed> $context
     * @return array<int, \Core\Cache\CacheInvalidationResult>
     */
    public function invalidateAll(array $context = []): array
    {
        return $this->invalidator->invalidatePatterns(self::PATTERNS, $context + ['source' => 'InventoryCacheService']);
    }
}
