<?php
namespace Modules\Auth\DTO;

class ForgotPasswordDTO {
    public function __construct(
        public readonly string $email
    ) {}
}