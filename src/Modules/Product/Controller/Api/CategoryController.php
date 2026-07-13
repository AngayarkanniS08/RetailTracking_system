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

    // DELETE /api/categories/{id}
    public function destroy(string $id): void {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        try {
            $this->service->deleteCategory($id);
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