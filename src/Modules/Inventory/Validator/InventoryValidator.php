<?php
declare(strict_types=1);

namespace Modules\Inventory\Validator;

use Modules\Auth\Validation\ValidationException;
use Modules\Inventory\DTO\InventoryCreateDTO;
use Modules\Inventory\DTO\InventoryUpdateDTO;
use Modules\Inventory\DTO\InventoryRestockDTO;

/**
 * InventoryValidator — all inventory input validation lives here.
 *
 * The controller calls these before touching the service; the service calls
 * them again as defense-in-depth for every mutation. No validation rules are
 * duplicated in the frontend or the repository.
 */
final class InventoryValidator
{
    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    private const MAX_QUANTITY = 1000000000; // 1e9 — overflow guard for INT columns

    private const IMMUTABLE_ON_UPDATE = ['id', 'user_id', 'product_id', 'product_name', 'unit'];

    /**
     * @throws ValidationException
     */
    public static function validateUuid(string $id, string $label = 'ID'): string
    {
        $id = trim($id);
        if (!preg_match(self::UUID_PATTERN, $id)) {
            throw new ValidationException("{$label} must be a valid UUID");
        }
        return $id;
    }

    /**
     * @param array<string, mixed> $input
     * @throws ValidationException
     */
    public static function validateCreate(array $input): InventoryCreateDTO
    {
        $productId = self::requiredString($input, 'product_id', 'Product');
        self::validateUuid($productId, 'Product ID');

        $batchNumber = self::requiredString($input, 'batch_number', 'Batch ID / Number');
        $batchNumber = mb_substr($batchNumber, 0, 100);

        $vendorName = trim((string)($input['vendor_name'] ?? ''));
        $vendorName = mb_substr($vendorName, 0, 100);

        $initialQty = self::positiveInt($input, 'quantity', 'Quantity');
        $costPrice  = self::nonNegativeFloat($input, 'cost_price', 'Cost price');
        $sellingPrice = self::nonNegativeFloat($input, 'selling_price', 'Selling price');
        $retailPrice  = self::nonNegativeFloat($input, 'retail_price', 'Retail price');

        $createdAt = trim((string)($input['created_at'] ?? ''));
        if ($createdAt !== '' && !self::isValidDate($createdAt)) {
            throw new ValidationException('Invalid created_at date');
        }

        return new InventoryCreateDTO(
            productId: $productId,
            batchNumber: $batchNumber,
            vendorName: $vendorName,
            initialQty: $initialQty,
            costPrice: $costPrice,
            sellingPrice: $sellingPrice,
            retailPrice: $retailPrice,
            createdAt: $createdAt !== '' ? $createdAt : null,
        );
    }

    /**
     * @param array<string, mixed> $input
     * @throws ValidationException
     */
    public static function validateUpdate(string $batchId, array $input): InventoryUpdateDTO
    {
        self::validateUuid($batchId, 'Batch ID');

        foreach (self::IMMUTABLE_ON_UPDATE as $field) {
            if (array_key_exists($field, $input)) {
                throw new ValidationException("Field '{$field}' is immutable and cannot be updated");
            }
        }

        $batchNumber = self::requiredString($input, 'batch_number', 'Batch ID / Number');
        $batchNumber = mb_substr($batchNumber, 0, 100);

        $vendorName = trim((string)($input['vendor_name'] ?? ''));

        $quantity  = self::nonNegativeInt($input, 'quantity', 'Quantity');
        $costPrice = self::nonNegativeFloat($input, 'cost_price', 'Cost price');
        $sellingPrice = self::nonNegativeFloat($input, 'selling_price', 'Selling price');
        $retailPrice  = self::nonNegativeFloat($input, 'retail_price', 'Retail price');

        $createdAt = trim((string)($input['created_at'] ?? ''));
        if ($createdAt !== '' && !self::isValidDate($createdAt)) {
            throw new ValidationException('Invalid created_at date');
        }

        $expectedUpdatedAt = trim((string)($input['expected_updated_at'] ?? ''));
        if ($expectedUpdatedAt !== '' && strtotime($expectedUpdatedAt) === false) {
            throw new ValidationException('Invalid expected_updated_at value');
        }

        return new InventoryUpdateDTO(
            batchId: $batchId,
            batchNumber: $batchNumber,
            vendorName: $vendorName,
            quantity: $quantity,
            costPrice: $costPrice,
            sellingPrice: $sellingPrice,
            retailPrice: $retailPrice,
            createdAt: $createdAt !== '' ? $createdAt : null,
            expectedUpdatedAt: $expectedUpdatedAt !== '' ? $expectedUpdatedAt : null,
        );
    }

    /**
     * @param array<string, mixed> $input
     * @throws ValidationException
     */
    public static function validateRestock(string $batchId, array $input): InventoryRestockDTO
    {
        self::validateUuid($batchId, 'Batch ID');

        $addQuantity = self::positiveInt($input, 'add_quantity', 'Restock quantity');
        $reason = trim((string)($input['reason'] ?? ''));
        $reason = mb_substr($reason, 0, 500);

        return new InventoryRestockDTO(
            batchId: $batchId,
            addQuantity: $addQuantity,
            reason: $reason,
        );
    }

    /**
     * Normalise + validate pagination/filter query params.
     *
     * @param array<string, mixed> $query
     * @return array{page:int, limit:int, search:string, category_id:string, subcategory_id:string}
     * @throws ValidationException
     */
    public static function validateListQuery(array $query): array
    {
        $page  = (int)($query['page'] ?? 1);
        $limit = (int)($query['limit'] ?? 5);
        $page  = max(1, $page);
        $limit = max(1, min(100, $limit));

        $search       = trim((string)($query['search'] ?? ''));
        $categoryId   = trim((string)($query['category_id'] ?? ''));
        $subcategoryId = trim((string)($query['subcategory_id'] ?? ''));

        if ($categoryId !== '' && !preg_match(self::UUID_PATTERN, $categoryId)) {
            throw new ValidationException('category_id must be a valid UUID');
        }
        if ($subcategoryId !== '' && !preg_match(self::UUID_PATTERN, $subcategoryId)) {
            throw new ValidationException('subcategory_id must be a valid UUID');
        }
        if (mb_strlen($search) > 200) {
            throw new ValidationException('Search term too long');
        }

        return [
            'page'           => $page,
            'limit'          => $limit,
            'search'         => $search,
            'category_id'    => $categoryId,
            'subcategory_id' => $subcategoryId,
        ];
    }

    // ── helpers ────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $input
     * @throws ValidationException
     */
    private static function requiredString(array $input, string $key, string $label): string
    {
        $value = trim((string)($input[$key] ?? ''));
        if ($value === '') {
            throw new ValidationException("{$label} is required");
        }
        return $value;
    }

    /**
     * @param array<string, mixed> $input
     * @throws ValidationException
     */
    private static function positiveInt(array $input, string $key, string $label): int
    {
        if (!array_key_exists($key, $input) || $input[$key] === null || $input[$key] === '') {
            throw new ValidationException("{$label} is required");
        }
        $value = filter_var($input[$key], FILTER_VALIDATE_INT);
        if ($value === false) {
            throw new ValidationException("{$label} must be a whole number");
        }
        if ($value <= 0) {
            throw new ValidationException("{$label} must be greater than zero");
        }
        if ($value > self::MAX_QUANTITY) {
            throw new ValidationException("{$label} exceeds the maximum allowed value");
        }
        return (int)$value;
    }

    /**
     * @param array<string, mixed> $input
     * @throws ValidationException
     */
    private static function nonNegativeInt(array $input, string $key, string $label): int
    {
        if (!array_key_exists($key, $input) || $input[$key] === null || $input[$key] === '') {
            throw new ValidationException("{$label} is required");
        }
        $value = filter_var($input[$key], FILTER_VALIDATE_INT);
        if ($value === false) {
            throw new ValidationException("{$label} must be a whole number");
        }
        if ($value < 0) {
            throw new ValidationException("{$label} cannot be negative");
        }
        if ($value > self::MAX_QUANTITY) {
            throw new ValidationException("{$label} exceeds the maximum allowed value");
        }
        return (int)$value;
    }

    /**
     * @param array<string, mixed> $input
     * @throws ValidationException
     */
    private static function nonNegativeFloat(array $input, string $key, string $label): float
    {
        if (!array_key_exists($key, $input) || $input[$key] === null || $input[$key] === '') {
            return 0.0;
        }
        if (!is_numeric($input[$key])) {
            throw new ValidationException("{$label} must be numeric");
        }
        $value = (float)$input[$key];
        if ($value < 0) {
            throw new ValidationException("{$label} cannot be negative");
        }
        if ($value > 1000000000000.0) {
            throw new ValidationException("{$label} exceeds the maximum allowed value");
        }
        return round($value, 2);
    }

    private static function isValidDate(string $value): bool
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return true;
        }
        return strtotime($value) !== false;
    }
}
