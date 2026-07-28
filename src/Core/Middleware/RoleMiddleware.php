<?php
declare(strict_types=1);

namespace Core\Middleware;

use Core\Request;
use Core\Response;

class RoleMiddleware
{
    public static function handle(Request $request): void
    {
        // Role authorization check hook
    }
}
