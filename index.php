<?php
declare(strict_types=1);

// Load Composer autoloader (for Core\ classes)
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

$startTime = microtime(true);

// Set the include path to include the root directory so requires resolve views/layouts/...
set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__);

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    header('Location: /login');
    exit;
}

// ============================================
// Route mapping
// ============================================
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$basePath = dirname($_SERVER['SCRIPT_NAME']);
$routePath = $basePath === '/' ? $requestUri : substr($requestUri, strlen($basePath));
$routePath = $routePath ?: '/';

$routeMap = [
    '/'               => null,
    '/login'          => 'login',
    '/register'       => 'register',
    '/dashboard'      => 'dashboard',
    '/billing'        => 'billing_pos',
    '/customers'      => 'credit_kadan',
    '/daily-sales'    => 'day_to_day_selling',
    '/products'       => 'product_master',
    '/inventory'      => 'inventory',
    '/vendors'        => 'vendor_list',
    '/vendor-history' => 'vendorhistory',
    '/stock-intel'    => 'stockintel',
    '/product-history'=> 'product_history',
    '/backup'         => 'backup',
];

$initialSection = $routeMap[$routePath] ?? null;

// ============================================
// Determine if user is logged in
// ============================================
$isLoggedIn = isset($_COOKIE['auth_uid']);

$publicPages = ['backup'];

if (!$isLoggedIn) {
    if ($initialSection && in_array($initialSection, $publicPages)) {
        // Public page — show full SPA layout without requiring session
        require_once 'views/layouts/header.php';
        echo '<div class="dashboard" id="dashboardView">';
        require_once 'views/layouts/topbar.php';
        echo '<div class="main-container">';
        require_once 'views/layouts/sidebar.php';
        echo '<main class="content-area">';
        require_once 'views/settings/backup.php';
        echo '</main>';
        echo '</div>';
        echo '</div>';
        require_once 'views/layouts/modals.php';
        require_once 'views/layouts/footer.php';
        echo "<script>document.addEventListener('DOMContentLoaded', function() { switchTab('{$initialSection}'); });</script>";
        exit;
    }

    // Show auth forms (no dashboard layout)
    require_once 'views/layouts/header.php';
    $action = $initialSection ?? 'login';
    if ($action === 'login') {
        require_once 'views/auth/login.php';
    } elseif ($action === 'forgot_password' || $action === 'ForgotPassword') {
        require_once 'views/auth/ForgotPassword.php';
    } elseif ($action === 'reset_password') {
        require_once 'views/auth/ResetPassword.php';
    } elseif ($action === 'register') {
        require_once 'views/auth/register.php';
    } else {
        require_once 'views/auth/login.php';
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

// Global alert banner for low stock warnings (rendered full-width below topbar)
echo '
<div id="globalLowStockBanner" class="global-low-stock-banner" style="display:none; padding: 10px 20px; background: rgba(220, 38, 38, 0.08); border-bottom: 1px solid rgba(220, 38, 38, 0.15); font-size: 0.85rem; align-items: center; justify-content: space-between; width: 100%;">
    <div style="display:flex; align-items:center; gap:8px; flex: 1; min-width: 0;">
        <span style="font-size:1.1rem;">🚨</span>
        <span id="globalLowStockBannerMessage" style="font-weight: 500; color: var(--danger); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Some products are below their reorder threshold!</span>
    </div>
    <div style="display:flex; align-items:center; gap:12px; margin-left: 10px;">
        <button class="btn btn-sm" style="padding: 2px 8px; font-size: 0.72rem; color: var(--danger); border-color: var(--danger); background: transparent;" onclick="openActiveAlertsModal()">Manage Alerts</button>
        <button onclick="closeGlobalLowStockBanner()" style="background:none; border:none; color:var(--danger); font-size:1.2rem; cursor:pointer; padding:0 4px; line-height:1; display:flex; align-items:center; opacity:0.8;" onmouseover="this.style.opacity=\'1\'" onmouseout="this.style.opacity=\'0.8\'">&times;</button>
    </div>
</div>
';

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
require_once 'views/product/history.php';
require_once 'views/settings/backup.php';

echo '</main>';
echo '</div>';
echo '</div>';

require_once 'views/layouts/modals.php';

$elapsed = number_format((microtime(true) - $startTime) * 1000, 2);
$version = \Core\VersionHelper::getVersion();
echo '<div style="text-align:center;padding:8px 0;font-size:0.75rem;color:var(--muted);border-top:1px solid var(--border);margin-top:1rem;">';
echo "Page Rendered in {$elapsed} ms &middot; {$version}";
echo '</div>';

require_once 'views/layouts/footer.php';

// Set initial section via JS
if ($initialSection && $initialSection !== 'login') {
    echo "<script>document.addEventListener('DOMContentLoaded', function() { switchTab('{$initialSection}'); });</script>";
}
