<?php
namespace Modules\Auth\Model;

class User {
    public function __construct(
        public ?string $id,
        public string $username,
        public string $email,
        public string $passwordHash,
        public ?string $createdAt
    ) {}
}