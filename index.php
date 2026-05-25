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

// ============================================
// 3. Session configuration (must be before session_start)
// ============================================
session_save_path(__DIR__ . '/tmp/sessions');
session_start();

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
    // API endpoint routing
    if ($path === '/api/login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $controller = new Modules\Auth\Controller\Api\LoginController();
        $controller->login();
        exit;
    }
    // Add other API routes here

    http_response_code(404);
    echo json_encode(['error' => 'API endpoint not found']);
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
        require_once 'views/auth/forgot_password.php';
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
