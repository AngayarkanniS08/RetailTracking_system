<?php

namespace Core;

use Core\Router;
use config\Database;
use Core\Middlewares\AuthMiddleware;
use Modules\Auth\Controller\Api\ForgotPasswordController;
use Modules\Auth\Controller\Api\ResetPasswordController;
use Modules\Auth\Controller\Api\RegistrationController;
use Modules\Auth\Controller\Api\LoginController;
use Modules\Auth\Controller\Api\AuthController;
use Modules\Product\Controller\Api\CategoryController;
use Modules\Product\Controller\Api\SubcategoryController;
use Modules\Product\Controller\Api\ProductController;
use Modules\Product\Controller\Api\UnitController;
use Modules\Inventory\Controller\Api\BatchController;
use Modules\Backup\Controller\BackupController;
use Modules\Backup\Controller\BackupConfigController;

class ApiRoutes
{
    public static function register(Router $router): void
    {
        // ── Public endpoints (no JWT required) ─────────────────────
        $router->add('POST', '/api/forgot-password', function (): void {
            (new ForgotPasswordController())->forgot();
        });
        $router->add('POST', '/api/auth/forgot-password', function (): void {
            (new ForgotPasswordController())->forgot();
        });

        $router->add('POST', '/api/reset-password', function (): void {
            (new ResetPasswordController())->reset();
        });
        $router->add('POST', '/api/auth/reset-password', function (): void {
            (new ResetPasswordController())->reset();
        });

        $router->add('POST', '/api/register', function (): void {
            (new RegistrationController())->register();
        });
        $router->add('POST', '/api/auth/register', function (): void {
            (new RegistrationController())->register();
        });

        $router->add('POST', '/api/login', function (): void {
            (new LoginController())->login();
        });
        $router->add('POST', '/api/auth/login', function (): void {
            (new LoginController())->login();
        });

        // ── Token refresh (no auth — accepts an expired token) ────
        $router->add('POST', '/api/auth/refresh', function (): void {
            (new AuthController())->refresh();
        });

        // ── Categories (default 24h expiry) ────────────────────────
        $router->add('GET', '/api/categories', function (): void {
            AuthMiddleware::authenticate();
            (new CategoryController())->index();
        });
        $router->add('POST', '/api/categories', function (): void {
            AuthMiddleware::authenticate();
            (new CategoryController())->store();
        });
        $router->add('PUT', '/api/categories/{id}', function (array $params): void {
            AuthMiddleware::authenticate(900);
            (new CategoryController())->update($params['id']);
        });
        $router->add('DELETE', '/api/categories/{id}', function (array $params): void {
            AuthMiddleware::authenticate(900);
            (new CategoryController())->destroy($params['id']);
        });

        // ── Subcategories ──────────────────────────────────────────
        $router->add('GET', '/api/subcategories', function (): void {
            AuthMiddleware::authenticate();
            (new SubcategoryController())->index();
        });
        $router->add('POST', '/api/subcategories', function (): void {
            AuthMiddleware::authenticate();
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
            AuthMiddleware::authenticate();
            (new ProductController())->index();
        });
        $router->add('POST', '/api/products', function (): void {
            AuthMiddleware::authenticate();
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

        // ── Units ──────────────────────────────────────────────────
        $router->add('GET', '/api/units', function (): void {
            AuthMiddleware::authenticate();
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

        // ── Product Alerts ──────────────────────────────────────────
        $router->add('GET', '/api/inventory/alerts', function (): void {
            AuthMiddleware::authenticate();
            (new \Modules\Inventory\Controller\Api\AlertController())->index();
        });
        $router->add('POST', '/api/inventory/alerts', function (): void {
            AuthMiddleware::authenticate();
            (new \Modules\Inventory\Controller\Api\AlertController())->store();
        });
        $router->add('PATCH', '/api/inventory/alerts/{productId}/disable', function (array $params): void {
            AuthMiddleware::authenticate(900);
            (new \Modules\Inventory\Controller\Api\AlertController())->disable($params['productId']);
        });

        // ── Purchases & Vendors ──────────────────────────────────────
        $router->add('GET', '/api/purchases', function (): void {
            (new \Modules\Vendor\Controller\PurchaseController())->index();
        });
        $router->add('POST', '/api/purchases', function (): void {
            (new \Modules\Vendor\Controller\PurchaseController())->store();
        });
        $router->add('GET', '/api/purchases/{id}', function (array $params): void {
            (new \Modules\Vendor\Controller\PurchaseController())->show($params['id']);
        });
        $router->add('POST', '/api/purchases/{id}/pay', function (array $params): void {
            AuthMiddleware::authenticate(900);
            (new \Modules\Vendor\Controller\PurchaseController())->recordPayment($params['id']);
        });
        $router->add('PUT', '/api/purchases/{id}', function (array $params): void {
            AuthMiddleware::authenticate(900);
            (new \Modules\Vendor\Controller\PurchaseController())->update($params['id']);
        });
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

        // ── Cart Calculation ──────────────────────────────────────────
        $router->add('POST', '/api/billing/calculate', function (): void {
            AuthMiddleware::authenticate();
            (new \Modules\Billing\Controller\Api\CartCalculationController())->calculate();
        });

        // ── POS Search ──────────────────────────────────────────────
        $router->add('GET', '/api/pos/search', function (): void {
            AuthMiddleware::authenticate();
            (new \Modules\Billing\Controller\Api\PosSearchController())->search();
        });
        $router->add('POST', '/api/pos/search/flush', function (): void {
            (new \Modules\Billing\Controller\Api\PosSearchController())->flushCache();
        });

        // ── Customers / Credit ─────────────────────────────────────
        $router->add('GET', '/api/customers', function (): void {
            AuthMiddleware::authenticate();
            (new \Modules\Customer\Controller\Api\CustomerController())->index();
        });
        $router->add('GET', '/api/customers/credits', function (): void {
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
        $router->add('POST', '/api/customers/{id}/credit-payments', function (array $params): void {
            AuthMiddleware::authenticate(900);
            (new \Modules\Customer\Controller\Api\CustomerController())->pay($params['id']);
        });
        $router->add('GET', '/api/customers/{id}/ledger', function (array $params): void {
            AuthMiddleware::authenticate();
            (new \Modules\Customer\Controller\Api\CustomerController())->ledger($params['id']);
        });

        // ── Credit Sale / Return / Limit ────────────────────────────
        $router->add('POST', '/api/customers/{id}/credit-sale', function (array $params): void {
            AuthMiddleware::authenticate(900);
            (new \Modules\Customer\Controller\Api\CreditController())->recordCreditSale($params['id']);
        });
        $router->add('POST', '/api/customers/{id}/credit-return', function (array $params): void {
            AuthMiddleware::authenticate(900);
            (new \Modules\Customer\Controller\Api\CreditController())->recordReturn($params['id']);
        });
        $router->add('GET', '/api/customers/{id}/credit-check', function (array $params): void {
            AuthMiddleware::authenticate();
            (new \Modules\Customer\Controller\Api\CreditController())->checkCreditLimit($params['id']);
        });

        // ── Ledger Endpoints ─────────────────────────────────────────
        $router->add('GET', '/api/customers/{id}/ledger/entries', function (array $params): void {
            AuthMiddleware::authenticate();
            (new \Modules\Customer\Controller\Api\LedgerController())->entries($params['id']);
        });
        $router->add('GET', '/api/customers/{id}/ledger/summary', function (array $params): void {
            AuthMiddleware::authenticate();
            (new \Modules\Customer\Controller\Api\LedgerController())->summary($params['id']);
        });
        $router->add('GET', '/api/customers/{id}/ledger/balance', function (array $params): void {
            AuthMiddleware::authenticate();
            (new \Modules\Customer\Controller\Api\LedgerController())->balance($params['id']);
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

        // ── Product History ─────────────────────────────────────────
        $router->add('GET', '/api/products/with-stock', function (): void {
            AuthMiddleware::authenticate();
            (new \Modules\Reports\Controller\Api\ProductHistoryController())->productsWithStock();
        });
        $router->add('GET', '/api/products/{id}/history', function (array $params): void {
            AuthMiddleware::authenticate();
            (new \Modules\Reports\Controller\Api\ProductHistoryController())->show($params['id']);
        });
        $router->add('GET', '/api/products/{id}/daily-sales', function (array $params): void {
            AuthMiddleware::authenticate();
            (new \Modules\Reports\Controller\Api\ProductHistoryController())->dailySales($params['id']);
        });
        $router->add('POST', '/api/products/{id}/daily-sales', function (array $params): void {
            AuthMiddleware::authenticate(900);
            (new \Modules\Reports\Controller\Api\ProductHistoryController())->storeDailySale($params['id']);
        });
        $router->add('DELETE', '/api/products/daily-sales/{saleId}', function (array $params): void {
            AuthMiddleware::authenticate(900);
            (new \Modules\Reports\Controller\Api\ProductHistoryController())->destroyDailySale($params['saleId']);
        });

        // ── Backup / Restore ─────────────────────────────────────────
        $router->add('GET', '/api/backup/config', function (): void {
            (new BackupConfigController())->get();
        });
        $router->add('PUT', '/api/backup/config', function (): void {
            (new BackupConfigController())->update();
        });
        $router->add('POST', '/api/backup/start', function (): void {
            (new BackupController())->start();
        });
        $router->add('POST', '/api/backup/create', function (): void {
            (new BackupController())->start();
        });
        $router->add('GET', '/api/backup/status/{id}', function (array $params): void {
            (new BackupController())->status($params['id']);
        });
        $router->add('GET', '/api/backup/status', function (): void {
            (new BackupController())->currentStatus();
        });
        $router->add('GET', '/api/backup/files', function (): void {
            (new BackupController())->files();
        });
        $router->add('GET', '/api/backup/list', function (): void {
            (new BackupController())->files();
        });
        $router->add('POST', '/api/backup/restore', function (): void {
            (new BackupController())->restore();
        });
        $router->add('GET', '/api/backup/auth-url', function (): void {
            (new BackupConfigController())->authUrl();
        });
        $router->add('POST', '/api/backup/auth-code', function (): void {
            (new BackupConfigController())->exchangeCode();
        });

        // ── Health / Monitoring ─────────────────────────────────────────
        $router->add('GET', '/health/live', function (): void {
            (new \Modules\Health\Controller\HealthController())->live();
        });
        $router->add('GET', '/health/ready', function (): void {
            (new \Modules\Health\Controller\HealthController())->ready();
        });
        $router->add('GET', '/health', function (): void {
            (new \Modules\Health\Controller\HealthController())->health();
        });
        $router->add('GET', '/api/health', function (): void {
            (new \Modules\Health\Controller\HealthController())->health();
        });
        $router->add('GET', '/api/telemetry', function (): void {
            (new \Modules\Health\Controller\HealthController())->telemetry();
        });
        $router->add('GET', '/api/telemetry/history', function (): void {
            (new \Modules\Health\Controller\HealthController())->telemetryHistory();
        });
        $router->add('GET', '/metrics', function (): void {
            (new \Modules\Health\Controller\HealthController())->metrics();
        });
    }
}
