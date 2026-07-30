<?php

namespace Modules\Health\Service;

use Config\Database;
use Modules\Health\Model\HealthStatus;

class TelemetryService
{
    private const RETENTION_DAYS = 90;
    private const SAMPLE_INTERVAL = 10;

    public function recordSnapshot(array $metrics): int
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare('
            INSERT INTO public.telemetry_metrics (
                cpu_percent, ram_percent, ram_used_bytes, ram_total_bytes,
                swap_percent, swap_used_bytes, swap_total_bytes,
                disk_percent, disk_used_bytes, disk_total_bytes,
                disk_read_bytes, disk_write_bytes,
                db_latency_ms, db_total_connections, db_active_connections,
                db_idle_connections, db_cache_hit_ratio, db_size_bytes, db_slow_queries,
                valkey_latency_ms, valkey_hit_ratio,
                api_latency_ms,
                network_rx_bytes, network_tx_bytes, network_rx_packets, network_tx_packets,
                health_score, health_status,
                load_1m, load_5m, load_15m,
                uptime_seconds
            ) VALUES (
                :cpu_percent, :ram_percent, :ram_used_bytes, :ram_total_bytes,
                :swap_percent, :swap_used_bytes, :swap_total_bytes,
                :disk_percent, :disk_used_bytes, :disk_total_bytes,
                :disk_read_bytes, :disk_write_bytes,
                :db_latency_ms, :db_total_connections, :db_active_connections,
                :db_idle_connections, :db_cache_hit_ratio, :db_size_bytes, :db_slow_queries,
                :valkey_latency_ms, :valkey_hit_ratio,
                :api_latency_ms,
                :network_rx_bytes, :network_tx_bytes, :network_rx_packets, :network_tx_packets,
                :health_score, :health_status,
                :load_1m, :load_5m, :load_15m,
                :uptime_seconds
            )
        ');

        $params = [
            'cpu_percent' => $metrics['cpu_percent'] ?? null,
            'ram_percent' => $metrics['ram_percent'] ?? null,
            'ram_used_bytes' => $metrics['ram_used_bytes'] ?? null,
            'ram_total_bytes' => $metrics['ram_total_bytes'] ?? null,
            'swap_percent' => $metrics['swap_percent'] ?? null,
            'swap_used_bytes' => $metrics['swap_used_bytes'] ?? null,
            'swap_total_bytes' => $metrics['swap_total_bytes'] ?? null,
            'disk_percent' => $metrics['disk_percent'] ?? null,
            'disk_used_bytes' => $metrics['disk_used_bytes'] ?? null,
            'disk_total_bytes' => $metrics['disk_total_bytes'] ?? null,
            'disk_read_bytes' => $metrics['disk_read_bytes'] ?? null,
            'disk_write_bytes' => $metrics['disk_write_bytes'] ?? null,
            'db_latency_ms' => $metrics['db_latency_ms'] ?? null,
            'db_total_connections' => $metrics['db_total_connections'] ?? null,
            'db_active_connections' => $metrics['db_active_connections'] ?? null,
            'db_idle_connections' => $metrics['db_idle_connections'] ?? null,
            'db_cache_hit_ratio' => $metrics['db_cache_hit_ratio'] ?? null,
            'db_size_bytes' => $metrics['db_size_bytes'] ?? null,
            'db_slow_queries' => $metrics['db_slow_queries'] ?? null,
            'valkey_latency_ms' => $metrics['valkey_latency_ms'] ?? null,
            'valkey_hit_ratio' => $metrics['valkey_hit_ratio'] ?? null,
            'api_latency_ms' => $metrics['api_latency_ms'] ?? null,
            'network_rx_bytes' => $metrics['network_rx_bytes'] ?? null,
            'network_tx_bytes' => $metrics['network_tx_bytes'] ?? null,
            'network_rx_packets' => $metrics['network_rx_packets'] ?? null,
            'network_tx_packets' => $metrics['network_tx_packets'] ?? null,
            'health_score' => $metrics['health_score'] ?? null,
            'health_status' => $metrics['health_status'] ?? null,
            'load_1m' => $metrics['load_1m'] ?? null,
            'load_5m' => $metrics['load_5m'] ?? null,
            'load_15m' => $metrics['load_15m'] ?? null,
            'uptime_seconds' => $metrics['uptime_seconds'] ?? null,
        ];

        $stmt->execute($params);

        $this->purgeOldData($pdo);

        return (int) $pdo->lastInsertId();
    }

    public function getHistory(string $range = '1h', int $limit = 60): array
    {
        $pdo = Database::getConnection();
        $interval = $this->rangeToInterval($range);

        $stmt = $pdo->prepare("
            SELECT * FROM public.telemetry_metrics
            WHERE recorded_at >= NOW() - (:interval::interval)
            ORDER BY recorded_at ASC
            LIMIT :limit
        ");
        $stmt->bindValue(':interval', $interval, \PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getLatestSnapshot(): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query('
            SELECT * FROM public.telemetry_metrics
            ORDER BY recorded_at DESC
            LIMIT 1
        ');
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getAggregatedMetrics(string $range = '1h'): array
    {
        $pdo = Database::getConnection();
        $interval = $this->rangeToInterval($range);

        $stmt = $pdo->prepare("
            SELECT
                AVG(cpu_percent) as avg_cpu,
                MAX(cpu_percent) as max_cpu,
                AVG(ram_percent) as avg_ram,
                MAX(ram_percent) as max_ram,
                AVG(disk_percent) as avg_disk,
                MAX(disk_percent) as max_disk,
                AVG(db_latency_ms) as avg_db_latency,
                MAX(db_latency_ms) as max_db_latency,
                AVG(valkey_latency_ms) as avg_valkey_latency,
                MAX(valkey_latency_ms) as max_valkey_latency,
                AVG(health_score) as avg_health_score,
                MIN(health_score) as min_health_score,
                COUNT(*) as sample_count
            FROM public.telemetry_metrics
            WHERE recorded_at >= NOW() - (:interval::interval)
        ");
        $stmt->bindValue(':interval', $interval, \PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
    }

    public function getUptimeHistory(string $range = '24h'): array
    {
        $pdo = Database::getConnection();
        $interval = $this->rangeToInterval($range);

        $stmt = $pdo->prepare("
            SELECT health_status, health_score, recorded_at
            FROM public.telemetry_metrics
            WHERE recorded_at >= NOW() - (:interval::interval)
            ORDER BY recorded_at ASC
        ");
        $stmt->bindValue(':interval', $interval, \PDO::PARAM_STR);
        $stmt->execute();

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $total = count($rows);
        $healthy = count(array_filter($rows, fn($r) => $r['health_status'] === 'healthy'));

        return [
            'uptime_percent' => $total > 0 ? round(($healthy / $total) * 100, 2) : 0,
            'total_samples' => $total,
            'healthy_samples' => $healthy,
            'history' => $rows,
        ];
    }

    private function purgeOldData(\PDO $pdo): void
    {
        $stmt = $pdo->prepare("
            DELETE FROM public.telemetry_metrics
            WHERE recorded_at < NOW() - (:days || ' days')::interval
        ");
        $stmt->bindValue(':days', self::RETENTION_DAYS, \PDO::PARAM_INT);
        $stmt->execute();
    }

    private function rangeToInterval(string $range): string
    {
        return match ($range) {
            '5m' => '5 minutes',
            '15m' => '15 minutes',
            '1h' => '1 hour',
            '24h' => '24 hours',
            '7d' => '7 days',
            '30d' => '30 days',
            default => '1 hour',
        };
    }
}
