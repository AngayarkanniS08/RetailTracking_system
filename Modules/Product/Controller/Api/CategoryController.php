<?php
namespace Modules\Product\Controller\Api;

use Modules\Product\Repository\CategoryRepository;
use Core\Middlewares\AuthMiddleware;

class CategoryController {
    private CategoryRepository $repo;
    
    public function __construct() {
        $this->repo = new CategoryRepository();
    }
    
    public function index(): void {
        header('Content-Type: application/json');
        AuthMiddleware::authenticate();

        echo json_encode($this->repo->findAll());
    }

    public function store() {
        header('Content-Type: application/json');

        // Verify JWT
        $user = AuthMiddleware::authenticate(); // stops if invalid
        
        // Only POST allowed
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $name = trim($input['name'] ?? '');
        
        if (empty($name)) {
            http_response_code(400);
            echo json_encode(['error' => 'Category name is required']);
            return;
        }
        
        // Check duplicate
        if ($this->repo->findByName($name)) {
            http_response_code(409);
            echo json_encode(['error' => 'Category already exists']);
            return;
        }
        
        $category = $this->repo->create($name);
        echo json_encode(['success' => true, 'category' => $category]);
    }
}