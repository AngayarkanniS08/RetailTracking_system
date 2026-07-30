<?php

namespace Modules\Health\Service;

use Config\Database;
use Core\Cache\ValkeyCache;
use Modules\Health\Model\HealthStatus;

class HealthService
{
    private const CACHE_TTL = 5;
    private const DISK_WARN_PERCENT = 80;
    private const DISK_CRITICAL_PERCENT = 90;
    private static array $_cache = [];
    private static array $_previousStatus = [];
    private static ?array $_prevCpuTimes = null;
    private static ?float $_prevCpuTime = null;

    public function checkApi(): array
    {
        $start = microtime(true);
        $latency = (microtime(true) - $start) * 1000;

        return [
            'status' => HealthStatus::HEALTHY,
            'latency_ms' => round($latency, 2),
        ];
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
            Database::setCurrentUser('00000000-0000-4000-8000-000000000001');
            $stmt = $pdo->query('SELECT last_backup_status, last_backup_at FROM backup_config ORDER BY last_backup_at DESC NULLS LAST LIMIT 1');
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

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
                $usedPercent = ($used / $total) * 100;

                if ($usedPercent > self::DISK_CRITICAL_PERCENT) {
                    $status = HealthStatus::UNHEALTHY;
                    error_log("[HEALTH] disk.critical: {$usedPercent}% used");
                } elseif ($usedPercent > self::DISK_WARN_PERCENT) {
                    $status = HealthStatus::DEGRADED;
                    error_log("[HEALTH] disk.warning: {$usedPercent}% used");
                } else {
                    $status = HealthStatus::HEALTHY;
                }

                $result = [
                    'status' => $status,
                    'total_bytes' => (int)$total,
                    'used_bytes' => (int)$used,
                    'free_bytes' => (int)$free,
                    'free_percent' => (int)round(($free / $total) * 100),
                    'used_percent' => (int)round($usedPercent),
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

    public function checkCpu(): array
    {
        try {
            $load = sys_getloadavg();
            $numCpus = $this->getCpuCount();

            $cpuPercent = $numCpus > 0
                ? round(($load[0] / $numCpus) * 100, 1)
                : 0;
            $cpuPercent = min(100, max(0, $cpuPercent));

            $status = HealthStatus::HEALTHY;
            if ($cpuPercent >= 90) {
                $status = HealthStatus::UNHEALTHY;
            } elseif ($cpuPercent >= 70) {
                $status = HealthStatus::DEGRADED;
            }

            return [
                'status' => $status,
                'percent' => $cpuPercent,
                'load_1m' => round($load[0] ?? 0, 2),
                'load_5m' => round($load[1] ?? 0, 2),
                'load_15m' => round($load[2] ?? 0, 2),
                'num_cpus' => $numCpus,
            ];
        } catch (\Exception $e) {
            error_log('[HEALTH] cpu.unknown: ' . $e->getMessage());
            return ['status' => HealthStatus::UNKNOWN];
        }
    }

    private function getCpuCount(): int
    {
        if (PHP_OS_FAMILY === 'Darwin') {
            $output = [];
            exec('sysctl -n hw.ncpu 2>/dev/null', $output);
            return isset($output[0]) ? (int)$output[0] : 1;
        }
        $numCpus = @file_get_contents('/proc/cpuinfo');
        if ($numCpus === false) {
            $output = [];
            exec('nproc 2>/dev/null', $output);
            return isset($output[0]) ? (int)$output[0] : 1;
        }
        return substr_count($numCpus, 'processor') ?: 1;
    }

    private function checkCpuMac(): array
    {
        try {
            $output = [];
            $exitCode = 0;
            exec('ps -A -o %cpu 2>/dev/null', $output, $exitCode);
            if ($exitCode !== 0 || empty($output)) {
                return ['status' => HealthStatus::UNKNOWN];
            }
            $total = 0;
            $count = 0;
            foreach ($output as $line) {
                $val = trim($line);
                if (is_numeric($val)) {
                    $total += (float)$val;
                    $count++;
                }
            }
            $cpuPercent = $count > 0 ? round($total / $count, 1) : 0;
            $load = sys_getloadavg();

            $status = HealthStatus::HEALTHY;
            if ($cpuPercent >= 90) $status = HealthStatus::UNHEALTHY;
            elseif ($cpuPercent >= 70) $status = HealthStatus::DEGRADED;

            return [
                'status' => $status,
                'percent' => $cpuPercent,
                'load_1m' => round($load[0] ?? 0, 2),
                'load_5m' => round($load[1] ?? 0, 2),
                'load_15m' => round($load[2] ?? 0, 2),
            ];
        } catch (\Exception $e) {
            return ['status' => HealthStatus::UNKNOWN];
        }
    }

    public function checkMemory(): array
    {
        try {
            $meminfo = @file_get_contents('/proc/meminfo');
            if ($meminfo === false) {
                if (PHP_OS_FAMILY === 'Darwin') {
                    return $this->checkMemoryMac();
                }
                return ['status' => HealthStatus::UNKNOWN];
            }

            $data = [];
            foreach (explode("\n", $meminfo) as $line) {
                if (preg_match('/^(\w+):\s+(\d+)/', $line, $m)) {
                    $data[$m[1]] = (int)$m[2] * 1024;
                }
            }

            $total = $data['MemTotal'] ?? 0;
            $available = $data['MemAvailable'] ?? $data['MemFree'] ?? 0;
            $used = $total - $available;
            $percent = $total > 0 ? round(($used / $total) * 100, 1) : 0;

            $swapTotal = $data['SwapTotal'] ?? 0;
            $swapFree = $data['SwapFree'] ?? 0;
            $swapUsed = $swapTotal - $swapFree;
            $swapPercent = $swapTotal > 0 ? round(($swapUsed / $swapTotal) * 100, 1) : 0;

            $status = HealthStatus::HEALTHY;
            if ($percent >= 90) $status = HealthStatus::UNHEALTHY;
            elseif ($percent >= 80) $status = HealthStatus::DEGRADED;

            return [
                'status' => $status,
                'percent' => $percent,
                'used_bytes' => $used,
                'total_bytes' => $total,
                'available_bytes' => $available,
                'swap_percent' => $swapPercent,
                'swap_used_bytes' => $swapUsed,
                'swap_total_bytes' => $swapTotal,
            ];
        } catch (\Exception $e) {
            error_log('[HEALTH] memory.unknown: ' . $e->getMessage());
            return ['status' => HealthStatus::UNKNOWN];
        }
    }

    private function checkMemoryMac(): array
    {
        try {
            $output = [];
            exec('vm_stat 2>/dev/null', $output);
            if (empty($output)) return ['status' => HealthStatus::UNKNOWN];

            exec('sysctl hw.memsize 2>/dev/null', $memOutput);
            $total = !empty($memOutput) ? (int)trim(explode(':', $memOutput[0])[1] ?? 0) : 0;

            $pages = [];
            foreach ($output as $line) {
                if (preg_match('/^Pages\s+([^:]+):\s+(\d+)/', $line, $m)) {
                    $pages[strtolower($m[1])] = (int)$m[2];
                }
            }

            $pageSize = 16384;
            $active = ($pages['active'] ?? 0) * $pageSize;
            $wired = ($pages['wired down'] ?? 0) * $pageSize;
            $compressed = ($pages['occupied by compressor'] ?? 0) * $pageSize;
            $used = $active + $wired + $compressed;
            $percent = $total > 0 ? round(($used / $total) * 100, 1) : 0;

            $status = HealthStatus::HEALTHY;
            if ($percent >= 90) $status = HealthStatus::UNHEALTHY;
            elseif ($percent >= 80) $status = HealthStatus::DEGRADED;

            return [
                'status' => $status,
                'percent' => $percent,
                'used_bytes' => $used,
                'total_bytes' => $total,
                'available_bytes' => $total - $used,
            ];
        } catch (\Exception $e) {
            return ['status' => HealthStatus::UNKNOWN];
        }
    }

    public function checkDatabaseMetrics(): array
    {
        try {
            $pdo = Database::getConnection();

            $latencyStart = microtime(true);
            $pdo->query('SELECT 1');
            $latency = (microtime(true) - $latencyStart) * 1000;

            $connStmt = $pdo->query("SELECT count(*) as total FROM pg_stat_activity WHERE datname = current_database()");
            $totalConnections = (int)$connStmt->fetchColumn();

            $activeStmt = $pdo->query("SELECT count(*) as total FROM pg_stat_activity WHERE state = 'active' AND datname = current_database()");
            $activeConnections = (int)$activeStmt->fetchColumn();

            $idleStmt = $pdo->query("SELECT count(*) as total FROM pg_stat_activity WHERE state = 'idle' AND datname = current_database()");
            $idleConnections = (int)$idleStmt->fetchColumn();

            $slowStmt = $pdo->query("SELECT count(*) as total FROM pg_stat_activity WHERE state = 'active' AND now() - query_start > interval '1 second' AND datname = current_database()");
            $slowQueries = (int)$slowStmt->fetchColumn();

            $cacheStmt = $pdo->query("SELECT sum(blks_hit) as hits, sum(blks_read) as reads FROM pg_stat_database WHERE datname = current_database()");
            $cacheRow = $cacheStmt->fetch(\PDO::FETCH_ASSOC);
            $hits = (int)($cacheRow['hits'] ?? 0);
            $reads = (int)($cacheRow['reads'] ?? 0);
            $cacheHitRatio = ($hits + $reads) > 0 ? round(($hits / ($hits + $reads)) * 100, 2) : 0;

            $sizeStmt = $pdo->query("SELECT pg_database_size(current_database())");
            $dbSize = (int)$sizeStmt->fetchColumn();

            return [
                'latency_ms' => round($latency, 1),
                'total_connections' => $totalConnections,
                'active_connections' => $activeConnections,
                'idle_connections' => $idleConnections,
                'slow_queries' => $slowQueries,
                'cache_hit_ratio' => $cacheHitRatio,
                'size_bytes' => $dbSize,
            ];
        } catch (\Exception $e) {
            error_log('[HEALTH] db_metrics.unknown: ' . $e->getMessage());
            return [];
        }
    }

    public function checkNetwork(): array
    {
        try {
            $netDev = @file_get_contents('/proc/net/dev');
            if ($netDev === false) {
                if (PHP_OS_FAMILY === 'Darwin') {
                    return $this->checkNetworkMac();
                }
                return [];
            }

            $lines = explode("\n", $netDev);
            $totalRx = 0;
            $totalTx = 0;
            $totalRxPackets = 0;
            $totalTxPackets = 0;

            foreach ($lines as $line) {
                if (preg_match('/^\s*(\S+):\s*(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)/', $line, $m)) {
                    if ($m[1] === 'lo') continue;
                    $totalRx += isset($m[2]) ? (int)$m[2] : 0;
                    $totalTx += isset($m[10]) ? (int)$m[10] : 0;
                    $totalRxPackets += isset($m[3]) ? (int)$m[3] : 0;
                    $totalTxPackets += isset($m[11]) ? (int)$m[11] : 0;
                }
            }

            return [
                'rx_bytes' => $totalRx,
                'tx_bytes' => $totalTx,
                'rx_packets' => $totalRxPackets,
                'tx_packets' => $totalTxPackets,
            ];
        } catch (\Exception $e) {
            error_log('[HEALTH] network.unknown: ' . $e->getMessage());
            return [];
        }
    }

    private function checkNetworkMac(): array
    {
        try {
            $output = [];
            exec('netstat -ib 2>/dev/null', $output);
            $totalRx = 0;
            $totalTx = 0;

            foreach ($output as $line) {
                if (preg_match('/^\s*(en\d+|eth\d+)\s/', $line, $m)) {
                    $parts = preg_split('/\s+/', $line);
                    if (isset($parts[6])) $totalRx += (int)$parts[6];
                    if (isset($parts[9])) $totalTx += (int)$parts[9];
                }
            }

            return [
                'rx_bytes' => $totalRx,
                'tx_bytes' => $totalTx,
            ];
        } catch (\Exception $e) {
            return [];
        }
    }

    public function checkUptime(): array
    {
        try {
            $uptime = @file_get_contents('/proc/uptime');
            if ($uptime === false) {
                $uptimeSeconds = defined('APP_START_TIME') ? time() - APP_START_TIME : 0;
            } else {
                $uptimeSeconds = (int)explode(' ', $uptime)[0];
            }

            $days = floor($uptimeSeconds / 86400);
            $hours = floor(($uptimeSeconds % 86400) / 3600);
            $minutes = floor(($uptimeSeconds % 3600) / 60);

            return [
                'uptime_seconds' => $uptimeSeconds,
                'uptime_human' => "{$days}d {$hours}h {$minutes}m",
                'days' => (int)$days,
                'hours' => (int)$hours,
                'minutes' => (int)$minutes,
            ];
        } catch (\Exception $e) {
            return [
                'uptime_seconds' => 0,
                'uptime_human' => 'Unknown',
            ];
        }
    }

    public function getFullTelemetry(): array
    {
        $start = microtime(true);

        $disk = $this->checkDisk();
        $memory = $this->checkMemory();
        $cpu = $this->checkCpu();
        $dbMetrics = $this->checkDatabaseMetrics();
        $network = $this->checkNetwork();
        $uptime = $this->checkUptime();
        $db = $this->checkDatabase();
        $valkey = $this->checkValkey();
        $api = $this->checkApi();

        $apiLatency = (microtime(true) - $start) * 1000;

        $metrics = [
            'cpu_percent' => $cpu['percent'] ?? null,
            'cpu_load_1m' => $cpu['load_1m'] ?? null,
            'cpu_load_5m' => $cpu['load_5m'] ?? null,
            'cpu_load_15m' => $cpu['load_15m'] ?? null,
            'cpu_status' => $cpu['status'] ?? HealthStatus::UNKNOWN,
            'ram_percent' => $memory['percent'] ?? null,
            'ram_used_bytes' => $memory['used_bytes'] ?? null,
            'ram_total_bytes' => $memory['total_bytes'] ?? null,
            'ram_available_bytes' => $memory['available_bytes'] ?? null,
            'ram_status' => $memory['status'] ?? HealthStatus::UNKNOWN,
            'swap_percent' => $memory['swap_percent'] ?? null,
            'swap_used_bytes' => $memory['swap_used_bytes'] ?? null,
            'swap_total_bytes' => $memory['swap_total_bytes'] ?? null,
            'disk_used_percent' => $disk['used_percent'] ?? null,
            'disk_used_bytes' => $disk['used_bytes'] ?? null,
            'disk_total_bytes' => $disk['total_bytes'] ?? null,
            'disk_free_bytes' => $disk['free_bytes'] ?? null,
            'disk_free_percent' => $disk['free_percent'] ?? null,
            'disk_status' => $disk['status'] ?? HealthStatus::UNKNOWN,
            'db_latency_ms' => $dbMetrics['latency_ms'] ?? $db['latency_ms'] ?? null,
            'db_total_connections' => $dbMetrics['total_connections'] ?? null,
            'db_active_connections' => $dbMetrics['active_connections'] ?? null,
            'db_idle_connections' => $dbMetrics['idle_connections'] ?? null,
            'db_slow_queries' => $dbMetrics['slow_queries'] ?? null,
            'db_cache_hit_ratio' => $dbMetrics['cache_hit_ratio'] ?? null,
            'db_size_bytes' => $dbMetrics['size_bytes'] ?? null,
            'db_status' => $db['status'] ?? HealthStatus::UNKNOWN,
            'valkey_latency_ms' => $valkey['latency_ms'] ?? null,
            'valkey_status' => $valkey['status'] ?? HealthStatus::UNKNOWN,
            'api_latency_ms' => round($apiLatency, 2),
            'api_status' => $api['status'] ?? HealthStatus::UNKNOWN,
            'network_rx_bytes' => $network['rx_bytes'] ?? null,
            'network_tx_bytes' => $network['tx_bytes'] ?? null,
            'network_rx_packets' => $network['rx_packets'] ?? null,
            'network_tx_packets' => $network['tx_packets'] ?? null,
            'uptime_seconds' => $uptime['uptime_seconds'] ?? null,
            'uptime_human' => $uptime['uptime_human'] ?? 'Unknown',
        ];

        $backup = $this->checkBackup();
        $components = [
            'api' => $api,
            'database' => array_merge($db, $dbMetrics),
            'valkey' => $valkey,
            'backup' => $backup,
            'disk' => $disk,
            'cpu' => $cpu,
            'memory' => $memory,
        ];

        $engine = new \Modules\Health\Model\HealthScoreEngine();
        $healthScore = $engine->calculate($metrics);

        $metrics['health_score'] = $healthScore['score'];
        $metrics['health_status'] = $healthScore['status'];
        $metrics['health_level'] = $healthScore['level'];

        return [
            'status' => $healthScore['status'],
            'score' => $healthScore,
            'components' => $components,
            'metrics' => $metrics,
            'timestamp' => date('c'),
        ];
    }

    public function getFullHealth(): array
    {
        $telemetry = $this->getFullTelemetry();

        return [
            'status' => $telemetry['status'],
            'score' => $telemetry['score'],
            'components' => $telemetry['components'],
            'metrics' => $telemetry['metrics'],
            'timestamp' => $telemetry['timestamp'],
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
