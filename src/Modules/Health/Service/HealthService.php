<?php

namespace Modules\Health\Service;

use Config\Database;
use Core\Cache\ValkeyCache;
use Modules\Health\Model\HealthStatus;

class HealthService
{
    private const CACHE_TTL = 5;
    private const DISK_WARN_PERCENT = 20;
    private const DISK_CRITICAL_PERCENT = 10;
    private static array $_cache = [];
    private static array $_previousStatus = [];

    public function checkApi(): array
    {
        return ['status' => HealthStatus::HEALTHY];
    }

    public function checkDatabase(): array
    {
        $cached = $this->getCached('database');
        if ($cached !== null) return $cached;

        try {
            $start = microtime(true);
            $pdo = Database::getConnection();
            $pdo->query('SELECT 1');
            $latency = (microtime(true) - $start) * 1000;

            $result = [
                'status' => HealthStatus::HEALTHY,
                'latency_ms' => round($latency, 1),
            ];
        } catch (\Exception $e) {
            error_log('[HEALTH] database.unhealthy: ' . $e->getMessage());
            $result = ['status' => HealthStatus::UNHEALTHY];
        }

        $this->setCached('database', $result);
        $this->logStateChange('database', $result['status']);
        return $result;
    }

    public function checkValkey(): array
    {
        $cached = $this->getCached('valkey');
        if ($cached !== null) return $cached;

        try {
            $start = microtime(true);
            $redis = ValkeyCache::getClient();
            $redis->ping();
            $latency = (microtime(true) - $start) * 1000;

            $result = [
                'status' => HealthStatus::HEALTHY,
                'latency_ms' => round($latency, 1),
            ];
        } catch (\Exception $e) {
            error_log('[HEALTH] valkey.unhealthy: ' . $e->getMessage());
            $result = ['status' => HealthStatus::UNHEALTHY];
        }

        $this->setCached('valkey', $result);
        $this->logStateChange('valkey', $result['status']);
        return $result;
    }

    public function checkBackup(): array
    {
        $cached = $this->getCached('backup');
        if ($cached !== null) return $cached;

        try {
            $pdo = Database::getConnection();
            $userStmt = $pdo->query('SELECT id FROM users LIMIT 1');
            $userId = $userStmt->fetchColumn();

            if ($userId) {
                Database::setCurrentUser($userId);
                $stmt = $pdo->query('SELECT last_backup_status, last_backup_at FROM backup_config LIMIT 1');
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            } else {
                $row = false;
            }

            if (!$row) {
                $result = [
                    'status' => HealthStatus::DEGRADED,
                    'state' => 'never_run',
                ];
            } else {
                $status = $row['last_backup_status'];
                $stateMap = [
                    'completed' => HealthStatus::HEALTHY,
                    'failed' => HealthStatus::DEGRADED,
                    'never' => HealthStatus::DEGRADED,
                ];
                $result = [
                    'status' => $stateMap[$status] ?? HealthStatus::DEGRADED,
                    'state' => $status ?: 'never_run',
                ];
                if ($row['last_backup_at']) {
                    $result['last_successful_backup'] = $row['last_backup_at'];
                }
            }
        } catch (\Exception $e) {
            error_log('[HEALTH] backup.unknown: ' . $e->getMessage());
            $result = ['status' => HealthStatus::UNKNOWN, 'state' => 'unknown'];
        }

        $this->setCached('backup', $result);
        $this->logStateChange('backup', $result['status']);
        return $result;
    }

    public function checkDisk(): array
    {
        $cached = $this->getCached('disk');
        if ($cached !== null) return $cached;

        try {
            $path = __DIR__;
            $free = @disk_free_space($path);
            $total = @disk_total_space($path);

            if ($total === false || $total <= 0) {
                $result = ['status' => HealthStatus::UNKNOWN];
            } else {
                $free = (float)$free;
                $total = (float)$total;
                $used = $total - $free;
                $freePercent = ($free / $total) * 100;

                if ($freePercent < self::DISK_CRITICAL_PERCENT) {
                    $status = HealthStatus::UNHEALTHY;
                    error_log("[HEALTH] disk.critical: {$freePercent}% free");
                } elseif ($freePercent < self::DISK_WARN_PERCENT) {
                    $status = HealthStatus::DEGRADED;
                    error_log("[HEALTH] disk.warning: {$freePercent}% free");
                } else {
                    $status = HealthStatus::HEALTHY;
                }

                $result = [
                    'status' => $status,
                    'total_bytes' => (int)$total,
                    'used_bytes' => (int)$used,
                    'free_bytes' => (int)$free,
                    'free_percent' => (int)round($freePercent),
                ];
            }
        } catch (\Exception $e) {
            error_log('[HEALTH] disk.unknown: ' . $e->getMessage());
            $result = ['status' => HealthStatus::UNKNOWN];
        }

        $this->setCached('disk', $result);
        $this->logStateChange('disk', $result['status']);
        return $result;
    }

    public function getFullHealth(): array
    {
        $components = [
            'api' => $this->checkApi(),
            'database' => $this->checkDatabase(),
            'valkey' => $this->checkValkey(),
            'backup' => $this->checkBackup(),
            'disk' => $this->checkDisk(),
        ];

        return [
            'status' => HealthStatus::aggregate($components),
            'components' => $components,
            'timestamp' => date('c'),
        ];
    }

    private function getCached(string $key): ?array
    {
        if (isset(self::$_cache[$key]) && (time() - self::$_cache[$key]['time']) < self::CACHE_TTL) {
            return self::$_cache[$key]['data'];
        }
        return null;
    }

    private function setCached(string $key, array $data): void
    {
        self::$_cache[$key] = ['data' => $data, 'time' => time()];
    }

    private function logStateChange(string $component, string $status): void
    {
        $prev = self::$_previousStatus[$component] ?? null;
        if ($prev !== $status) {
            error_log("[HEALTH] {$component}.{$status}");
            self::$_previousStatus[$component] = $status;
        }
    }
}
