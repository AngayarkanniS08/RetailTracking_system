<?php
namespace Modules\Product\Controller\Api;

use Modules\Product\Repository\ProductRepository;
use Core\Middlewares\AuthMiddleware;

class ProductController {
    private ProductRepository $repo;

    public function __construct() {
        $this->repo = new ProductRepository();
    }

    // GET /api/products
    public function index(): void {
        header('Content-Type: application/json');
        AuthMiddleware::authenticate();

        echo json_encode($this->repo->findAll());
    }

    // POST /api/products
    public function store(): void {
        header('Content-Type: application/json');
        AuthMiddleware::authenticate();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $name       = trim($input['name']        ?? '');
        $categoryId = trim($input['category_id'] ?? '');
        $unit       = trim($input['unit']        ?? '');
        $hsnCode    = trim($input['hsn_code']    ?? '');
        $gstRate    = (float)($input['gst_rate'] ?? 0);

        if (empty($name) || empty($categoryId) || empty($unit)) {
            http_response_code(400);
            echo json_encode(['error' => 'Name, category_id, and unit are required']);
            return;
        }

        $product = $this->repo->create($name, $categoryId, $unit, $hsnCode ?: null, $gstRate);
        echo json_encode(['success' => true, 'product' => $product]);
    }

    // DELETE /api/products/{id}
    public function destroy(string $id): void {
        header('Content-Type: application/json');
        AuthMiddleware::authenticate();

        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        $deleted = $this->repo->delete($id);
        if (!$deleted) {
            http_response_code(404);
            echo json_encode(['error' => 'Product not found']);
            return;
        }

        echo json_encode(['success' => true]);
    }
}
