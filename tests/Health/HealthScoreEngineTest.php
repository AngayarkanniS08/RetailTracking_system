<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/Modules/Health/Model/HealthStatus.php';
require_once __DIR__ . '/../../src/Modules/Health/Model/HealthScoreEngine.php';

use Modules\Health\Model\HealthScoreEngine;
use Modules\Health\Model\HealthStatus;

$pass = 0;
$fail = 0;

function assertEq($expected, $actual, string $label): void {
    global $pass, $fail;
    if ($expected == $actual) {
        echo "  PASS: {$label}\n";
        $pass++;
    } else {
        echo "  FAIL: {$label} - expected " . json_encode((float)$expected) . ", got " . json_encode((float)$actual) . "\n";
        $fail++;
    }
}

function assertTrue(bool $val, string $label): void {
    global $pass, $fail;
    if ($val === true) {
        echo "  PASS: {$label}\n";
        $pass++;
    } else {
        echo "  FAIL: {$label} - expected true, got false\n";
        $fail++;
    }
}

function assertGreaterThan(float $expected, float $actual, string $label): void {
    global $pass, $fail;
    if ($actual > $expected) {
        echo "  PASS: {$label}\n";
        $pass++;
    } else {
        echo "  FAIL: {$label} - expected > {$expected}, got {$actual}\n";
        $fail++;
    }
}

// Test 1: Default weights sum to 100
echo "Test: Default weights sum to 100\n";
$engine = new HealthScoreEngine();
$weights = $engine->getWeights();
assertEq(100, array_sum($weights), 'Default weights sum to 100');

// Test 2: Healthy CPU returns 100 score component
echo "\nTest: CPU scoring\n";
$ref = new ReflectionMethod($engine, 'scoreCpu');

$score = $ref->invoke($engine, ['cpu_percent' => 30]);
assertEq(100, $score, 'CPU at 30% -> 100');

$score = $ref->invoke($engine, ['cpu_percent' => 70]);
assertEq(75, $score, 'CPU at 70% -> 75');

$score = $ref->invoke($engine, ['cpu_percent' => 85]);
assertEq(75, $score, 'CPU at 85% -> 75');

$score = $ref->invoke($engine, ['cpu_percent' => 95]);
assertEq(0, $score, 'CPU at 95% -> 0');

$score = $ref->invoke($engine, []);
assertEq(null, $score, 'No CPU data -> null');

// Test 3: Memory scoring
echo "\nTest: Memory scoring\n";
$refMem = new ReflectionMethod($engine, 'scoreMemory');

$score = $refMem->invoke($engine, ['ram_percent' => 40]);
assertEq(100, $score, 'RAM at 40% -> 100');

$score = $refMem->invoke($engine, ['ram_percent' => 85]);
assertEq(75, $score, 'RAM at 85% -> 75');

$score = $refMem->invoke($engine, ['ram_percent' => 95]);
assertEq(0, $score, 'RAM at 95% -> 0');

// Test 4: Database scoring
echo "\nTest: Database scoring\n";
$refDb = new ReflectionMethod($engine, 'scoreDatabase');

$score = $refDb->invoke($engine, ['db_latency_ms' => 5, 'db_slow_queries' => 0]);
assertEq(100, $score, 'DB latency 5ms, no slow queries -> 100');

$score = $refDb->invoke($engine, ['db_latency_ms' => 150, 'db_slow_queries' => 3]);
assertEq(90, $score, 'DB latency 150ms, 3 slow queries -> 90');

$score = $refDb->invoke($engine, ['db_latency_ms' => 600, 'db_slow_queries' => 8]);
assertEq(55, $score, 'DB latency 600ms, 8 slow queries -> 55');

$score = $refDb->invoke($engine, ['db_latency_ms' => 1500, 'db_slow_queries' => 15]);
assertEq(20, $score, 'DB latency 1500ms, 15 slow queries -> 20');

// Test 5: Overall health score calculation
echo "\nTest: Overall health score\n";
$metrics = [
    'cpu_percent' => 30,
    'ram_percent' => 40,
    'swap_percent' => 10,
    'disk_percent' => 50,
    'db_latency_ms' => 5,
    'db_slow_queries' => 0,
    'valkey_latency_ms' => 2,
    'api_latency_ms' => 10,
];

$result = $engine->calculate($metrics);
assertEq(100, $result['score'], 'All healthy metrics -> score 100');
assertEq('healthy', $result['status'], 'All healthy -> status healthy');

// Test 6: Degraded scenario
echo "\nTest: Degraded scenario\n";
$metrics2 = [
    'cpu_percent' => 85,
    'ram_percent' => 88,
    'swap_percent' => 50,
    'disk_percent' => 82,
    'db_latency_ms' => 250,
    'db_slow_queries' => 6,
    'valkey_latency_ms' => 60,
    'api_latency_ms' => 300,
];

$result2 = $engine->calculate($metrics2);
assertGreaterThan(0, $result2['score'], 'Degraded metrics -> score > 0');
assertTrue($result2['score'] < 100, 'Degraded metrics -> score < 100');
assertEq('degraded', $result2['status'], 'Degraded metrics -> status degraded');

// Test 7: Missing metrics return null components
echo "\nTest: Missing metrics\n";
$result3 = $engine->calculate([]);
assertEq(0, $result3['score'], 'No metrics -> score 0');
assertEq('critical', $result3['status'], 'No metrics -> status critical');

// Test 8: Custom weights
echo "\nTest: Custom weights\n";
$customWeights = ['cpu' => 50, 'memory' => 50];
$customEngine = new HealthScoreEngine($customWeights);
assertEq(2, count($customEngine->getWeights()), 'Custom weights count');
assertEq(50, $customEngine->getWeights()['cpu'], 'Custom CPU weight');

echo "\n========================================\n";
echo "Results: {$pass} passed, {$fail} failed\n";
echo "========================================\n";

exit($fail > 0 ? 1 : 0);
