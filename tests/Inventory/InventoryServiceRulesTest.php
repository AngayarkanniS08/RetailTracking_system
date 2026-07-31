<?php
declare(strict_types=1);

/**
 * InventoryServiceRulesTest — pure business-rule tests for the Inventory module.
 *
 * Covers stock-status thresholds, inventory value, restock math, the restock
 * recommendation, overflow guard, search normalisation and the optimistic
 * concurrency check (via a fake repository — no database required).
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Modules\Auth\Validation\ValidationException;
use Modules\Inventory\DTO\InventoryUpdateDTO;
use Modules\Inventory\Events\InventoryEvent;
use Modules\Inventory\Events\InventoryEventDispatcher;
use Modules\Inventory\Events\InventoryEventListenerInterface;
use Modules\Inventory\Policy\InventoryPolicy;
use Modules\Inventory\Repository\Contract\InventoryRepositoryInterface;
use Modules\Inventory\Service\InventorySearchService;
use Modules\Inventory\Service\InventoryService;
use Modules\Inventory\Validator\InventoryValidator;

$pass = 0;
$fail = 0;

function assertSame($expected, $actual, string $label): void
{
    global $pass, $fail;
    if ($expected === $actual) {
        echo "  PASS: {$label}\n";
        $pass++;
    } else {
        echo "  FAIL: {$label} - expected " . json_encode($expected) . ", got " . json_encode($actual) . "\n";
        $fail++;
    }
}

function assertThrows(callable $fn, string $needle, string $label): void
{
    global $pass, $fail;
    try {
        $fn();
        echo "  FAIL: {$label} - expected ValidationException containing '{$needle}', none thrown\n";
        $fail++;
    } catch (ValidationException $e) {
        if (str_contains($e->getMessage(), $needle)) {
            echo "  PASS: {$label}\n";
            $pass++;
        } else {
            echo "  FAIL: {$label} - expected message containing '{$needle}', got '{$e->getMessage()}'\n";
            $fail++;
        }
    }
}

/** Minimal no-op listener so dispatch never touches DB/cache/notifications. */
class NoopInventoryListener implements InventoryEventListenerInterface
{
    public function handle(InventoryEvent $event): void {}
}

/** In-memory repository implementing the contract, DB-free. */
class FakeInventoryRepository implements InventoryRepositoryInterface
{
    private array $rows;

    public function __construct(array $rows = [])
    {
        $this->rows = $rows;
    }

    public function paginate(int $page, int $limit, string $search = '', string $categoryId = '', string $subcategoryId = ''): array
    {
        return ['data' => array_values($this->rows), 'total' => count($this->rows)];
    }

    public function findById(string $id): ?array
    {
        foreach ($this->rows as $row) {
            if ($row['id'] === $id) {
                return $row;
            }
        }
        return null;
    }

    public function create(array $data): array
    {
        $row = array_merge(['id' => uniqid('batch-', true), 'updated_at' => '2026-07-31 10:00:00', 'rop' => 0, 'emergency_stock' => 0], $data);
        $this->rows[] = $row;
        return $row;
    }

    public function update(string $id, array $data): bool
    {
        foreach ($this->rows as &$row) {
            if ($row['id'] === $id) {
                $row = array_merge($row, $data, ['updated_at' => '2026-07-31 11:00:00']);
                return true;
            }
        }
        return false;
    }

    public function restock(string $id, int $newQuantity): bool
    {
        foreach ($this->rows as &$row) {
            if ($row['id'] === $id) {
                $row['quantity'] = $newQuantity;
                $row['updated_at'] = '2026-07-31 11:00:00';
                return true;
            }
        }
        return false;
    }

    public function batchNumberExists(string $productId, string $batchNumber, ?string $excludeId = null): bool
    {
        foreach ($this->rows as $row) {
            if ($row['product_id'] === $productId && $row['batch_number'] === $batchNumber && $row['id'] !== $excludeId) {
                return true;
            }
        }
        return false;
    }

    public function getStats(string $search = '', string $categoryId = '', string $subcategoryId = ''): array
    {
        return ['total_batches' => count($this->rows), 'total_stock_value' => 0, 'low_stock_count' => 0, 'out_of_stock_count' => 0];
    }
}

function buildService(FakeInventoryRepository $repo): InventoryService
{
    return new InventoryService(
        repo: $repo,
        dispatcher: new InventoryEventDispatcher([new NoopInventoryListener()])
    );
}

$BATCH = 'd1111111-1111-1111-1111-111111111111';
$USER  = 'e165e33e-0b13-4db9-93bb-79858a78a74a';

// ── 1. Stock status thresholds ───────────────────────────────
echo "Test: calculateStockStatus\n";
$svc = buildService(new FakeInventoryRepository());
assertSame('OUT_OF_STOCK', $svc->calculateStockStatus(0, 10)['stock_status'], 'qty 0 -> OUT_OF_STOCK');
assertSame('critical', $svc->calculateStockStatus(0, 10)['severity'], 'qty 0 -> severity critical');
assertSame('LOW_STOCK', $svc->calculateStockStatus(5, 10, 0)['stock_status'], 'qty 5 <= rop 10 -> LOW_STOCK');
assertSame('LOW_STOCK', $svc->calculateStockStatus(8, 0, 0)['stock_status'], 'qty 8 with no rop -> default threshold LOW_STOCK');
assertSame('LOW_STOCK', $svc->calculateStockStatus(10, 0, 0)['stock_status'], 'qty 10 == default threshold -> LOW_STOCK');
assertSame('IN_STOCK', $svc->calculateStockStatus(11, 0, 0)['stock_status'], 'qty 11 > default threshold -> IN_STOCK');
assertSame('CRITICAL_STOCK', $svc->calculateStockStatus(3, 10, 5)['stock_status'], 'qty 3 <= emergency 5 -> CRITICAL_STOCK');
assertSame('LOW_STOCK', $svc->calculateStockStatus(7, 10, 5)['stock_status'], 'qty 7 above emergency but <= rop -> LOW_STOCK');

// ── 2. Inventory value ───────────────────────────────────────
echo "\nTest: calculateInventoryValue\n";
assertSame(2000.0, $svc->calculateInventoryValue(20, 100.0), '20 units x 100 = 2000');
assertSame(0.0, $svc->calculateInventoryValue(0, 100.0), '0 units -> 0');

// ── 3. Restock math ──────────────────────────────────────────
echo "\nTest: calculateNewQuantity\n";
assertSame(18, $svc->calculateNewQuantity(8, 10), '8 + 10 = 18');

// ── 4. Restock recommendation ────────────────────────────────
echo "\nTest: calculateRestockRecommendation\n";
$rec = $svc->calculateRestockRecommendation(8, 50);
assertSame(50, $rec['recommended_target'], 'recommended target = max capacity');
assertSame(50, $rec['maximum_capacity'], 'maximum capacity = 50');
assertSame(8, $rec['remaining'], 'remaining = current qty');
assertSame(42, $rec['deficit'], 'deficit = 50 - 8');
assertSame(42, $rec['recommended_order_quantity'], 'order qty = deficit');
$recFull = $svc->calculateRestockRecommendation(60, 50);
assertSame(0, $recFull['recommended_order_quantity'], 'over capacity -> no restock recommended');

// ── 5. Overflow guard ────────────────────────────────────────
echo "\nTest: InventoryPolicy::canRestock\n";
assertThrows(
    fn() => InventoryPolicy::canRestock(999999999, 2, 1000000000),
    'exceed',
    'overflow restock rejected'
);
try {
    InventoryPolicy::canRestock(5, 5, 1000000000);
    echo "  PASS: within-range restock allowed\n";
    $pass++;
} catch (ValidationException $e) {
    echo "  FAIL: within-range restock allowed\n";
    $fail++;
}

// ── 6. Search normalisation ──────────────────────────────────
echo "\nTest: InventorySearchService::normalizeSearch\n";
$searchSvc = new InventorySearchService();
assertSame('', $searchSvc->normalizeSearch('   '), 'blank search normalised to empty');
assertSame('metro textiles', $searchSvc->normalizeSearch('  metro   textiles  '), 'whitespace collapsed');
assertSame('100\\%', $searchSvc->normalizeSearch('100%'), 'percent wildcard escaped');
assertSame('a\\_b', $searchSvc->normalizeSearch('a_b'), 'underscore wildcard escaped');
assertSame('c\\\\d', $searchSvc->normalizeSearch('c\\d'), 'backslash escaped');
assertThrows(
    fn() => $searchSvc->normalizeSearch(str_repeat('x', 201)),
    'Search term too long',
    'oversized search rejected'
);

// ── 7. List query validation ─────────────────────────────────
echo "\nTest: InventoryValidator::validateListQuery\n";
$q = InventoryValidator::validateListQuery([]);
assertSame(1, $q['page'], 'default page 1');
assertSame(5, $q['limit'], 'default limit 5');
$clamped = InventoryValidator::validateListQuery(['page' => 0, 'limit' => 500]);
assertSame(1, $clamped['page'], 'page clamped to 1');
assertSame(100, $clamped['limit'], 'limit clamped to 100');
assertThrows(
    fn() => InventoryValidator::validateListQuery(['category_id' => 'not-a-uuid']),
    'category_id must be a valid UUID',
    'invalid category_id rejected'
);

// ── 8. Duplicate batch number guard ──────────────────────────
echo "\nTest: createBatch duplicate guard\n";
$repo = new FakeInventoryRepository([[
    'id' => $BATCH, 'product_id' => 'b1111111-1111-1111-1111-111111111111', 'batch_number' => 'ALERT-B1', 'quantity' => 20, 'updated_at' => '2026-07-31 10:00:00',
]]);
$svcDup = buildService($repo);
assertThrows(
    fn() => $svcDup->createBatch(
        InventoryValidator::validateCreate([
            'product_id' => 'b1111111-1111-1111-1111-111111111111', 'batch_number' => 'ALERT-B1', 'quantity' => 5,
            'cost_price' => 10, 'selling_price' => 15, 'retail_price' => 18,
        ]),
        $USER
    ),
    "A batch with number 'ALERT-B1' already exists",
    'duplicate batch number rejected'
);

// ── 9. Optimistic concurrency (update) ───────────────────────
echo "\nTest: updateBatch optimistic concurrency\n";
$repoCon = new FakeInventoryRepository([[
    'id' => $BATCH, 'product_id' => 'p1', 'batch_number' => 'ALERT-B1', 'vendor_name' => 'Metro',
    'quantity' => 20, 'cost_price' => 100, 'selling_price' => 150, 'retail_price' => 150,
    'rop' => 0, 'emergency_stock' => 0, 'created_at' => '2026-06-16 16:43:52', 'updated_at' => '2026-07-31 10:00:00',
]]);
$svcCon = buildService($repoCon);

$dtoFresh = InventoryValidator::validateUpdate($BATCH, [
    'batch_number' => 'ALERT-B1', 'vendor_name' => 'Metro', 'quantity' => 25,
    'cost_price' => 100, 'selling_price' => 150, 'retail_price' => 150,
    'expected_updated_at' => '2026-07-31 10:00:00',
]);
try {
    $updated = $svcCon->updateBatch($dtoFresh, $USER);
    echo "  PASS: matching expected_updated_at proceeds (new qty {$updated['quantity']})\n";
    $pass++;
} catch (ValidationException $e) {
    echo "  FAIL: matching expected_updated_at should proceed: {$e->getMessage()}\n";
    $fail++;
}

$dtoStale = InventoryValidator::validateUpdate($BATCH, [
    'batch_number' => 'ALERT-B1', 'vendor_name' => 'Metro', 'quantity' => 30,
    'cost_price' => 100, 'selling_price' => 150, 'retail_price' => 150,
    'expected_updated_at' => '2026-07-31 09:00:00',
]);
assertThrows(
    fn() => $svcCon->updateBatch($dtoStale, $USER),
    'This batch was modified by another user',
    'stale expected_updated_at rejected'
);

// ── 10. Restock workflow + response shape ────────────────────
echo "\nTest: restockBatch\n";
$repoR = new FakeInventoryRepository([[
    'id' => $BATCH, 'product_id' => 'p1', 'batch_number' => 'ALERT-B1', 'vendor_name' => 'Metro',
    'quantity' => 8, 'cost_price' => 100, 'selling_price' => 150, 'retail_price' => 150,
    'rop' => 0, 'emergency_stock' => 0, 'original_quantity' => 50, 'initial_qty' => 50,
    'created_at' => '2026-06-16 16:43:52', 'updated_at' => '2026-07-31 10:00:00',
]]);
$svcR = buildService($repoR);
$result = $svcR->restockBatch(
    InventoryValidator::validateRestock($BATCH, ['add_quantity' => 10]),
    $USER
);
assertSame(8, $result['previous_quantity'], 'previous qty captured');
assertSame(10, $result['added_quantity'], 'added qty echoed');
assertSame(18, $result['new_quantity'], 'new qty = 8 + 10');
assertSame('IN_STOCK', $result['stock_status'], '18 > default threshold 10 -> IN_STOCK');
assertSame(1800.0, $result['inventory_value'], 'value = 18 * 100');
assertSame(32, $result['restock_recommendation']['recommended_order_quantity'], 'deficit = 50 - 18 = 32');

echo "\n========================================\n";
echo "Results: {$pass} passed, {$fail} failed\n";
echo "========================================\n";

exit($fail > 0 ? 1 : 0);
