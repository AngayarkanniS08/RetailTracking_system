<?php
declare(strict_types=1);

namespace Controllers;

use Core\Request;
use Core\View;

class VendorController
{
    public function index(Request $request): void
    {
        View::render('vendor/index', [
            'pageTitle'    => 'Vendor List',
            'currentRoute' => '/vendors',
        ]);
    }

    public function history(Request $request): void
    {
        View::render('vendor/history', [
            'pageTitle'    => 'Vendor Purchase History',
            'currentRoute' => '/vendors/history',
        ]);
    }
}
