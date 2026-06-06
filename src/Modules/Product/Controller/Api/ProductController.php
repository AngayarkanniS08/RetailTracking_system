<?php

namespace Modules\Product\Controller\Api;

use Modules\Product\DTO\ProductDTO;
use Modules\Product\Service\ProductService;
use Modules\Product\Repository\ProductRepository;
use Modules\Product\Repository\CategoryRepository;
use Modules\Auth\Validation\ValidationException;
use Core\Cache\ValkeyCache;
use Core\Middlewares\AuthMiddleware;
use Exception;
use PDOException;

class ProductController
{
    private ProductService $service;

    public function __construct()
    {
        $this->service = new ProductService(new ProductRepository(), new CategoryRepository());
    }

    // GET /api/products
    public function index(): void
    {
        header('Content-Type: application/json');
        $page = (int)($_GET['page'] ?? 1);
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 4; // default to 4 if not specified
        $search = trim($_GET['search'] ?? '');
        $categoryId = trim($_GET['category_id'] ?? '');
        $subcategoryId = trim($_GET['subcategory_id'] ?? '');

        // Safely authenticate using JWT and extract user context
        $user = AuthMiddleware::authenticate();
        $userId = $user->data->user_id ?? null;

        // Build cache key (unique per search, page, filters, user)
        $cacheKey = sprintf(
            'products:search:%s:cat:%s:subcat:%s:page:%d:limit:%d:user:%s',
            md5($search),
            $categoryId ?: 'all',
            $subcategoryId ?: 'all',
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
        } catch (\Exception $e) {
            error_log('Valkey read error: ' . $e->getMessage());
            // proceed to database
        }

        try {
            // Database query (paginated fetch with category and subcategory filtering)
            $result = $this->service->getProductsPaginated($page, $limit, $search, $categoryId, $subcategoryId);
            $json = json_encode($result);

            // Store in cache with TTL (e.g., 5 minutes = 300 seconds)
            if ($valkey) {
                try {
                    $valkey->setex($cacheKey, 300, $json);
                } catch (\Exception $e) {
                    error_log('Valkey write error: ' . $e->getMessage());
                }
            }

            echo $json;
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to load products']);
        }
    }

    // POST /api/products
    public function store(): void
    {
        header('Content-Type: application/json');


        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        $input         = json_decode(file_get_contents('php://input'), true);
        $name          = trim($input['name']           ?? '');
        $categoryId    = trim($input['category_id']    ?? '');
        $subcategoryId = trim($input['subcategory_id'] ?? '') ?: null;
        $unit          = trim($input['unit']           ?? '');
        $hsnCode       = trim($input['hsn_code']       ?? '') ?: null;
        $gstRate       = (float)($input['gst_rate']    ?? 0);

        try {
            $dto     = new ProductDTO($name, $categoryId, $subcategoryId, $unit, $hsnCode, $gstRate);
            $product = $this->service->createProduct($dto);
            http_response_code(201);
            echo json_encode(['success' => true, 'product' => $product]);
        } catch (ValidationException $e) {
            http_response_code(422);
            echo json_encode(['error' => $e->getMessage()]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }

    // PUT /api/products/{id}
    public function update(string $id): void
    {
        header('Content-Type: application/json');


        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        $input         = json_decode(file_get_contents('php://input'), true);
        $name          = trim($input['name']           ?? '');
        $categoryId    = trim($input['category_id']    ?? '');
        $subcategoryId = trim($input['subcategory_id'] ?? '') ?: null;
        $unit          = trim($input['unit']           ?? '');
        $hsnCode       = trim($input['hsn_code']       ?? '') ?: null;
        $gstRate       = (float)($input['gst_rate']    ?? 0);

        try {
            $dto     = new ProductDTO($name, $categoryId, $subcategoryId, $unit, $hsnCode, $gstRate);
            $product = $this->service->updateProduct($id, $dto);
            echo json_encode(['success' => true, 'product' => $product]);
        } catch (ValidationException $e) {
            http_response_code(422);
            echo json_encode(['error' => $e->getMessage()]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }

    // DELETE /api/products/{id}
    public function destroy(string $id): void
    {
        header('Content-Type: application/json');


        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        try {
            $this->service->deleteProduct($id);
            echo json_encode(['success' => true]);
        } catch (ValidationException $e) {
            http_response_code(404);
            echo json_encode(['error' => $e->getMessage()]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23503') {
                http_response_code(422);
                echo json_encode(['error' => 'Cannot delete because it is linked to other records.']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Database error']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }
}
