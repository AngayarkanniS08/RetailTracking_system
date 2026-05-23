<?php
namespace Modules\Auth\Service;

use Modules\Auth\DTO\RegisterDTO;
use Modules\Auth\Repository\Contract\UserRepositoryInterface;
use Modules\Validation\ValidationException;

class RegistrationService {
    private UserRepositoryInterface $userRepo;
    
    public function __construct(UserRepositoryInterface $userRepo) {
        $this->userRepo = $userRepo;
    }
    
    /**
     * @throws ValidationException
     */
    public function register(RegisterDTO $dto): array {
        // 1. Validate input
        if (empty($dto->username) || empty($dto->email) || empty($dto->password)) {
            throw new ValidationException("All fields are required");
        }
        if (!filter_var($dto->email, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException("Invalid email format");
        }
        
        // 2. Check for duplicates
        if ($this->userRepo->findByUsername($dto->username)) {
            throw new ValidationException("Username already taken");
        }
        if ($this->userRepo->findByEmail($dto->email)) {
            throw new ValidationException("Email already registered");
        }
        
        // 3. Hash password and save
        $hashed = password_hash($dto->password, PASSWORD_DEFAULT);
        $user = $this->userRepo->save($dto->username, $dto->email, $hashed);
        
        return $user;
    }
}