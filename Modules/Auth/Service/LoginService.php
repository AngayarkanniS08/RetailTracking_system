<?php

namespace Modules\Auth\Service;

use Modules\Auth\DTO\LoginDTO;
use Modules\Auth\Repository\Contract\UserRepositoryInterface;
use Modules\Auth\validation\ValidationException; // or define a simple exception class

class LoginService
{
    private UserRepositoryInterface $userRepo;

    public function __construct(UserRepositoryInterface $userRepo)
    {
        $this->userRepo = $userRepo;
    }

    /**
     * @throws ValidationException
     * @return array{id: string, username: string, email: string}
     */
    public function login(LoginDTO $dto): array
    {
        if (empty($dto->username) || empty($dto->password)) {
            throw new ValidationException("Username and password are required");
        }

        $user = $this->userRepo->findByUsernameOrEmail($dto->username);
        if (!$user) {
            throw new ValidationException("Invalid credentials");
        }

        if (!password_verify($dto->password, $user['password_hash'])) {
            throw new ValidationException("Invalid credentials");
        }

        // Return safe user data (no password hash)
        return [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
        ];
    }
}
