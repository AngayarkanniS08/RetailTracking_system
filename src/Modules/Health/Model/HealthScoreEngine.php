<?php

namespace Modules\Health\Model;

class HealthScoreEngine
{
    private array $weights;

    public function __construct(array $weights = [])
    {
        $this->weights = $weights ?: self::defaultWeights();
    }

    public static function defaultWeights(): array
    {
        return [
            'cpu' => 20,
            'memory' => 20,
            'disk' => 15,
            'database' => 20,
            'valkey' => 10,
            'api' => 10,
            'swap' => 5,
        ];
    }

    public function getWeights(): array
    {
        return $this->weights;
    }

    public function calculate(array $metrics): array
    {
        $scores = [];
        $totalWeight = 0;
        $weightedSum = 0;

        foreach ($this->weights as $component => $weight) {
            $componentScore = match ($component) {
                'cpu' => $this->scoreCpu($metrics),
                'memory' => $this->scoreMemory($metrics),
                'swap' => $this->scoreSwap($metrics),
                'disk' => $this->scoreDisk($metrics),
                'database' => $this->scoreDatabase($metrics),
                'valkey' => $this->scoreValkey($metrics),
                'api' => $this->scoreApi($metrics),
                default => null,
            };

            if ($componentScore === null) {
                continue;
            }

            $scores[$component] = $componentScore;
            $totalWeight += $weight;
            $weightedSum += $componentScore * $weight;
        }

        $finalScore = $totalWeight > 0 ? round($weightedSum / $totalWeight, 1) : 0;
        $status = $this->scoreToStatus($finalScore);
        $statusLevel = $this->scoreToStatusLevel($finalScore);

        return [
            'score' => $finalScore,
            'status' => $status,
            'level' => $statusLevel,
            'components' => $scores,
            'weights' => $this->weights,
        ];
    }

    private function scoreCpu(array $metrics): ?float
    {
        $cpu = $metrics['cpu_percent'] ?? null;
        if ($cpu === null) return null;
        if ($cpu >= 95) return 0;
        if ($cpu >= 80) return max(0, 100 - ($cpu - 80) * 5);
        if ($cpu >= 60) return max(50, 100 - ($cpu - 60) * 2.5);
        return 100;
    }

    private function scoreMemory(array $metrics): ?float
    {
        $ram = $metrics['ram_percent'] ?? null;
        if ($ram === null) return null;
        if ($ram >= 95) return 0;
        if ($ram >= 80) return max(0, 100 - ($ram - 80) * 5);
        if ($ram >= 60) return max(50, 100 - ($ram - 60) * 2.5);
        return 100;
    }

    private function scoreSwap(array $metrics): ?float
    {
        $swap = $metrics['swap_percent'] ?? null;
        if ($swap === null) return null;
        if ($swap >= 90) return 0;
        if ($swap >= 60) return max(30, 100 - ($swap - 60) * 2);
        return 100;
    }

    private function scoreDisk(array $metrics): ?float
    {
        $disk = $metrics['disk_percent'] ?? null;
        if ($disk === null) return null;
        $used = $disk;
        if ($used >= 95) return 0;
        if ($used >= 80) return max(20, 100 - ($used - 80) * 4);
        if ($used >= 60) return max(60, 100 - ($used - 60) * 2);
        return 100;
    }

    private function scoreDatabase(array $metrics): ?float
    {
        $latency = $metrics['db_latency_ms'] ?? null;
        $slowQueries = $metrics['db_slow_queries'] ?? 0;
        $connections = $metrics['db_active_connections'] ?? 0;

        if ($latency === null) return null;

        $score = 100;
        if ($latency > 1000) $score -= 50;
        elseif ($latency > 500) $score -= 30;
        elseif ($latency > 200) $score -= 15;
        elseif ($latency > 100) $score -= 5;

        if ($slowQueries > 10) $score -= 30;
        elseif ($slowQueries > 5) $score -= 15;
        elseif ($slowQueries > 0) $score -= 5;

        return max(0, $score);
    }

    private function scoreValkey(array $metrics): ?float
    {
        $latency = $metrics['valkey_latency_ms'] ?? null;
        if ($latency === null) return null;
        if ($latency > 500) return 0;
        if ($latency > 200) return 30;
        if ($latency > 100) return 60;
        if ($latency > 50) return 80;
        return 100;
    }

    private function scoreApi(array $metrics): ?float
    {
        $latency = $metrics['api_latency_ms'] ?? null;
        if ($latency === null) return null;
        if ($latency > 2000) return 0;
        if ($latency > 1000) return 30;
        if ($latency > 500) return 60;
        if ($latency > 200) return 80;
        return 100;
    }

    private function scoreToStatus(float $score): string
    {
        if ($score >= 90) return HealthStatus::HEALTHY;
        if ($score >= 70) return HealthStatus::DEGRADED;
        if ($score >= 40) return HealthStatus::UNHEALTHY;
        return HealthStatus::CRITICAL;
    }

    private function scoreToStatusLevel(float $score): string
    {
        if ($score >= 90) return 'operational';
        if ($score >= 70) return 'degraded';
        if ($score >= 40) return 'critical';
        return 'down';
    }
}
