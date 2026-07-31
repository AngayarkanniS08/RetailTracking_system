<?php
declare(strict_types=1);

namespace Modules\Inventory\Service;

use Modules\Auth\Validation\ValidationException;

/**
 * InventorySearchService — normalises free-text inventory search.
 *
 * Supports matching across product name, SKU/display id, HSN/barcode, batch
 * number, vendor name, category and subcategory. The service only prepares a
 * safe, tenant-agnostic token; the repository owns the SQL.
 */
final class InventorySearchService
{
    private const MAX_SEARCH_LENGTH = 200;

    /**
     * Normalise a raw search string into a safe ILIKE token.
     *
     * @throws ValidationException
     */
    public function normalizeSearch(?string $raw): string
    {
        $raw = trim((string)$raw);
        if ($raw === '') {
            return '';
        }
        if (mb_strlen($raw) > self::MAX_SEARCH_LENGTH) {
            throw new ValidationException('Search term too long');
        }

        // Escape wildcards so user input cannot expand the match set.
        // NOTE: backslash MUST be escaped first — str_replace applies array
        // needles sequentially, so a later '\' pass would double-escape the
        // backslashes just inserted for '%' and '_'.
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $raw);

        // Collapse internal whitespace and split into tokens so multi-word
        // queries match as a combined phrase as well as any single token.
        $escaped = preg_replace('/\s+/', ' ', $escaped) ?? $escaped;
        return $escaped;
    }
}
