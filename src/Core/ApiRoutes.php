<?php

namespace Core;

use Core\Router;
use config\Database;
use Core\Middlewares\AuthMiddleware;
use Modules\Auth\Controller\Api\ForgotPasswordController;
use Modules\Auth\Controller\Api\ResetPasswordController;
use Modules\Auth\Controller\Api\RegistrationController;
use Modules\Auth\Controller\Api\LoginController;
use Modules\Product\Controller\Api\CategoryController;
use Modules\Product\Controller\Api\SubcategoryController;
use Modules\Product\Controller\Api\ProductController;
use Modules\Product\Controller\Api\UnitController;
use Modules\Inventory\Controller\Api\BatchController;

class ApiRoutes
{
    public static function register(Router $router): void
    {
        // ── Public endpoints (no JWT required) ─────────────────────
        $router->add('POST', '/api/forgot-password', function (): void {
            (new ForgotPasswordController())->forgot();
        });
        $router->add('POST', '/api/reset-password', function (): void {
            (new ResetPasswordController())->reset();
        });
        $router->add('POST', '/api/register', function (): void {
            (new RegistrationController())->register();
        });
        $router->add('POST', '/api/login', function (): void {
            (new LoginController())->login();
        });

        // ── Categories (default 24h expiry) ────────────────────────
        $router->add('GET', '/api/categories', function (): void {
            AuthMiddleware::authenticate(); // default 86400s
            (new CategoryController())->index();
        });
        $router->add('POST', '/api/categories', function (): void {
            AuthMiddleware::authenticate(); // default
            (new CategoryController())->store();
        });
        $router->add('PUT', '/api/categories/{id}', function (array $params): void {
            // Shorter expiry for updates: 15 minutes (900s)
            AuthMiddleware::authenticate(900);
            (new CategoryController())->update($params['id']);
        });
        $router->add('DELETE', '/api/categories/{id}', function (array $params): void {
            // Shorter expiry for deletions: 15 minutes
            AuthMiddleware::authenticate(900);
            (new CategoryController())->destroy($params['id']);
        });

        // ── Subcategories ──────────────────────────────────────────
        $router->add('GET', '/api/subcategories', function (): void {
            AuthMiddleware::authenticate(); // default
            (new SubcategoryController())->index();
        });
        $router->add('POST', '/api/subcategories', function (): void {
            AuthMiddleware::authenticate(); // default
            (new SubcategoryController())->store();
        });
        $router->add('PUT', '/api/subcategories/{id}', function (array $params): void {
            AuthMiddleware::authenticate(900);
            (new SubcategoryController())->update($params['id']);
        });
        $router->add('DELETE', '/api/subcategories/{id}', function (array $params): void {
            AuthMiddleware::authenticate(900);
            (new SubcategoryController())->destroy($params['id']);
        });

        // ── Products ────────────────────────────────────────────────
        $router->add('GET', '/api/products', function (): void {
            AuthMiddleware::authenticate(); // default
            (new ProductController())->index();
        });
        $router->add('POST', '/api/products', function (): void {
            AuthMiddleware::authenticate(); // default
            (new ProductController())->store();
        });
        $router->add('PUT', '/api/products/{id}', function (array $params): void {
            AuthMiddleware::authenticate(900);
            (new ProductController())->update($params['id']);
        });
        $router->add('DELETE', '/api/products/{id}', function (array $params): void {
            AuthMiddleware::authenticate(900);
            (new ProductController())->destroy($params['id']);
        });

        // ── Units (read‑only, default expiry) ──────────────────────
        $router->add('GET', '/api/units', function (): void {
            AuthMiddleware::authenticate(); // default
            (new UnitController())->index();
        });

        // ── Inventory Batches ──────────────────────────────────────
        $router->add('GET', '/api/inventory/batches', function (): void {
            (new BatchController())->index();
        });
        $router->add('POST', '/api/inventory/batches', function (): void {
            (new BatchController())->store();
        });
        $router->add('PUT', '/api/inventory/batches/{id}', function (array $params): void {
            (new BatchController())->update($params['id']);
        });

        // ── Product Alerts (6-Layer flow) ──────────────────────────
        $router->add('GET', '/api/inventory/alerts', function (): void {
            AuthMiddleware::authenticate();
            (new \Modules\Inventory\Controller\Api\AlertController())->index();
        });
        $router->add('POST', '/api/inventory/alerts', function (): void {
            AuthMiddleware::authenticate();
            (new \Modules\Inventory\Controller\Api\AlertController())->store();
        });
        // FIX: renamed from DELETE to PATCH .../disable
        // Reason: the operation resets alert fields to zero (soft-disable), it does
        // not remove a database row. DELETE semantics were misleading to consumers.
        $router->add('PATCH', '/api/inventory/alerts/{productId}/disable', function (array $params): void {
            AuthMiddleware::authenticate(900); // Shorter expiry for modifications
            (new \Modules\Inventory\Controller\Api\AlertController())->disable($params['productId']);
        });

                // ── Vendors (Standalone Profile Management) ────────────────
        // ── Vendor Purchases (6-Layer flow) ──────────────────────────

        // GET /api/purchases – list all purchases (paginated, filterable)
        $router->add('GET', '/api/purchases', function (): void {
            (new \Modules\Vendor\Controller\PurchaseController())->index();
        });

        // POST /api/purchases – create a new purchase (with vendor on-the-fly)
        $router->add('POST', '/api/purchases', function (): void {
            (new \Modules\Vendor\Controller\PurchaseController())->store();
        });

        // GET /api/purchases/{id} – get a single purchase with its items
        $router->add('GET', '/api/purchases/{id}', function (array $params): void {
            (new \Modules\Vendor\Controller\PurchaseController())->show($params['id']);
        });

        // POST /api/purchases/{id}/pay – record a payment against a purchase
        $router->add('POST', '/api/purchases/{id}/pay', function (array $params): void {
            AuthMiddleware::authenticate(900); // Shorter expiry for payment operations
            (new \Modules\Vendor\Controller\PurchaseController())->recordPayment($params['id']);
        });

        // (Optional) PUT /api/purchases/{id} – update a purchase
        $router->add('PUT', '/api/purchases/{id}', function (array $params): void {
            AuthMiddleware::authenticate(900);
            (new \Modules\Vendor\Controller\PurchaseController())->update($params['id']);
        });

        // (Optional) DELETE /api/purchases/{id} – delete a purchase
        $router->add('DELETE', '/api/purchases/{id}', function (array $params): void {
            AuthMiddleware::authenticate(900);
            (new \Modules\Vendor\Controller\PurchaseController())->destroy($params['id']);
        });

        $router->add('GET', '/api/vendors', function (): void {
            AuthMiddleware::authenticate();
            (new \Modules\Vendor\Controller\PurchaseController())->vendorList();
        });

        $router->add('GET', '/api/vendors/{id}/history', function (array $params): void {
            AuthMiddleware::authenticate();
            (new \Modules\Vendor\Controller\PurchaseController())->vendorHistory($params['id']);
        });

        $router->add('GET', '/api/vendors/history/all', function (): void {
            AuthMiddleware::authenticate();
            (new \Modules\Vendor\Controller\PurchaseController())->allHistory();
        });

        $router->add('GET', '/api/vendors/{id}/payments', function (array $params): void {
            AuthMiddleware::authenticate();
            (new \Modules\Vendor\Controller\PurchaseController())->vendorPayments($params['id']);
        });

        $router->add('GET', '/api/vendors/payments/all', function (): void {
            AuthMiddleware::authenticate();
            (new \Modules\Vendor\Controller\PurchaseController())->allPayments();
        });

        // ── Billing / Invoices ─────────────────────────────────────────
        $router->add('GET', '/api/invoices', function (): void {
            AuthMiddleware::authenticate();
            (new \Modules\Billing\Controller\InvoiceController())->index();
        });

        $router->add('POST', '/api/invoices', function (): void {
            AuthMiddleware::authenticate(900);
            (new \Modules\Billing\Controller\InvoiceController())->store();
        });

        $router->add('GET', '/api/invoices/{id}', function (array $params): void {
            AuthMiddleware::authenticate();
            (new \Modules\Billing\Controller\InvoiceController())->show($params['id']);
        });

        $router->add('POST', '/api/invoices/{id}/cancel', function (array $params): void {
            AuthMiddleware::authenticate(900);
            (new \Modules\Billing\Controller\InvoiceController())->cancel($params['id']);
        });

        $router->add('POST', '/api/invoices/{id}/return', function (array $params): void {
            AuthMiddleware::authenticate(900);
            (new \Modules\Billing\Controller\InvoiceController())->returnItems($params['id']);
        });

        $router->add('GET', '/api/invoices/{id}/receipt', function (array $params): void {
            AuthMiddleware::authenticate();
            (new \Modules\Billing\Controller\InvoiceController())->receipt($params['id']);
        });

        // ── POS Search (Valkey-cached) ──────────────────────────────
        $router->add('GET', '/api/pos/search', function (): void {
            AuthMiddleware::authenticate();
            (new \Modules\Billing\Controller\Api\PosSearchController())->search();
        });

        $router->add('POST', '/api/pos/search/flush', function (): void {
            (new \Modules\Billing\Controller\Api\PosSearchController())->flushCache();
        });

        // ── Customers / Credit (Kadan) ─────────────────────────
        $router->add('GET', '/api/customers', function (): void {
            AuthMiddleware::authenticate();
            (new \Modules\Customer\Controller\Api\CustomerController())->index();
        });

        $router->add('POST', '/api/customers', function (): void {
            AuthMiddleware::authenticate(900);
            (new \Modules\Customer\Controller\Api\CustomerController())->store();
        });

        $router->add('GET', '/api/customers/{id}', function (array $params): void {
            AuthMiddleware::authenticate();
            (new \Modules\Customer\Controller\Api\CustomerController())->show($params['id']);
        });

        $router->add('PUT', '/api/customers/{id}', function (array $params): void {
            AuthMiddleware::authenticate(900);
            (new \Modules\Customer\Controller\Api\CustomerController())->update($params['id']);
        });

        $router->add('POST', '/api/customers/{id}/pay', function (array $params): void {
            AuthMiddleware::authenticate(900);
            (new \Modules\Customer\Controller\Api\CustomerController())->pay($params['id']);
        });

        $router->add('GET', '/api/customers/{id}/ledger', function (array $params): void {
            AuthMiddleware::authenticate();
            (new \Modules\Customer\Controller\Api\CustomerController())->ledger($params['id']);
        });

        // ── Dashboard / Reports ──────────────────────────────────────────
        $router->add('GET', '/api/dashboard/stats', function (): void {
            AuthMiddleware::authenticate();
            (new \Modules\Reports\Controller\Api\DashboardController())->stats();
        });

        $router->add('GET', '/api/dashboard/stock-intel', function (): void {
            AuthMiddleware::authenticate();
            (new \Modules\Reports\Controller\Api\DashboardController())->stockIntel();
        });

    }
}
