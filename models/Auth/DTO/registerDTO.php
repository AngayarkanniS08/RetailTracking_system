<?php
namespace Auth\DTO;

class RegisterDTO {
    public function __construct(
        public readonly string $username,
        public readonly string $email,
        public readonly string $password
    ) {}
}