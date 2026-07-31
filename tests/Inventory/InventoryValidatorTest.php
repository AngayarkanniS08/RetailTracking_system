<?php
declare(strict_types=1);

/**
 * InventoryValidatorTest — input-validation contract for the Inventory module.
 *
 * Every mutation payload is validated here before it reaches the service.
 * These tests pin the exact error contract the frontend depends on
 * (immutability, positivity, UUID shape, overflow caps).
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Modules\Auth\Validation\ValidationException;
use Modules\Inventory\Validator\InventoryValidator;

$pass = 0;
$fail = 0;

function assertTrue(bool $val, string $label): void
{
    global $pass, $fail;
    if ($val === true) {
        echo "  PASS: {$label}\n";
        $pass++;
    } else {
        echo "  FAIL: {$label} - expected true, got false\n";
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

const VALID_ID   = 'd1111111-1111-1111-1111-111111111111';
const VALID_PROD = 'b1111111-1111-1111-1111-111111111111';

// ── 1. UUID validation ───────────────────────────────────────
echo "Test: validateUuid\n";
assertTrue(InventoryValidator::validateUuid(VALID_ID) === VALID_ID, 'valid uuid passes');
assertThrows(fn() => InventoryValidator::validateUuid('abc'), 'must be a valid UUID', 'short uuid rejected');
assertThrows(fn() => InventoryValidator::validateUuid(''), 'must be a valid UUID', 'empty uuid rejected');

// ── 2. Create validation ─────────────────────────────────────
echo "\nTest: validateCreate\n";
assertThrows(fn() => InventoryValidator::validateCreate([]), 'Product is required', 'missing product_id rejected');
assertThrows(
    fn() => InventoryValidator::validateCreate(['product_id' => VALID_PROD]),
    'Batch ID / Number is required',
    'missing batch_number rejected'
);
assertThrows(
    fn() => InventoryValidator::validateCreate(['product_id' => VALID_PROD, 'batch_number' => 'B-1', 'quantity' => 0]),
    'Quantity must be greater than zero',
    'zero quantity rejected'
);
assertThrows(
    fn() => InventoryValidator::validateCreate(['product_id' => VALID_PROD, 'batch_number' => 'B-1', 'quantity' => -5]),
    'Quantity must be greater than zero',
    'negative quantity rejected'
);
assertThrows(
    fn() => InventoryValidator::validateCreate(['product_id' => VALID_PROD, 'batch_number' => 'B-1', 'quantity' => 5, 'cost_price' => -1]),
    'Cost price cannot be negative',
    'negative cost rejected'
);
assertThrows(
    fn() => InventoryValidator::validateCreate(['product_id' => VALID_PROD, 'batch_number' => 'B-1', 'quantity' => 5, 'created_at' => 'not-a-date']),
    'Invalid created_at date',
    'malformed date rejected'
);

$dto = InventoryValidator::validateCreate([
    'product_id' => VALID_PROD, 'batch_number' => '  B-1  ', 'vendor_name' => 'Metro',
    'quantity' => 25, 'cost_price' => 99.995, 'selling_price' => 150, 'retail_price' => 170,
    'created_at' => '2026-06-16',
]);
assertTrue($dto->productId === VALID_PROD, 'product_id mapped');
assertTrue($dto->batchNumber === 'B-1', 'batch_number trimmed');
assertTrue($dto->initialQty === 25, 'quantity mapped');
assertTrue($dto->costPrice === 100.0, 'cost rounded to 2dp');
assertTrue($dto->createdAt === '2026-06-16', 'date kept');

// ── 3. Update validation + immutability ──────────────────────
echo "\nTest: validateUpdate\n";
foreach (['id', 'user_id', 'product_id', 'product_name', 'unit'] as $field) {
    $payload = ['batch_number' => 'B-1', 'quantity' => 5];
    $payload[$field] = 'anything';
    assertThrows(
        fn() => InventoryValidator::validateUpdate(VALID_ID, $payload),
        "Field '{$field}' is immutable",
        "immutable field {$field} rejected"
    );
}
assertThrows(
    fn() => InventoryValidator::validateUpdate('bad-id', ['batch_number' => 'B-1', 'quantity' => 5]),
    'Batch ID must be a valid UUID',
    'bad batch id rejected'
);
assertThrows(
    fn() => InventoryValidator::validateUpdate(VALID_ID, ['batch_number' => 'B-1', 'quantity' => -1]),
    'Quantity cannot be negative',
    'negative update quantity rejected'
);
assertThrows(
    fn() => InventoryValidator::validateUpdate(VALID_ID, ['batch_number' => 'B-1', 'quantity' => 5, 'expected_updated_at' => 'garbage']),
    'Invalid expected_updated_at',
    'malformed expected_updated_at rejected'
);

$upd = InventoryValidator::validateUpdate(VALID_ID, [
    'batch_number' => 'B-1', 'vendor_name' => 'Metro', 'quantity' => 30,
    'cost_price' => 100, 'selling_price' => 150, 'retail_price' => 150,
    'expected_updated_at' => '2026-07-31 10:00:00',
]);
assertTrue($upd->batchId === VALID_ID, 'batch id mapped');
assertTrue($upd->quantity === 30, 'quantity mapped');
assertTrue($upd->expectedUpdatedAt === '2026-07-31 10:00:00', 'expected_updated_at kept');

// ── 4. Restock validation ────────────────────────────────────
echo "\nTest: validateRestock\n";
assertThrows(fn() => InventoryValidator::validateRestock(VALID_ID, []), 'Restock quantity is required', 'missing add_quantity rejected');
assertThrows(fn() => InventoryValidator::validateRestock(VALID_ID, ['add_quantity' => 0]), 'Restock quantity must be greater than zero', 'zero restock rejected');
assertThrows(fn() => InventoryValidator::validateRestock(VALID_ID, ['add_quantity' => 'abc']), 'Restock quantity must be a whole number', 'non-integer restock rejected');
assertThrows(
    fn() => InventoryValidator::validateRestock(VALID_ID, ['add_quantity' => 1000000001]),
    'exceeds the maximum allowed value',
    'overflow restock rejected'
);
$restock = InventoryValidator::validateRestock(VALID_ID, ['add_quantity' => 42, 'reason' => '  restock order  ']);
assertTrue($restock->addQuantity === 42, 'add_quantity mapped');
assertTrue($restock->reason === 'restock order', 'reason trimmed');

echo "\n========================================\n";
echo "Results: {$pass} passed, {$fail} failed\n";
echo "========================================\n";

exit($fail > 0 ? 1 : 0);
