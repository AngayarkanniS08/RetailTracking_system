<?php
declare(strict_types=1);

namespace Controllers;

use Core\Request;
use Core\View;

class ProductController
{
    public function index(Request $request): void
    {
        View::render('product/index', [
            'pageTitle'    => 'Product Master',
            'currentRoute' => '/products',
        ]);
    }

    public function history(Request $request): void
    {
        View::render('product/history', [
            'pageTitle'    => 'Product History',
            'currentRoute' => '/products/history',
        ]);
    }
}
