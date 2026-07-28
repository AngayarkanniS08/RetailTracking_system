<?php
declare(strict_types=1);

namespace Controllers;

use Core\Request;
use Core\View;

class ReportsController
{
    public function index(Request $request): void
    {
        View::render('reports/index', [
            'pageTitle'    => 'Sales & Business Reports',
            'currentRoute' => '/reports',
        ]);
    }

    public function daily(Request $request): void
    {
        View::render('reports/daily_sales', [
            'pageTitle'    => 'Day to Day Selling',
            'currentRoute' => '/daily-sales',
        ]);
    }
}
