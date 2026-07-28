<?php
declare(strict_types=1);

namespace Controllers;

use Core\Request;
use Core\View;

class HealthController
{
    public function index(Request $request): void
    {
        View::render('system/health', [
            'pageTitle'    => 'System Health & Performance',
            'currentRoute' => '/system/health',
        ]);
    }
}
