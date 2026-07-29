<?php
// Master Layout Wrapper for Logged-In Enterprise MPA Pages
require_once __DIR__ . '/header.php';
?>
<!-- Hidden Checkbox for Pure CSS Mobile Sidebar Drawer Toggle -->
<input type="checkbox" id="sidebarToggle" style="display:none;" />
<div class="dashboard" id="dashboardView">
  <?php require_once __DIR__ . '/topbar.php'; ?>

  <!-- Global Low Stock Alert Banner -->
  <div id="globalLowStockBanner" class="global-low-stock-banner" style="display:none; padding: 10px 20px; background: rgba(220, 38, 38, 0.08); border-bottom: 1px solid rgba(220, 38, 38, 0.15); font-size: 0.85rem; align-items: center; justify-content: space-between; width: 100%;">
      <div style="display:flex; align-items:center; gap:8px; flex: 1; min-width: 0;">
          <span style="font-size:1.1rem;">🚨</span>
          <span id="globalLowStockBannerMessage" style="font-weight: 500; color: var(--danger); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Some products are below their reorder threshold!</span>
      </div>
      <div style="display:flex; align-items:center; gap:12px; margin-left: 10px;">
          <button class="btn btn-sm" style="padding: 2px 8px; font-size: 0.72rem; color: var(--danger); border-color: var(--danger); background: transparent;" onclick="openActiveAlertsModal()">Manage Alerts</button>
          <button onclick="closeGlobalLowStockBanner()" style="background:none; border:none; color:var(--danger); font-size:1.2rem; cursor:pointer; padding:0 4px; line-height:1; display:flex; align-items:center; opacity:0.8;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.8'">&times;</button>
      </div>
  </div>

  <div class="main-container">
    <?php require_once __DIR__ . '/sidebar.php'; ?>
    
    <main class="content-area" id="mainContentArea">
      <div class="page-content-wrapper">
        <?= $pageContent ?? '' ?>
      </div>
    </main>
  </div>
</div>

<?php
require_once __DIR__ . '/modals.php';
require_once __DIR__ . '/footer.php';
?>
