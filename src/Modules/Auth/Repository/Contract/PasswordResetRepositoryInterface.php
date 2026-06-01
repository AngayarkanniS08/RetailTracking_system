<?php
namespace Modules\Auth\Repository\Contract;

interface PasswordResetRepositoryInterface {
    public function create(string $userId, string $token, \DateTimeInterface $expiresAt): void;
    public function findByToken(string $token): ?array;
    public function deleteByToken(string $token): void;
    public function deleteByUserId(string $userId): void; // optional
}