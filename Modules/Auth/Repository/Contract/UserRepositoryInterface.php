<?php

namespace Modules\Auth\Repository\Contract;

interface UserRepositoryInterface
{
    public function findByUsername(string $username): ?array;
    public function findByEmail(string $email): ?array;
    public function findByUsernameOrEmail(string $usernameOrEmail): ?array;
    public function save(string $username, string $email, string $hashedPassword): array;
}
