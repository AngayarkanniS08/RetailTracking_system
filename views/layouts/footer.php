  <!-- Backup status notification (hidden by default) -->
  <div id="backup-status-overlay" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;z-index:9999;background:rgba(0,0,0,0.3);pointer-events:none;">
    <div style="position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff3cd;border:2px solid #ffc107;border-radius:12px;padding:24px 32px;box-shadow:0 8px 32px rgba(0,0,0,0.2);text-align:center;min-width:320px;pointer-events:auto;">
      <div style="font-size:18px;font-weight:600;color:#856404;margin-bottom:8px;">Scheduled Backup in Progress</div>
      <div id="backup-status-msg" style="font-size:14px;color:#856404;"></div>
    </div>
  </div>

  <!-- Footer Scripts -->
  <script src="public/assets/js/Sidebar.js?v=<?= time(); ?>"></script>
  <script src="public/assets/js/utils.js?v=<?= time(); ?>"></script>
  <script src="public/assets/js/billing.js?v=<?= time(); ?>"></script>
  <script src="public/assets/js/ProductMaster.js?v=<?= time(); ?>"></script>
  <script src="public/assets/js/inventory.js?v=<?= time(); ?>"></script>
  <script src="public/assets/js/Vendor.js?v=<?= time(); ?>"></script>
  <script src="public/assets/js/credit.js?v=<?= time(); ?>"></script>
  <script src="public/assets/js/daily_sales.js?v=<?= time(); ?>"></script>
  <script src="public/assets/js/dashboard.js?v=<?= time(); ?>"></script>
  <script src="public/assets/js/product-history.js?v=<?= time(); ?>"></script>
  <script src="public/assets/js/backup-status.js?v=<?= time(); ?>"></script>
  <link rel="stylesheet" href="public/assets/css/theme.css?v=<?= time(); ?>">
</body>
</html>
