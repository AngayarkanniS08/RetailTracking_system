<?php

namespace Modules\Health\Model;

class HealthStatus
{
    const HEALTHY = 'healthy';
    const DEGRADED = 'degraded';
    const UNHEALTHY = 'unhealthy';
    const CRITICAL = 'critical';
    const OFFLINE = 'offline';
    const MAINTENANCE = 'maintenance';
    const UNKNOWN = 'unknown';

    public static function all(): array
    {
        return [
            self::HEALTHY,
            self::DEGRADED,
            self::UNHEALTHY,
            self::CRITICAL,
            self::OFFLINE,
            self::MAINTENANCE,
            self::UNKNOWN,
        ];
    }

    public static function color(string $status): string
    {
        return match ($status) {
            self::HEALTHY => '#10b981',
            self::DEGRADED => '#f59e0b',
            self::UNHEALTHY => '#f97316',
            self::CRITICAL => '#ef4444',
            self::OFFLINE => '#6b7280',
            self::MAINTENANCE => '#3b82f6',
            self::UNKNOWN => '#94a3b8',
            default => '#94a3b8',
        };
    }

    public static function icon(string $status): string
    {
        return match ($status) {
            self::HEALTHY => '🟢',
            self::DEGRADED => '🟡',
            self::UNHEALTHY => '🟠',
            self::CRITICAL => '🔴',
            self::OFFLINE => '⚫',
            self::MAINTENANCE => '🔵',
            self::UNKNOWN => '⚪',
            default => '⚪',
        };
    }

    public static function label(string $status): string
    {
        return match ($status) {
            self::HEALTHY => 'Operational',
            self::DEGRADED => 'Degraded',
            self::UNHEALTHY => 'Unhealthy',
            self::CRITICAL => 'Critical',
            self::OFFLINE => 'Offline',
            self::MAINTENANCE => 'Maintenance',
            self::UNKNOWN => 'Unknown',
            default => 'Unknown',
        };
    }

    public static function severity(string $status): int
    {
        return match ($status) {
            self::HEALTHY => 0,
            self::DEGRADED => 1,
            self::UNHEALTHY => 2,
            self::CRITICAL => 3,
            self::OFFLINE => 4,
            self::MAINTENANCE => 5,
            self::UNKNOWN => 6,
            default => 6,
        };
    }

    public static function aggregate(array $components): string
    {
        $hasCritical = false;
        $hasUnhealthy = false;
        $hasDegraded = false;
        $hasUnknown = false;
        $hasOffline = false;

        foreach ($components as $name => $result) {
            $status = $result['status'] ?? self::UNKNOWN;
            if ($status === self::CRITICAL) {
                $hasCritical = true;
            } elseif ($status === self::UNHEALTHY) {
                if ($name === 'api' || $name === 'database') {
                    return self::CRITICAL;
                }
                $hasUnhealthy = true;
            } elseif ($status === self::DEGRADED) {
                $hasDegraded = true;
            } elseif ($status === self::OFFLINE) {
                $hasOffline = true;
            } elseif ($status === self::UNKNOWN) {
                $hasUnknown = true;
            }
        }

        if ($hasCritical || $hasOffline) return self::CRITICAL;
        if ($hasUnhealthy) return self::UNHEALTHY;
        if ($hasDegraded || $hasUnknown) return self::DEGRADED;
        return self::HEALTHY;
    }
}
