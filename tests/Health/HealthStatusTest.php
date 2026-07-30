<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/Modules/Health/Model/HealthStatus.php';

use Modules\Health\Model\HealthStatus;

$pass = 0;
$fail = 0;

function assertEq($expected, $actual, string $label): void {
    global $pass, $fail;
    if ($expected === $actual) {
        echo "  PASS: {$label}\n";
        $pass++;
    } else {
        echo "  FAIL: {$label} - expected " . json_encode($expected) . ", got " . json_encode($actual) . "\n";
        $fail++;
    }
}

function assertContains(string $needle, array $haystack, string $label): void {
    global $pass, $fail;
    if (in_array($needle, $haystack, true)) {
        echo "  PASS: {$label}\n";
        $pass++;
    } else {
        echo "  FAIL: {$label} - '{$needle}' not found in list\n";
        $fail++;
    }
}

// Test 1: All status constants are defined
echo "Test: Status constants\n";
assertEq('healthy', HealthStatus::HEALTHY, 'HEALTHY constant');
assertEq('degraded', HealthStatus::DEGRADED, 'DEGRADED constant');
assertEq('unhealthy', HealthStatus::UNHEALTHY, 'UNHEALTHY constant');
assertEq('critical', HealthStatus::CRITICAL, 'CRITICAL constant');
assertEq('offline', HealthStatus::OFFLINE, 'OFFLINE constant');
assertEq('maintenance', HealthStatus::MAINTENANCE, 'MAINTENANCE constant');
assertEq('unknown', HealthStatus::UNKNOWN, 'UNKNOWN constant');

// Test 2: all() returns all statuses
echo "\nTest: all() method\n";
$all = HealthStatus::all();
assertEq(7, count($all), 'All statuses count is 7');
assertContains('healthy', $all, 'healthy in all()');
assertContains('critical', $all, 'critical in all()');
assertContains('unknown', $all, 'unknown in all()');

// Test 3: Color mapping
echo "\nTest: Color mapping\n";
assertEq('#10b981', HealthStatus::color('healthy'), 'healthy color');
assertEq('#ef4444', HealthStatus::color('critical'), 'critical color');
assertEq('#94a3b8', HealthStatus::color('unknown'), 'unknown color');
assertEq('#94a3b8', HealthStatus::color('nonexistent'), 'nonexistent defaults to unknown color');

// Test 4: Icon mapping
echo "\nTest: Icon mapping\n";
assertEq('🟢', HealthStatus::icon('healthy'), 'healthy icon');
assertEq('🔴', HealthStatus::icon('critical'), 'critical icon');
assertEq('⚪', HealthStatus::icon('unknown'), 'unknown icon');

// Test 5: Label mapping
echo "\nTest: Label mapping\n";
assertEq('Operational', HealthStatus::label('healthy'), 'healthy label');
assertEq('Critical', HealthStatus::label('critical'), 'critical label');
assertEq('Unknown', HealthStatus::label('nonexistent'), 'nonexistent defaults to Unknown');

// Test 6: Severity ordering
echo "\nTest: Severity ordering\n";
assertEq(0, HealthStatus::severity('healthy'), 'healthy severity 0');
assertEq(1, HealthStatus::severity('degraded'), 'degraded severity 1');
assertEq(4, HealthStatus::severity('offline'), 'offline severity 4');
assertEq(6, HealthStatus::severity('unknown'), 'unknown severity 6');

// Test 7: Aggregate - all healthy
echo "\nTest: Aggregate - all healthy\n";
$result = HealthStatus::aggregate([
    'api' => ['status' => 'healthy'],
    'database' => ['status' => 'healthy'],
    'valkey' => ['status' => 'healthy'],
]);
assertEq('healthy', $result, 'All healthy -> healthy');

// Test 8: Aggregate - critical takes priority
echo "\nTest: Aggregate - critical takes priority\n";
$result = HealthStatus::aggregate([
    'api' => ['status' => 'healthy'],
    'database' => ['status' => 'critical'],
    'valkey' => ['status' => 'degraded'],
]);
assertEq('critical', $result, 'Has critical -> critical');

// Test 9: Aggregate - unhealthy when api/database unhealthy
echo "\nTest: Aggregate - critical when api/database unhealthy\n";
$result = HealthStatus::aggregate([
    'api' => ['status' => 'unhealthy'],
    'database' => ['status' => 'healthy'],
]);
assertEq('critical', $result, 'API unhealthy -> critical');

// Test 10: Aggregate - degraded with unknown
echo "\nTest: Aggregate - degraded with unknown\n";
$result = HealthStatus::aggregate([
    'api' => ['status' => 'healthy'],
    'database' => ['status' => 'healthy'],
    'backup' => ['status' => 'unknown'],
]);
assertEq('degraded', $result, 'Has unknown -> degraded');

// Test 11: Aggregate - healthy with all healthy
echo "\nTest: Aggregate - healthy\n";
$result = HealthStatus::aggregate([
    'api' => ['status' => 'healthy'],
    'database' => ['status' => 'healthy'],
    'valkey' => ['status' => 'healthy'],
    'backup' => ['status' => 'healthy'],
]);
assertEq('healthy', $result, 'All healthy components -> healthy');

echo "\n========================================\n";
echo "Results: {$pass} passed, {$fail} failed\n";
echo "========================================\n";

exit($fail > 0 ? 1 : 0);
