<?php
namespace Modules\Billing\Controller\Api;

use Core\Middlewares\AuthMiddleware;
use Core\Cache\ValkeyCache;
use Config\Database;
use Exception;
use PDO;

class PosSearchController
{
    public function search(): void
    {
        header('Content-Type: application/json');

        $q = trim($_GET['q'] ?? '');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = max(1, min(50, (int)($_GET['limit'] ?? 8)));
        $offset = ($page - 1) * $limit;

        $user = AuthMiddleware::authenticate();
        $userId = $user->data->user_id ?? null;

        $cacheKey = sprintf(
            'pos:search:%s:page:%d:limit:%d:user:%s',
            md5($q),
            $page,
            $limit,
            $userId ?: 'guest'
        );

        $valkey = null;
        try {
            $valkey = ValkeyCache::getClient();
            $cached = $valkey->get($cacheKey);
            if ($cached !== false && $cached !== null) {
                echo $cached;
                return;
            }
        } catch (Exception $e) {
            error_log('Valkey read error: ' . $e->getMessage());
        }

        try {
            $db = Database::getConnection();

            $likeTerm = $q !== '' ? '%' . $q . '%' : null;
            $params = [];

            $where = "b.user_id = current_setting('app.current_user_id')::uuid AND b.remaining_qty > 0";
            if ($likeTerm !== null) {
                $where .= " AND (p.name ILIKE ? OR p.display_id::text ILIKE ? OR b.batch_number ILIKE ? OR b.id::text ILIKE ?)";
                $params = [$likeTerm, $likeTerm, $likeTerm, $likeTerm];
            }

            $countStmt = $db->prepare("SELECT COUNT(*) FROM public.inventory_batches b
                JOIN public.products p ON p.id = b.product_id WHERE $where");
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();

            $dataSql = "SELECT b.id AS batch_id, b.batch_number, b.product_id,
                p.display_id, p.name AS product_name, p.hsn_code, p.unit, p.gst_rate,
                b.selling_price, b.retail_price, b.remaining_qty AS quantity,
                b.vendor_name
                FROM public.inventory_batches b
                JOIN public.products p ON p.id = b.product_id
                WHERE $where
                ORDER BY p.name ASC, b.created_at DESC
                LIMIT ? OFFSET ?";
            $dataParams = $params;
            $dataParams[] = $limit;
            $dataParams[] = $offset;

            $dataStmt = $db->prepare($dataSql);
            $dataStmt->execute($dataParams);
            $results = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

            $totalPages = max(1, (int)ceil($total / $limit));

            $response = [
                'data' => $results,
                'pagination' => [
                    'current_page' => $page,
                    'per_page'     => $limit,
                    'total'        => $total,
                    'total_pages'  => $totalPages,
                    'has_next'     => $page < $totalPages,
                    'has_prev'     => $page > 1
                ]
            ];

            $json = json_encode($response);

            if ($valkey) {
                try {
                    $valkey->setex($cacheKey, 300, $json);
                } catch (Exception $e) {
                    error_log('Valkey write error: ' . $e->getMessage());
                }
            }

            echo $json;
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Search failed: ' . $e->getMessage()]);
        }
    }

    public function flushCache(): void
    {
        AuthMiddleware::authenticate();
        try {
            $valkey = ValkeyCache::getClient();
            $keys = $valkey->keys('pos:search:*');
            if (!empty($keys)) {
                $valkey->del($keys);
            }
            echo json_encode(['success' => true, 'flushed' => count($keys ?? [])]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to flush cache: ' . $e->getMessage()]);
        }
    }
}
