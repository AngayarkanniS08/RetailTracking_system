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

    public function bills(Request $request): void
    {
        $customerId = $request->get('customer_id') ?? $_GET['customer_id'] ?? '';
        $name       = $request->get('name') ?? $_GET['name'] ?? 'Customer';

        View::render('customer/bills', [
            'pageTitle'    => 'Billing History — ' . htmlspecialchars($name),
            'currentRoute' => '/customers',
            'customerId'   => $customerId,
            'customerName' => $name,
        ]);
    }
}
