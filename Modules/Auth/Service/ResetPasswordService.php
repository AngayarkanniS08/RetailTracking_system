<?php
namespace Modules\Auth\Service;

use Modules\Auth\DTO\ResetPasswordDTO;
use Modules\Auth\Repository\Contract\UserRepositoryInterface;
use Modules\Auth\Repository\Contract\PasswordResetRepositoryInterface;
use Modules\Auth\Validation\ValidationException;

class ResetPasswordService {
    private UserRepositoryInterface $userRepo;
    private PasswordResetRepositoryInterface $resetRepo;
    
    public function __construct(
        UserRepositoryInterface $userRepo,
        PasswordResetRepositoryInterface $resetRepo
    ) {
        $this->userRepo = $userRepo;
        $this->resetRepo = $resetRepo;
    }
    
    public function resetPassword(ResetPasswordDTO $dto): void {
        if ($dto->password !== $dto->passwordConfirmation) {
            throw new ValidationException("Passwords do not match");
        }
        
        $reset = $this->resetRepo->findByToken($dto->token);
        if (!$reset) {
            throw new ValidationException("Invalid or expired token");
        }
        
        $user = $this->userRepo->findById($reset['user_id']);
        if (!$user || $user['email'] !== $dto->email) {
            throw new ValidationException("Invalid request");
        }
        
        $hashed = password_hash($dto->password, PASSWORD_DEFAULT);
        $this->userRepo->updatePassword($user['id'], $hashed);
        $this->resetRepo->deleteByToken($dto->token);
    }
}