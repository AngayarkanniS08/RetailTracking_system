<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

require_once __DIR__ . '/config/Database.php';

// Simple autoloader supporting root-level and models/ subdirectory modules
spl_autoload_register(function ($class) {
    $base_dir = __DIR__ . '/';
    
    // Check if the class namespace belongs to the moved modules
    $parts = explode('\\', $class);
    $firstWord = strtolower($parts[0] ?? '');
    
    if (in_array($firstWord, ['auth', 'billing', 'customer', 'inventory', 'product', 'reports', 'settings'])) {
        $file = $base_dir . 'models/' . str_replace('\\', '/', $class) . '.php';
    } else {
        $file = $base_dir . str_replace('\\', '/', $class) . '.php';
    }
    
    if (file_exists($file)) {
        require_once $file;
    }
});

// Handle logout (always first)
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Handle POST registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'register') {
    require_once __DIR__ . '/models/Auth/Controller/Api/RegistrationController.php';
    $controller = new Auth\Controller\Api\RegistrationController();
    $controller->register();
    exit;
}

// Handle POST login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'login') {
    require_once __DIR__ . '/models/Auth/Controller/Api/LoginController.php';
    $controller = new Auth\Controller\Api\LoginController();
    $controller->login();
    exit;
}

// Check if user is logged in (using the session key set by registration or login)
$isLoggedIn = isset($_SESSION['user_id']);

// If not logged in, show only the auth forms (no layout)
if (!$isLoggedIn) {
    require_once 'views/layouts/header.php';
    
    $action = $_GET['action'] ?? 'register';
    if ($action === 'login') {
        require_once 'views/auth/login.php';
    } else {
        require_once 'views/auth/register.php';
    }
    
    require_once 'views/layouts/footer.php';
    exit; // Stop execution – no dashboard or layout
}

// If logged in, show full dashboard with layout
require_once 'views/layouts/header.php';

echo '<div class="dashboard" id="dashboardView">';

require_once 'views/layouts/topbar.php';

echo '<div class="main-container">';

require_once 'views/layouts/sidebar.php';

echo '<main class="content-area">';

// All consolidated feature sections (used for SPA tab switching)
require_once 'views/reports/dashboard.php';
require_once 'views/billing/index.php';
require_once 'views/customer/index.php';
require_once 'views/reports/daily_sales.php';
require_once 'views/product/index.php';
require_once 'views/inventory/index.php';
require_once 'views/vendor/index.php';
require_once 'views/vendor/history.php';
require_once 'views/reports/stockintel.php';

echo '</main>'; // content-area
echo '</div>'; // main-container
echo '</div>'; // dashboardView

require_once 'views/layouts/modals.php';
require_once 'views/layouts/footer.php';