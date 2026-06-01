<?php
namespace Modules\Product\Controller\Api;

use Modules\Product\Repository\UnitRepository;
use Core\Middlewares\AuthMiddleware;
use Exception;

class UnitController {
    private UnitRepository $repo;

    public function __construct() {
        $this->repo = new UnitRepository();
    }

    // GET /api/units
    public function index(): void {
        header('Content-Type: application/json');
        AuthMiddleware::authenticate();

        try {
            echo json_encode($this->repo->findAll());
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }
}
