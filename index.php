<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

require_once __DIR__ . '/config/Database.php';

// Simple autoloader
spl_autoload_register(function ($class) {
    // Convert namespace to file path
    $prefix = '';
    $base_dir = __DIR__ . '/';
    $file = $base_dir . str_replace('\\', '/', $class) . '.php';
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
    require_once __DIR__ . '/Auth/Controller/Api/RegistrationController.php';
    $controller = new Auth\Controller\Api\RegistrationController();
    $controller->register();
    exit;
}

// Handle POST login (will implement later)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'login') {
    // TODO: call LoginController
    // For now, redirect to dashboard if credentials are correct (placeholder)
    // After you implement login, replace this block.
    header('Location: index.php');
    exit;
}

// Check if user is logged in (using the session key set by registration)
$isLoggedIn = isset($_SESSION['user_id']);

// If not logged in, show only the auth forms (no layout)
if (!$isLoggedIn) {
    require_once 'Core/View/Layouts/header.php';
    
    $action = $_GET['action'] ?? 'register';
    if ($action === 'login') {
        require_once 'Auth/View/login.php';
    } else {
        require_once 'Auth/View/register.php';
    }
    
    require_once 'Core/View/Layouts/footer.php';
    exit; // Stop execution – no dashboard or layout
}

// If logged in, show full dashboard with layout
require_once 'Core/View/Layouts/header.php';

echo '<div class="dashboard" id="dashboardView">';

require_once 'Core/View/Layouts/topbar.php';

echo '<div class="main-container">';

require_once 'Core/View/Layouts/sidebar.php';

echo '<main class="content-area">';

// All feature sections (used for SPA tab switching)
require_once 'Reports/View/Dashboard/index.php';
require_once 'Billing/View/index.php';
require_once 'Customer/View/index.php';
require_once 'Reports/View/DailySales/index.php';
require_once 'Product/View/index.php';
require_once 'Inventory/View/index.php';
require_once 'Vendor/View/index.php';
require_once 'Vendor/View/History/index.php';
require_once 'Reports/View/Dashboard/stockintel/index.php';

echo '</main>'; // content-area
echo '</div>'; // main-container
echo '</div>'; // dashboardView

require_once 'Core/View/Layouts/modals.php';
require_once 'Core/View/Layouts/footer.php';