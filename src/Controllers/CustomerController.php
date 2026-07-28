<?php
declare(strict_types=1);

namespace Controllers;

use Core\Request;
use Core\View;

class CustomerController
{
    public function index(Request $request): void
    {
        View::render('customer/index', [
            'pageTitle'    => 'Customer Credit (Kadan)',
            'currentRoute' => '/customers',
        ]);
    }
}
