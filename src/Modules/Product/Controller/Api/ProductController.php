<?php
namespace Modules\Product\Controller\Api;

use Modules\Product\DTO\ProductDTO;
use Modules\Product\Service\ProductService;
use Modules\Product\Repository\ProductRepository;
use Modules\Product\Repository\CategoryRepository;
use Modules\Auth\Validation\ValidationException;
use Exception;
use PDOException;

class ProductController {
    private ProductService $service;

    public function __construct() {
        $this->service = new ProductService(new ProductRepository(), new CategoryRepository());
    }

    // GET /api/products
    public function index(): void {
        header('Content-Type: application/json');
        
        try {
            $products = $this->service->getAllProducts();
            echo json_encode($products);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }

    // POST /api/products
    public function store(): void {
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
    public function update(string $id): void {
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
    public function destroy(string $id): void {
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
