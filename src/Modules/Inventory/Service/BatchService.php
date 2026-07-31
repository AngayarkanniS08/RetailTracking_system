<?php
namespace Modules\Inventory\Service;

use Modules\Inventory\Repository\Contract\BatchRepositoryInterface;
use Core\Cache\CacheInvalidationService;

class BatchService
{
    private BatchRepositoryInterface $repo;

    public function __construct(BatchRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    public function getAllBatches(): array
    {
        return $this->repo->findAll();
    }

    public function getBatchById(string $id): ?array
    {
        return $this->repo->findById($id);
    }

    public function createBatch(array $data): array
    {
        $this->invalidateCache();
        return $this->repo->create($data);
    }

    public function updateBatch(string $id, array $data): bool
    {
        $this->invalidateCache();
        return $this->repo->updateall($id, $data);
    }

    private function invalidateCache(): void
    {
        $service = new CacheInvalidationService();
        $service->invalidatePatterns(
            ['inventory:batches:*', 'pos:search:*'],
            ['operation' => 'batchMutation', 'source' => 'BatchService']
        );
    }

    public function getBatchesPaginated(int $page, int $limit, string $search = '', string $categoryId = '', string $subcategoryId = ''): array
    {
        $result = $this->repo->findPaginated($page, $limit, $search, $categoryId, $subcategoryId);
        $stats = $this->repo->getStats($search, $categoryId, $subcategoryId);
        
        $totalPages = ceil($result['total'] / $limit);

        $enrichedData = array_map(function ($item) {
            $qty = (float)($item['quantity'] ?? $item['stock_qty'] ?? 0);
            $threshold = (float)($item['min_threshold'] ?? 10);
            if ($qty <= 0) {
                $item['stock_status'] = 'OUT_OF_STOCK';
                $item['status_text'] = 'Out of Stock';
                $item['status_badge_class'] = 'bg-danger';
            } elseif ($qty <= $threshold) {
                $item['stock_status'] = 'LOW_STOCK';
                $item['status_text'] = 'Low Stock';
                $item['status_badge_class'] = 'bg-warning text-dark';
            } else {
                $item['stock_status'] = 'IN_STOCK';
                $item['status_text'] = 'In Stock';
                $item['status_badge_class'] = 'bg-success';
            }
            return $item;
        }, $result['data']);
        
        return [
            'data' => $enrichedData,
            'pagination' => [
                'current_page' => $page,
                'total_pages'  => max(1, $totalPages),
                'limit'        => $limit,
                'total_records'=> $result['total'],
                'has_next'     => $page < $totalPages,
                'has_prev'     => $page > 1
            ],
            'stats' => $stats
        ];
    }

}
