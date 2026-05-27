<?php
namespace Modules\Product\Controller\Api;

use Modules\Product\DTO\SubcategoryDTO;
use Modules\Product\Service\SubcategoryService;
use Modules\Product\Repository\Contract\SubcategoryRepositoryInterface;
use Core\Middlewares\AuthMiddleware;
use Modules\Auth\validation\ValidationException;

class SubcategoryController {
    private SubcategoryService $service;
    
    public function __construct(SubcategoryRepositoryInterface $repo) {
        $this->service = new SubcategoryService($repo);
    }
    
    public function store() {
        AuthMiddleware::authenticate(); // JWT check
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $categoryId = $input['category_id'] ?? '';
        $name = trim($input['name'] ?? '');
        
        try {
            $dto = new SubcategoryDTO($categoryId, $name);
            $sub = $this->service->createSubcategory($dto);
            echo json_encode(['success' => true, 'subcategory' => $sub]);
        } catch (ValidationException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }
    
    public function index() {
        AuthMiddleware::authenticate();
        
        $categoryId = $_GET['category_id'] ?? '';
        try {
            $subs = $this->service->getSubcategoriesByCategory($categoryId);
            echo json_encode($subs);
        } catch (ValidationException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}