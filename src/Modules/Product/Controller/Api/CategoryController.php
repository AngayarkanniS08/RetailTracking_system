<?php
namespace Modules\Product\Controller\Api;

use Modules\Product\DTO\CategoryDTO;
use Modules\Product\Service\CategoryService;
use Modules\Product\Repository\CategoryRepository;
use Modules\Auth\Validation\ValidationException;
use Core\Middlewares\AuthMiddleware;
use Exception;
use PDOException;

class CategoryController {
    private CategoryService $service;

    public function __construct() {
        $this->service = new CategoryService(new CategoryRepository());
    }

    // GET /api/categories
    public function index(): void {
        header('Content-Type: application/json');

        try {
            $categories = $this->service->getAllCategories();
            echo json_encode($categories);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }

    // POST /api/categories
    public function store(): void {
        header('Content-Type: application/json');
       

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $name  = trim($input['name'] ?? '');

        try {
            $dto      = new CategoryDTO($name);
            $category = $this->service->createCategory($dto);
            http_response_code(201);
            echo json_encode(['success' => true, 'category' => $category]);
        } catch (ValidationException $e) {
            http_response_code(422);
            echo json_encode(['error' => $e->getMessage()]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23505') {
                http_response_code(422);
                echo json_encode(['error' => 'This category already exists.']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Database error']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }

    // PUT /api/categories/{id}
    public function update(string $id): void {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $name  = trim($input['name'] ?? '');

        try {
            $dto      = new CategoryDTO($name);
            $category = $this->service->updateCategory($id, $dto);
            echo json_encode(['success' => true, 'category' => $category]);
        } catch (ValidationException $e) {
            http_response_code(422);
            echo json_encode(['error' => $e->getMessage()]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23505') {
                http_response_code(422);
                echo json_encode(['error' => 'This category already exists.']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Database error']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }

    // DELETE /api/categories/{id}?force=true|false
    public function destroy(string $id): void {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            http_response_code(405);
            echo json_encode(['success' => false, 'code' => 'METHOD_NOT_ALLOWED', 'message' => 'Method not allowed']);
            return;
        }

        if (empty(trim($id))) {
            http_response_code(400);
            echo json_encode(['success' => false, 'code' => 'INVALID_REQUEST', 'message' => 'Invalid Category ID']);
            return;
        }

        $forceParam = $_GET['force'] ?? false;
        $force = in_array(strtolower((string)$forceParam), ['true', '1'], true);

        try {
            $result = $this->service->deleteCategory($id, $force);
            echo json_encode($result);
        } catch (ValidationException $e) {
            $statusCode = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 422;
            http_response_code($statusCode);

            $decoded = json_decode($e->getMessage(), true);
            if (is_array($decoded)) {
                echo json_encode(array_merge(['success' => false], $decoded));
            } else {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        } catch (PDOException $e) {
            if ($e->getCode() === '23503') {
                http_response_code(409);
                echo json_encode([
                    'success' => false,
                    'code' => 'CATEGORY_HAS_PRODUCTS',
                    'message' => 'Category is currently assigned to products or other records and cannot be deleted.',
                    'action' => 'Use force delete to remove the category and its products.'
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'code' => 'DATABASE_ERROR', 'message' => 'Database error']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'code' => 'INTERNAL_ERROR', 'message' => 'Internal server error']);
        }
    }
}