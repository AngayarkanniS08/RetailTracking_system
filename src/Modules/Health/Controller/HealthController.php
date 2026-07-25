<?php

namespace Modules\Health\Controller;

use Modules\Health\Service\HealthService;
use Modules\Health\Model\HealthStatus;

class HealthController
{
    private HealthService $service;

    public function __construct()
    {
        $this->service = new HealthService();
    }

    public function live(): void
    {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'ok',
            'service' => 'api',
            'timestamp' => date('c'),
        ]);
    }

    public function ready(): void
    {
        header('Content-Type: application/json');

        $db = $this->service->checkDatabase();
        $valkey = $this->service->checkValkey();

        $checks = [
            'database' => $db,
            'valkey' => $valkey,
        ];

        $dbUnhealthy = ($db['status'] ?? '') === HealthStatus::UNHEALTHY;

        if ($dbUnhealthy) {
            http_response_code(503);
            echo json_encode([
                'status' => HealthStatus::UNHEALTHY,
                'checks' => $checks,
                'timestamp' => date('c'),
            ]);
            return;
        }

        $overall = HealthStatus::aggregate($checks);
        echo json_encode([
            'status' => $overall,
            'checks' => $checks,
            'timestamp' => date('c'),
        ]);
    }

    public function health(): void
    {
        header('Content-Type: application/json');

        $health = $this->service->getFullHealth();

        if ($health['status'] === HealthStatus::UNHEALTHY) {
            http_response_code(503);
        }

        echo json_encode($health, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function metrics(): void
    {
        header('Content-Type: text/plain; charset=utf-8');

        $health = $this->service->getFullHealth();
        $uptime = defined('APP_START_TIME') ? time() - APP_START_TIME : 0;

        $lines = [];
        $lines[] = '# HELP vortex_uptime_seconds Application uptime';
        $lines[] = '# TYPE vortex_uptime_seconds gauge';
        $lines[] = "vortex_uptime_seconds {$uptime}";
        $lines[] = '';

        $components = $health['components'];
        foreach (['api', 'database', 'valkey'] as $comp) {
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

        $disk = $components['disk'] ?? [];
        if (isset($disk['free_percent'])) {
            $lines[] = '# HELP vortex_disk_free_percent Disk free space percentage';
            $lines[] = '# TYPE vortex_disk_free_percent gauge';
            $lines[] = "vortex_disk_free_percent {$disk['free_percent']}";
            $lines[] = '';
        }

        $lines[] = '# HELP vortex_health_overall Overall system health (1=healthy, 0=degraded/unhealthy)';
        $lines[] = '# TYPE vortex_health_overall gauge';
        $lines[] = 'vortex_health_overall ' . ($health['status'] === 'healthy' ? 1 : 0);

        echo implode("\n", $lines) . "\n";
    }
}
