<?php
declare(strict_types=1);

use Core\Container;
use Core\Request;
use Core\Response;
use Core\Router;
use Core\ExceptionHandler;
use Core\Middleware\AuthMiddleware;
use Controllers\AuthController;
use Controllers\DashboardController;
use Controllers\BillingController;
use Controllers\InventoryController;
use Controllers\ProductController;
use Controllers\VendorController;
use Controllers\CustomerController;
use Controllers\ReportsController;
use Controllers\HealthController;
use Controllers\SettingsController;

// 1. Autoloading
$autoloadPaths = [
    __DIR__ . '/src/vendor/autoload.php',
    __DIR__ . '/vendor/autoload.php',
];
foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}

// Fallback PSR-4 Autoloader for src/ directory
spl_autoload_register(function ($class) {
    $prefix = 'Core\\';
    if (str_starts_with($class, $prefix)) {
        $file = __DIR__ . '/src/Core/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (file_exists($file)) require_once $file;
        return;
    }
    $prefixController = 'Controllers\\';
    if (str_starts_with($class, $prefixController)) {
        $file = __DIR__ . '/src/Controllers/' . str_replace('\\', '/', substr($class, strlen($prefixController))) . '.php';
        if (file_exists($file)) require_once $file;
        return;
    }
});

// Set global exception handler
set_exception_handler([ExceptionHandler::class, 'handle']);

// 2. Initialize Container and Request
$container = Container::getInstance();
$request   = new Request();

// 3. Register Application Routes
$router = new Router();

// Register API Routes
\Core\ApiRoutes::register($router);

// Guest Auth Routes
$router->get('/login',           [AuthController::class, 'showLogin']);
$router->get('/register',        [AuthController::class, 'showRegister']);
$router->get('/forgot-password', [AuthController::class, 'showForgotPassword']);
$router->get('/reset-password',  [AuthController::class, 'showResetPassword']);
$router->get('/logout',          [AuthController::class, 'logout']);

// Root redirect
$router->get('/', function() {
    Response::redirect('/dashboard');
});

// Authenticated MPA Module Routes (Stripe / GitHub Standard)
$router->get('/dashboard',       [DashboardController::class, 'index'], [AuthMiddleware::class]);
$router->get('/billing',         [BillingController::class, 'index'],   [AuthMiddleware::class]);
$router->get('/inventory',       [InventoryController::class, 'index'], [AuthMiddleware::class]);
$router->get('/products',        [ProductController::class, 'index'],   [AuthMiddleware::class]);
$router->get('/products/history', [ProductController::class, 'history'], [AuthMiddleware::class]);
$router->get('/vendors',         [VendorController::class, 'index'],    [AuthMiddleware::class]);
$router->get('/vendors/history', [VendorController::class, 'history'],  [AuthMiddleware::class]);
$router->get('/customers',       [CustomerController::class, 'index'],  [AuthMiddleware::class]);
$router->get('/customer-bills',  [CustomerController::class, 'bills'],  [AuthMiddleware::class]);
$router->get('/reports',         [ReportsController::class, 'index'],    [AuthMiddleware::class]);
$router->get('/daily-sales',     [ReportsController::class, 'daily'],    [AuthMiddleware::class]);
$router->get('/system/health',   [HealthController::class, 'index'],    [AuthMiddleware::class]);
$router->get('/settings/backup', [SettingsController::class, 'backup'],  [AuthMiddleware::class]);
$router->get('/backup',          [SettingsController::class, 'backup'],  [AuthMiddleware::class]);

// 4. Dispatch Request
$router->dispatch($request);
