<?php
namespace Modules\Inventory\Service;

use Modules\Inventory\Repository\Contract\BatchRepositoryInterface;
use Core\Cache\ValkeyCache;

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
        try {
            $valkey = ValkeyCache::getClient();
            // Clear any cached dashboard or stock intelligence reports
            $keys = $valkey->keys('reports:*');
            if ($keys) {
                $valkey->del($keys);
            }
        } catch (\Exception $e) {
            error_log('Valkey cache invalidation error: ' . $e->getMessage());
        }
    }

    public function getBatchesPaginated(int $page, int $limit, string $search = '', string $categoryId = '', string $subcategoryId = ''): array
    {
        $result = $this->repo->findPaginated($page, $limit, $search, $categoryId, $subcategoryId);
        $stats = $this->repo->getStats($search, $categoryId, $subcategoryId);
        
        $totalPages = ceil($result['total'] / $limit);
        
        return [
            'data' => $result['data'],
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
