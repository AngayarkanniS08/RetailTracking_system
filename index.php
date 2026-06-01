<?php

declare(strict_types=1);

// Local bootstrapping for frontend session and settings
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$sessionPath = __DIR__ . '/tmp/sessions';
if (!is_dir($sessionPath) && !mkdir($sessionPath, 0777, true) && !is_dir($sessionPath)) {
    throw new \RuntimeException("Unable to create session directory: $sessionPath");
}

session_save_path($sessionPath);
ini_set('session.cookie_lifetime', '28800');
ini_set('session.gc_maxlifetime', '28800');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Set the include path to include the root directory so requires resolve views/layouts/...
set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__);

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: index.php');
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'set_session') {
    $input = json_decode(file_get_contents('php://input'), true);
    $_SESSION['user_id'] = $input['user_id'] ?? '';
    $_SESSION['username'] = $input['username'] ?? '';
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}

$action = $_GET['action'] ?? 'register';

// ============================================
// Determine if user is logged in
// ============================================
$isLoggedIn = isset($_SESSION['user_id']);

if (!$isLoggedIn) {
    // Show auth forms (no dashboard layout)
    require_once 'views/layouts/header.php';
    $action = $_GET['action'] ?? 'register';
    if ($action === 'login') {
        require_once 'views/auth/login.php';
    } elseif ($action === 'forgot_password' || $action === 'ForgotPassword') {
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
// Logged in – show dashboard
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
