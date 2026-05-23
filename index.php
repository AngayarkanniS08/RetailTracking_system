<?php
session_start();

// Simple mock login check
$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;

// Handle login request (for demo purposes)
if (isset($_GET['action']) && $_GET['action'] === 'login') {
    $_SESSION['logged_in'] = true;
    header('Location: index.php');
    exit;
}

// Handle logout request
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Include layout header
require_once 'Core/View/Layouts/header.php';

if ($isLoggedIn) {
    // Show Dashboard Shell
    echo '<div class="dashboard" id="dashboardView">';
    
    // Top Bar
    require_once 'Core/View/Layouts/topbar.php';
    
    echo '<div class="main-container">';
    
    // Sidebar
    require_once 'Core/View/Layouts/sidebar.php';
    
    echo '<main class="content-area">';
    
    // All Feature Sections (for SPA tab switching)
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
    
    // Modals
    require_once 'Core/View/Layouts/modals.php';
}
else {
    // Not logged in – show register by default, allow switch to login
    $action = $_GET['action'] ?? 'register';
    if ($action === 'login') {
        require_once 'Auth/View/login.php';
    } else {
        require_once 'Auth/View/register.php';
    }
}

// Include layout footer
require_once 'Core/View/Layouts/footer.php';
?>
