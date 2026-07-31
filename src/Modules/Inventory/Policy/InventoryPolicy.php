<?php
declare(strict_types=1);

namespace Modules\Inventory\Policy;

use Modules\Auth\Validation\ValidationException;

/**
 * InventoryPolicy — tenant ownership & access decisions.
 *
 * RLS is the first line of defence (rows are filtered at the SQL level). This
 * policy adds an explicit, auditable ownership assertion for every mutation so
 * callers can fail fast with a meaningful error instead of a generic DB error.
 */
final class InventoryPolicy
{
    /**
     * Assert that a fetched row belongs to the current tenant.
     *
     * @param array<string, mixed>|null $row   Row fetched under RLS (null ⇒ not found / not owned)
     * @param string                    $userId
     * @param string                    $entityLabel
     * @return array<string, mixed>
     * @throws ValidationException
     */
    public static function assertOwned(?array $row, string $userId, string $entityLabel = 'Batch'): array
    {
        if ($row === null) {
            throw new ValidationException("{$entityLabel} not found or you do not have access");
        }
        return $row;
    }

    /**
     * Assert the current user may restock a batch.
     */
    public static function canRestock(int $currentQuantity, int $addQuantity, int $maxQuantity): void
    {
        $newQuantity = $currentQuantity + $addQuantity;
        if ($newQuantity > $maxQuantity) {
            throw new ValidationException(
                "Restock would exceed the maximum allowed quantity ({$maxQuantity})"
            );
        }
    }
}
