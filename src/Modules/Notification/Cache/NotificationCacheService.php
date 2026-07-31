<?php

namespace Modules\Notification\Cache;

use Core\Cache\CacheInvalidationService;

/**
 * NotificationCacheService — Valkey-backed cache for notification payloads.
 *
 * Keys (per user, 60s TTL):
 *   notifications:user:{userId}      → full {summary, alerts} payload
 *   notifications:summary:{userId}   → summary sub-array (fast badge reads)
 *
 * Invalidation uses SCAN (never KEYS) via Core\Cache\CacheInvalidationService:
 *   invalidateUser($userId)  → on mark-read / read-all
 *   invalidateAll()          → on any domain mutation (inventory writes, ...)
 */
class NotificationCacheService
{
    public const TTL_SECONDS = 60;

    private const KEY_USER      = 'notifications:user:%s';
    private const KEY_SUMMARY   = 'notifications:summary:%s';
    private const PATTERN_ALL   = 'notifications:*';

    private CacheInvalidationService $invalidator;

    public function __construct(?CacheInvalidationService $invalidator = null)
    {
        $this->invalidator = $invalidator ?? new CacheInvalidationService();
    }

    /**
     * @return array{summary: array<string,int>, alerts: array<int,array<string,mixed>>}|null
     */
    public function getPayload(string $userId): ?array
    {
        return $this->readPayload($userId);
    }

    /**
     * @param array{summary: array<string,int>, alerts: array<int,array<string,mixed>>} $payload
     */
    public function setPayload(string $userId, array $payload): void
    {
        try {
            $client = \Core\Cache\ValkeyCache::getClient();
            $client->setex(sprintf(self::KEY_USER, $userId), self::TTL_SECONDS, json_encode($payload));
            $client->setex(sprintf(self::KEY_SUMMARY, $userId), self::TTL_SECONDS, json_encode($payload['summary'] ?? []));
        } catch (\Throwable $e) {
            error_log('NotificationCacheService::setPayload - ' . $e->getMessage());
        }
    }

    public function invalidateUser(string $userId): void
    {
        $this->invalidator->invalidatePattern("notifications:*:" . preg_quote($userId, '/'), [
            'domain' => 'notifications',
            'user'   => $userId,
        ]);
    }

    public function invalidateAll(): void
    {
        $this->invalidator->invalidatePattern(self::PATTERN_ALL, ['domain' => 'notifications']);
    }

    /** Inventory mutations (batch in/out, thresholds) invalidate the platform cache. */
    public function invalidateInventory(): void
    {
        $this->invalidateAll();
    }

    private function readPayload(string $userId): ?array
    {
        try {
            $client = \Core\Cache\ValkeyCache::getClient();
            $raw = $client->get(sprintf(self::KEY_USER, $userId));
            if ($raw === false || $raw === null || $raw === '') {
                return null;
            }
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable $e) {
            error_log('NotificationCacheService::getPayload - ' . $e->getMessage());
            return null;
        }
    }
}
