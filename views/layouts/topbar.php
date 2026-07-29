    <header class="top-bar">
      <div class="topbar-left" style="display:flex; align-items:center; gap: 20px;">
        <div class="logo-area" style="margin:0; display:flex; align-items:center; gap:10px;">
          <label for="sidebarToggle" class="mobile-hamburger-btn" title="Toggle Navigation Menu">☰</label>
          <div class="logo-icon" style="background: white; border-radius: 50%; overflow: hidden; display: flex; align-items: center; justify-content: center; padding: 2px; width: 28px; height: 28px; flex-shrink:0;">
            <img src="/public/assets/images/logo.png" alt="Logo" style="width: 20px; height: 20px; object-fit: contain;">
          </div>
          <div class="logo-text" style="font-weight: 700; font-size: 1rem; color: var(--text-strong);">Pudheera Retail <span style="font-size: 0.75rem; color:var(--muted); font-weight: 400; font-family:var(--font-body); background: var(--surface-container-low); padding: 2px 6px; border-radius: 4px; margin-left: 4px;">v1.0</span></div>
        </div>

        <?php
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $breadcrumbs = [
          ['name' => 'Home', 'url' => '/dashboard', 'active' => false]
        ];

        switch ($uri) {
          case '/dashboard':
            $breadcrumbs[0]['active'] = true;
            break;
          case '/billing':
            $breadcrumbs[] = ['name' => 'Operations & Finance', 'url' => '#', 'active' => false];
            $breadcrumbs[] = ['name' => 'Billing (POS)', 'url' => '/billing', 'active' => true];
            break;
          case '/customers':
            $breadcrumbs[] = ['name' => 'Operations & Finance', 'url' => '#', 'active' => false];
            $breadcrumbs[] = ['name' => 'Credit (Kadan)', 'url' => '/customers', 'active' => true];
            break;
          case '/customer-bills':
            $custName = trim($_GET['name'] ?? '');
            $crumbTitle = !empty($custName) ? htmlspecialchars($custName) . ' Billing History' : 'Billing History';
            $breadcrumbs[] = ['name' => 'Operations & Finance', 'url' => '#', 'active' => false];
            $breadcrumbs[] = ['name' => 'Credit (Kadan)', 'url' => '/customers', 'active' => false];
            $breadcrumbs[] = ['name' => $crumbTitle, 'url' => $_SERVER['REQUEST_URI'], 'active' => true];
            break;
          case '/daily-sales':
            $breadcrumbs[] = ['name' => 'Operations & Finance', 'url' => '#', 'active' => false];
            $breadcrumbs[] = ['name' => 'Day to Day Selling', 'url' => '/daily-sales', 'active' => true];
            break;
          case '/products':
            $breadcrumbs[] = ['name' => 'Goods & Supply', 'url' => '#', 'active' => false];
            $breadcrumbs[] = ['name' => 'Product Master', 'url' => '/products', 'active' => true];
            break;
          case '/inventory':
            $breadcrumbs[] = ['name' => 'Goods & Supply', 'url' => '#', 'active' => false];
            $breadcrumbs[] = ['name' => 'Inventory', 'url' => '/inventory', 'active' => true];
            break;
          case '/vendors':
            $breadcrumbs[] = ['name' => 'Goods & Supply', 'url' => '#', 'active' => false];
            $breadcrumbs[] = ['name' => 'Vendor List', 'url' => '/vendors', 'active' => true];
            break;
          case '/vendors/history':
            $breadcrumbs[] = ['name' => 'Goods & Supply', 'url' => '#', 'active' => false];
            $breadcrumbs[] = ['name' => 'Vendor History', 'url' => '/vendors/history', 'active' => true];
            break;
          case '/system/health':
            $breadcrumbs[] = ['name' => 'System', 'url' => '#', 'active' => false];
            $breadcrumbs[] = ['name' => 'System Health', 'url' => '/system/health', 'active' => true];
            break;
          case '/backup':
          case '/settings/backup':
            $breadcrumbs[] = ['name' => 'System', 'url' => '#', 'active' => false];
            $breadcrumbs[] = ['name' => 'Backup & Restore', 'url' => '/backup', 'active' => true];
            break;
          case '/products/history':
            $breadcrumbs[] = ['name' => 'Insights', 'url' => '#', 'active' => false];
            $breadcrumbs[] = ['name' => 'Product History', 'url' => '/products/history', 'active' => true];
            break;
          default:
            $breadcrumbs[] = ['name' => 'Page', 'url' => $uri, 'active' => true];
            break;
        }
        ?>

        <!-- Bootstrap 5.3 Breadcrumb Navigation -->
        <nav aria-label="breadcrumb" class="topbar-breadcrumb-nav">
          <ol class="breadcrumb">
            <?php foreach ($breadcrumbs as $index => $crumb): ?>
              <?php if ($crumb['active']): ?>
                <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($crumb['name']); ?></li>
              <?php else: ?>
                <li class="breadcrumb-item"><a href="<?= htmlspecialchars($crumb['url']); ?>"><?= htmlspecialchars($crumb['name']); ?></a></li>
              <?php endif; ?>
            <?php endforeach; ?>
          </ol>
        </nav>
      </div>


      <div class="user-menu" style="display:flex; align-items:center; gap: 12px;">
        <!-- Topbar Theme Switcher Pill -->
        <div class="topbar-theme-pill">
          <button class="topbar-theme-btn" data-theme="light" title="Switch to Light Mode">
            <span>☀️</span> Light
          </button>
          <button class="topbar-theme-btn active" data-theme="dark" title="Switch to Dark Mode">
            <span>🌙</span> Dark
          </button>
        </div>

        <!-- 🔔 Notification Icon with Badge -->
        <div class="topbar-alert-icon" id="topbarAlertIcon" onclick="openActiveAlertsModal()" title="Alerts & Notifications">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
          </svg>
          <span id="topbarAlertBadge" class="topbar-alert-badge">3</span>
        </div>

        <!-- User Avatar -->
        <div class="avatar" title="Admin Account">A</div>

        <!-- Logout Button -->
        <a href="javascript:void(0)" onclick="logoutUser()" class="btn btn-outline btn-sm" style="border-color: var(--border-strong); padding: 6px 14px; font-weight: 500;">Logout</a>
      </div>
    </header>
