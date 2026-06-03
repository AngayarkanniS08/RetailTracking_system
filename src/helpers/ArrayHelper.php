<?php
namespace App\Common\Helpers;

class ArrayHelper {
    public static function getPaginationMeta(int $page, int $limit, int $total): array {
        $totalPages = (int) ceil($total / $limit);
        return [
            'current_page' => $page,
            'per_page'     => $limit,
            'total'        => $total,
            'total_pages'  => $totalPages,
            'has_next'     => $page < $totalPages,
            'has_prev'     => $page > 1
        ];
    }
}