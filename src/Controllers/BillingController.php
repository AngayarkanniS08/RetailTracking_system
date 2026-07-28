<?php
declare(strict_types=1);

namespace Controllers;

use Core\Request;
use Core\View;

class BillingController
{
    public function index(Request $request): void
    {
        View::render('billing/index', [
            'pageTitle'    => 'Billing (POS)',
            'currentRoute' => '/billing',
        ]);
    }
}
