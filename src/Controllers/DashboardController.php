<?php
declare(strict_types=1);

namespace Controllers;

use Core\Request;
use Core\View;

class DashboardController
{
    public function index(Request $request): void
    {
        View::render('dashboard/index', [
            'pageTitle'    => 'Dashboard',
            'currentRoute' => '/dashboard',
        ]);
    }
}
