<?php
declare(strict_types=1);

namespace Controllers;

use Core\Request;
use Core\View;

class InventoryController
{
    public function index(Request $request): void
    {
        View::render('inventory/index', [
            'pageTitle'    => 'Inventory Management',
            'currentRoute' => '/inventory',
        ]);
    }
}
