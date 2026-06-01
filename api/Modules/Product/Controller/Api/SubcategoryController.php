<?php
namespace Modules\Product\Controller\Api;

use Modules\Product\DTO\SubcategoryDTO;
use Modules\Product\Service\SubcategoryService;
use Modules\Product\Repository\SubcategoryRepository;
use Modules\Product\Repository\CategoryRepository;
use Modules\Auth\Validation\ValidationException;
use Core\Middlewares\AuthMiddleware;
use Exception;
use PDOException;

class SubcategoryController {
    private SubcategoryService $service;

    public function __construct() {
        $this->service = new SubcategoryService(new SubcategoryRepository(), new CategoryRepository());
    }

    // GET /api/subcategories  (optionally filtered by ?category_id=...)
    public function index(): void {
        header('Content-Type: application/json');
        AuthMiddleware::authenticate();

        $categoryId = $_GET['category_id'] ?? '';

        try {
            $subs = $categoryId
                ? $this->service->getSubcategoriesByCategory($categoryId)
                : $this->service->getAllSubcategories();

            echo json_encode($subs);
        } catch (ValidationException $e) {
            http_response_code(422);
            echo json_encode(['error' => $e->getMessage()]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }

    // POST /api/subcategories
    public function store(): void {
        header('Content-Type: application/json');
        AuthMiddleware::authenticate();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        $input      = json_decode(file_get_contents('php://input'), true);
        $categoryId = trim($input['category_id'] ?? '');
        $name       = trim($input['name']        ?? '');

        try {
            $dto = new SubcategoryDTO($categoryId, $name);
            $sub = $this->service->createSubcategory($dto);
            http_response_code(201);
            echo json_encode(['success' => true, 'subcategory' => $sub]);
        } catch (ValidationException $e) {
            http_response_code(422);
            echo json_encode(['error' => $e->getMessage()]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }

    // PUT /api/subcategories/{id}
    public function update(string $id): void {
        header('Content-Type: application/json');
        AuthMiddleware::authenticate();

        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        $input      = json_decode(file_get_contents('php://input'), true);
        $categoryId = trim($input['category_id'] ?? '');
        $name       = trim($input['name']        ?? '');

        try {
            $dto = new SubcategoryDTO($categoryId, $name);
            $sub = $this->service->updateSubcategory($id, $dto);
            echo json_encode(['success' => true, 'subcategory' => $sub]);
        } catch (ValidationException $e) {
            http_response_code(422);
            echo json_encode(['error' => $e->getMessage()]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }

    // DELETE /api/subcategories/{id}
    public function destroy(string $id): void {
        header('Content-Type: application/json');
        AuthMiddleware::authenticate();

        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        try {
            $this->service->deleteSubcategory($id);
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