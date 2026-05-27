<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ============================================
// 1. Load Composer autoloader (for JWT, etc.)
// ============================================
require_once __DIR__ . '/vendor/autoload.php';

// ============================================
// 2. Custom autoloader for Modules (must be before using any Module class)
// ============================================
// Autoloader for Modules\ namespace
spl_autoload_register(function ($class) {
    $prefix = 'Modules\\';
    $base_dir = __DIR__ . '/Modules/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

// Autoloader for Core\ namespace
spl_autoload_register(function ($class) {
    $prefix = 'Core\\';
    $base_dir = __DIR__ . '/Core/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

// ============================================
// 3. Session configuration (must be before session_start)
// ============================================
session_save_path(__DIR__ . '/tmp/sessions');
ini_set('session.cookie_lifetime', 28800);
ini_set('session.gc_maxlifetime', 28800);
session_start();
// Set session cookie lifetime to 8 hours (28800 seconds)


// ============================================
// 4. Load database config (defines getDB() function)
// ============================================
require_once __DIR__ . '/config/Database.php';

// ============================================
// 5. Handle API requests (stateless, JSON)
// ============================================
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);

if (strpos($path, '/api/') === 0) {
    // Forgot password (make sure the path matches your frontend fetch URL)
    if ($path === '/api/forgot-password' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $controller = new Modules\Auth\Controller\Api\ForgotPasswordController();
        $controller->forgot();
        exit;
    }
    // Reset password
    if ($path === '/api/reset-password' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $controller = new Modules\Auth\Controller\Api\ResetPasswordController();
        $controller->reset();   // <-- YOU MISSED THIS CALL
        exit;
    }
    // Login
    if ($path === '/api/login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $controller = new Modules\Auth\Controller\Api\LoginController();
        $controller->login();
        exit;
    }

    // ---- Categories ----
    if ($path === '/api/categories' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $controller = new Modules\Product\Controller\Api\CategoryController();
        $controller->index();
        exit;
    }
    if ($path === '/api/categories' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $controller = new Modules\Product\Controller\Api\CategoryController();
        $controller->store();
        exit;
    }
    if (preg_match('#^/api/categories/([^/]+)$#', $path, $m) && $_SERVER['REQUEST_METHOD'] === 'PUT') {
        $controller = new Modules\Product\Controller\Api\CategoryController();
        $controller->update($m[1]);
        exit;
    }
    if (preg_match('#^/api/categories/([^/]+)$#', $path, $m) && $_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $controller = new Modules\Product\Controller\Api\CategoryController();
        $controller->destroy($m[1]);
        exit;
    }

    // ---- Subcategories ----
    if ($path === '/api/subcategories' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $controller = new Modules\Product\Controller\Api\SubcategoryController();
        $controller->index();
        exit;
    }
    if ($path === '/api/subcategories' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $controller = new Modules\Product\Controller\Api\SubcategoryController();
        $controller->store();
        exit;
    }
    if (preg_match('#^/api/subcategories/([^/]+)$#', $path, $m) && $_SERVER['REQUEST_METHOD'] === 'PUT') {
        $controller = new Modules\Product\Controller\Api\SubcategoryController();
        $controller->update($m[1]);
        exit;
    }
    if (preg_match('#^/api/subcategories/([^/]+)$#', $path, $m) && $_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $controller = new Modules\Product\Controller\Api\SubcategoryController();
        $controller->destroy($m[1]);
        exit;
    }

    // ---- Products ----
    if ($path === '/api/products' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $controller = new Modules\Product\Controller\Api\ProductController();
        $controller->index();
        exit;
    }
    if ($path === '/api/products' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $controller = new Modules\Product\Controller\Api\ProductController();
        $controller->store();
        exit;
    }
    if (preg_match('#^/api/products/([^/]+)$#', $path, $m) && $_SERVER['REQUEST_METHOD'] === 'PUT') {
        $controller = new Modules\Product\Controller\Api\ProductController();
        $controller->update($m[1]);
        exit;
    }
    if (preg_match('#^/api/products/([^/]+)$#', $path, $m) && $_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $controller = new Modules\Product\Controller\Api\ProductController();
        $controller->destroy($m[1]);
        exit;
    }

    // ---- Units (read-only, static list) ----
    if ($path === '/api/units' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $controller = new Modules\Product\Controller\Api\UnitController();
        $controller->index();
        exit;
    }
    // Update product (PUT)
    if (preg_match('/^\/api\/products\/([a-f0-9\-]+)$/', $path, $matches) && $_SERVER['REQUEST_METHOD'] === 'PUT') {
        $productId = $matches[1];
        $controller = new Modules\Product\Controller\Api\ProductController();
        $controller->update($productId);
        exit;
    }

    // Any other API endpoint -> 404
    http_response_code(404);
    echo json_encode(['error' => 'API endpoint not found']);
    exit;
}
// Web routes
$action = $_GET['action'] ?? 'register';
if ($action === 'ForgotPassword') {
    require_once 'views/auth/ForgotPassword.php'; // your forgot password form
    exit;
}
if ($action === 'reset_password') {
    $controller = new Modules\Auth\Controller\Web\ResetPasswordController();
    $controller->showResetForm();
    exit;
}

// ============================================
// 6. Web request handling (session-based)
// ============================================

// Logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: index.php');
    exit;
}

// POST Registration (web form)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'register') {
    $controller = new Modules\Auth\Controller\Api\RegistrationController();
    $controller->register();
    exit;
}

// POST Login (web form)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'login') {
    // Use the existing API login controller for web login if a dedicated web controller is not available.
    $controller = new Modules\Auth\Controller\Web\LoginController();
    $controller->login();
    exit;
}

// ============================================
// 7. Determine if user is logged in
// ============================================
$isLoggedIn = isset($_SESSION['user_id']);

if (!$isLoggedIn) {
    // Show auth forms (no dashboard layout)
    require_once 'views/layouts/header.php';
    $action = $_GET['action'] ?? 'register';
    if ($action === 'login') {
        require_once 'views/auth/login.php';
    } elseif ($action === 'forgot_password') {
        require_once 'views/auth/ForgotPassword.php';
    } elseif ($action === 'reset_password') {
        require_once 'views/auth/ResetPassword.php';
    } else {
        require_once 'views/auth/register.php';
    }
    require_once 'views/layouts/footer.php';
    exit;
}

// ============================================
// 8. Logged in – show dashboard
// ============================================
require_once 'views/layouts/header.php';
echo '<div class="dashboard" id="dashboardView">';
require_once 'views/layouts/topbar.php';
echo '<div class="main-container">';
require_once 'views/layouts/sidebar.php';
echo '<main class="content-area">';

// Feature sections
require_once 'views/reports/dashboard.php';
require_once 'views/billing/index.php';
require_once 'views/customer/index.php';
require_once 'views/reports/daily_sales.php';
require_once 'views/product/index.php';
require_once 'views/inventory/index.php';
require_once 'views/vendor/index.php';
require_once 'views/vendor/history.php';
require_once 'views/reports/stockintel.php';

echo '</main>';
echo '</div>';
echo '</div>';

require_once 'views/layouts/modals.php';
require_once 'views/layouts/footer.php';
