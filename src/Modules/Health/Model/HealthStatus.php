<?php

namespace Modules\Health\Model;

class HealthStatus
{
    const HEALTHY = 'healthy';
    const DEGRADED = 'degraded';
    const UNHEALTHY = 'unhealthy';
    const UNKNOWN = 'unknown';

    public static function aggregate(array $components): string
    {
        $hasUnhealthy = false;
        $hasDegraded = false;
        $hasUnknown = false;

        foreach ($components as $name => $result) {
            $status = $result['status'] ?? self::UNKNOWN;
            if ($status === self::UNHEALTHY) {
                if ($name === 'api' || $name === 'database') {
                    return self::UNHEALTHY;
                }
                $hasUnhealthy = true;
            } elseif ($status === self::DEGRADED) {
                $hasDegraded = true;
            } elseif ($status === self::UNKNOWN) {
                $hasUnknown = true;
            }
        }

        if ($hasUnhealthy) {
            return self::UNHEALTHY;
        }
        if ($hasDegraded || $hasUnknown) {
            return self::DEGRADED;
        }
        return self::HEALTHY;
    }
}
