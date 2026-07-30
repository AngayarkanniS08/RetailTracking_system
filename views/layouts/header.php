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
  <link rel="stylesheet" href="/public/assets/css/pages/dashboard.css?v=<?= time(); ?>">
  <script>
    // Inline script for flash-free theme detection & immediate global helpers
    (function() {
      var saved = localStorage.getItem('theme') || 'dark';
      document.documentElement.setAttribute('data-theme-mode', saved);
      document.documentElement.setAttribute('data-bs-theme', saved);

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
        if (el) { el.classList.add('active'); }
      };

      window.closeModal = window.closeModal || function(id) {
        var el = document.getElementById(id);
        if (el) { el.classList.remove('active'); }
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


