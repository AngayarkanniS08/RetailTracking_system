<!DOCTYPE html>
<html lang="en" data-theme-mode="dark">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pudheera Retail Tracking System</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;700&family=Libre+Caslon+Text:ital,wght@0,400;0,700;1,400&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/public/assets/css/style.css?v=<?= time(); ?>">
  <script>
    // Inline script for flash-free theme detection & immediate global helpers
    (function() {
      var saved = localStorage.getItem('theme') || 'dark';
      document.documentElement.setAttribute('data-theme-mode', saved);

      window.switchTab = window.switchTab || function(sectionId) {
        document.querySelectorAll('.view-section').forEach(function(el) {
          el.classList.remove('active');
        });
        var target = document.getElementById(sectionId);
        if (target) {
          target.classList.add('active');
          window.location.hash = sectionId;
        }
        document.querySelectorAll('.nav-item[data-section]').forEach(function(nav) {
          nav.classList.toggle('active', nav.getAttribute('data-section') === sectionId);
        });
      };

      window.openModal = window.openModal || function(id) {
        var el = document.getElementById(id);
        if (el) { el.classList.add('active'); document.body.style.overflow = 'hidden'; }
      };

      window.closeModal = window.closeModal || function(id) {
        var el = document.getElementById(id);
        if (el) { el.classList.remove('active'); document.body.style.overflow = ''; }
      };

      window.logoutUser = window.logoutUser || function() {
        document.cookie = "auth_uid=; path=/; max-age=0;";
        localStorage.removeItem('auth_token');
        localStorage.removeItem('auth_user');
        window.location.href = '/login';
      };

      // Vendor fallback stubs to prevent inline onclick ReferenceError before ES module load
      window.searchVendorHistory = window.searchVendorHistory || function() {};
      window.clearVendorHistorySearch = window.clearVendorHistorySearch || function() {};
      window.loadProductsForVendor = window.loadProductsForVendor || function() {};
      window.loadPurchases = window.loadPurchases || function() {};
      window.switchHistoryTab = window.switchHistoryTab || function() {};
    })();
  </script>
</head>

<body>

  <!-- THEME SWITCHER -->
  <div class="theme-panel">
    <button class="theme-btn active" data-theme="dark" title="Dark Mode">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
      </svg>
    </button>
    <button class="theme-btn" data-theme="light" title="Light Mode">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="5"></circle>
        <line x1="12" y1="1" x2="12" y2="3"></line>
        <line x1="12" y1="21" x2="12" y2="23"></line>
        <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
        <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
        <line x1="1" y1="12" x2="3" y2="12"></line>
        <line x1="21" y1="12" x2="23" y2="12"></line>
        <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
        <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
      </svg>
    </button>
  </div>
