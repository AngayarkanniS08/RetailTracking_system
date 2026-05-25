<?php
namespace Modules\Auth\Controller\Api;

use Modules\Auth\DTO\ForgotPasswordDTO;
use Modules\Auth\Service\ForgotPasswordService;
use Modules\Auth\Repository\UserRepository;
use Modules\Auth\Repository\PasswordResetRepository;
use Modules\Auth\validation\ValidationException;

class ForgotPasswordController {
    private ForgotPasswordService $service;
    
    public function __construct() {
        $userRepo = new UserRepository();
        $resetRepo = new PasswordResetRepository();
        $this->service = new ForgotPasswordService($userRepo, $resetRepo);
    }
    
    public function forgot(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $email = trim($input['email'] ?? '');
        
        if (empty($email)) {
            http_response_code(400);
            echo json_encode(['error' => 'Email required']);
            return;
        }
        
        try {
            $dto = new ForgotPasswordDTO($email);
            $this->service->sendResetLink($dto);
            echo json_encode(['success' => true, 'message' => 'If that email exists, we sent a reset link.']);
        } catch (ValidationException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Internal error']);
        }
    }
}