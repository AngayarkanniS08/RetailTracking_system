<?php

namespace Modules\Health\Controller;

use Modules\Health\Service\HealthService;
use Modules\Health\Service\TelemetryService;
use Modules\Health\Model\HealthStatus;

class HealthController
{
    private HealthService $service;
    private TelemetryService $telemetry;

    public function __construct()
    {
        $this->service = new HealthService();
        $this->telemetry = new TelemetryService();
    }

    public function live(): void
    {
        $this->respondJson([
            'status' => 'ok',
            'service' => 'api',
            'timestamp' => date('c'),
        ]);
    }

    public function ready(): void
    {
        $db = $this->service->checkDatabase();
        $valkey = $this->service->checkValkey();

        $checks = [
            'database' => $db,
            'valkey' => $valkey,
        ];

        $dbUnhealthy = ($db['status'] ?? '') === HealthStatus::UNHEALTHY;

        if ($dbUnhealthy) {
            $this->respondJson([
                'status' => HealthStatus::UNHEALTHY,
                'checks' => $checks,
                'timestamp' => date('c'),
            ], 503);
            return;
        }

        $overall = HealthStatus::aggregate($checks);
        $this->respondJson([
            'status' => $overall,
            'checks' => $checks,
            'timestamp' => date('c'),
        ]);
    }

    public function health(): void
    {
        $health = $this->service->getFullHealth();
        $statusCode = ($health['status'] === HealthStatus::UNHEALTHY || $health['status'] === HealthStatus::CRITICAL) ? 503 : 200;
        $this->respondJson($health, $statusCode);
    }

    public function telemetry(): void
    {
        $health = $this->service->getFullTelemetry();

        try {
            $metrics = $health['metrics'] ?? [];
            $metrics['health_score'] = $health['score']['score'] ?? null;
            $metrics['health_status'] = $health['status'] ?? null;
            $this->telemetry->recordSnapshot($metrics);
        } catch (\Exception $e) {
            error_log('[TELEMETRY] record.failed: ' . $e->getMessage());
        }

        $this->respondJson($health);
    }

    public function telemetryHistory(): void
    {
        $range = $_GET['range'] ?? '1h';
        $limit = min((int)($_GET['limit'] ?? 60), 1000);

        $validRanges = ['5m', '15m', '1h', '24h', '7d', '30d'];
        if (!in_array($range, $validRanges, true)) {
            $range = '1h';
        }

        $history = $this->telemetry->getHistory($range, $limit);
        $aggregated = $this->telemetry->getAggregatedMetrics($range);
        $uptime = $this->telemetry->getUptimeHistory($range);
        $latest = $this->telemetry->getLatestSnapshot();

        $this->respondJson([
            'range' => $range,
            'history' => $history,
            'aggregated' => $aggregated,
            'uptime' => $uptime,
            'latest' => $latest,
            'sample_count' => count($history),
            'timestamp' => date('c'),
        ]);
    }

    public function metrics(): void
    {
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: text/plain; charset=utf-8');

        $health = $this->service->getFullHealth();
        $uptime = $health['metrics']['uptime_seconds'] ?? 0;

        $lines = [];
        $lines[] = '# HELP vortex_uptime_seconds Application uptime';
        $lines[] = '# TYPE vortex_uptime_seconds gauge';
        $lines[] = "vortex_uptime_seconds {$uptime}";
        $lines[] = '';

        $components = $health['components'];
        foreach (['api', 'database', 'valkey', 'cpu', 'memory'] as $comp) {
            $c = $components[$comp] ?? [];
            $val = ($c['status'] ?? '') === 'healthy' ? 1 : 0;
            $key = str_replace('-', '_', $comp);
            $lines[] = "# HELP vortex_{$key}_health {$comp} health status (1=healthy, 0=unhealthy)";
            $lines[] = "# TYPE vortex_{$key}_health gauge";
            $lines[] = "vortex_{$key}_health {$val}";
            $lines[] = '';
        }

        $backup = $components['backup'] ?? [];
        $backupState = $backup['state'] ?? 'unknown';
        $backupHealth = ($backup['status'] ?? '') === 'healthy' ? 1 : 0;
        $lines[] = '# HELP vortex_backup_health Backup health status (1=healthy, 0=degraded)';
        $lines[] = '# TYPE vortex_backup_health gauge';
        $lines[] = "vortex_backup_health {$backupHealth}";
        $lines[] = "# HELP vortex_backup_state Backup state";
        $lines[] = "# TYPE vortex_backup_state gauge";
        $lines[] = "vortex_backup_state{state=\"{$backupState}\"} 1";
        $lines[] = '';

        $metrics = $health['metrics'] ?? [];
        if (isset($metrics['cpu_percent'])) {
            $lines[] = '# HELP vortex_cpu_percent CPU utilization percent';
            $lines[] = '# TYPE vortex_cpu_percent gauge';
            $lines[] = "vortex_cpu_percent {$metrics['cpu_percent']}";
            $lines[] = '';
        }
        if (isset($metrics['ram_percent'])) {
            $lines[] = '# HELP vortex_ram_percent RAM utilization percent';
            $lines[] = '# TYPE vortex_ram_percent gauge';
            $lines[] = "vortex_ram_percent {$metrics['ram_percent']}";
            $lines[] = '';
        }
        if (isset($metrics['disk_free_percent'])) {
            $lines[] = '# HELP vortex_disk_free_percent Disk free space percentage';
            $lines[] = '# TYPE vortex_disk_free_percent gauge';
            $lines[] = "vortex_disk_free_percent {$metrics['disk_free_percent']}";
            $lines[] = '';
        }
        if (isset($metrics['health_score'])) {
            $lines[] = '# HELP vortex_health_score Overall health score (0-100)';
            $lines[] = '# TYPE vortex_health_score gauge';
            $lines[] = "vortex_health_score {$metrics['health_score']}";
            $lines[] = '';
        }

        $lines[] = '# HELP vortex_health_overall Overall system health (1=healthy, 0=degraded/unhealthy)';
        $lines[] = '# TYPE vortex_health_overall gauge';
        $lines[] = 'vortex_health_overall ' . ($health['status'] === 'healthy' ? 1 : 0);

        echo implode("\n", $lines) . "\n";
    }

    private function respondJson(array $data, int $statusCode = 200): void
    {
        while (ob_get_level()) ob_end_clean();
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
