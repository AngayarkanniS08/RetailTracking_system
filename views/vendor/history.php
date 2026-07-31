<?php
// Optional per-vendor scope: vendor_id from URL
$vendorId = $_GET['vendor_id'] ?? '';
$vendorName = '';
if (!empty($vendorId)) {
    try {
        $pdo = \Config\Database::getConnection();
        $stmt = $pdo->prepare("SELECT name FROM vendors WHERE id = ? AND user_id = current_setting('app.current_user_id', true)::uuid");
        $stmt->execute([$vendorId]);
        $vendor = $stmt->fetch(PDO::FETCH_ASSOC);
        $vendorName = $vendor['name'] ?? '';
    } catch (Exception $e) {
        $vendorName = '';
    }
}
?>

<section id="vendor_history_summary" class="view-section active">

  <!-- Page Header Row: Title on Left, Search Input on Right -->
  <div class="page-header-row" style="display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 24px;">
    <div>
      <h1 style="font-size: 1.4rem; font-weight: 700; color: var(--text-strong); margin: 0; letter-spacing: -0.02em;">
        Vendor History<?= $vendorName ? ' — ' . htmlspecialchars($vendorName) : '' ?>
      </h1>
      <div style="font-size: 0.82rem; color: var(--muted); margin-top: 4px;">Daily vendor purchase summary — click a date to view all transactions that day.</div>
    </div>

    <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
      <div class="search-input-wrapper" style="position: relative; width: 300px;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--muted); pointer-events: none;">
          <circle cx="11" cy="11" r="8"></circle>
          <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
        <input type="text" id="vendorHistorySearch" class="input-field" placeholder="Search by vendor name or phone..."
               oninput="onVendorHistorySearchInput()" style="padding-left: 36px; height: 38px; font-size: 0.85rem; border-radius: var(--radius-md);" />
      </div>
    </div>
  </div>

  <!-- KPI Summary Cards Grid -->
  <div class="kpi-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
    <div class="kpi-card" style="background: var(--card); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 18px 20px; box-shadow: var(--shadow-xs);">
      <div style="font-size: 0.75rem; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px;">TOTAL PURCHASES</div>
      <div id="vhkTotalPurchases" style="font-size: 1.5rem; font-weight: 700; color: var(--text-strong);">0</div>
      <div style="font-size: 0.75rem; color: var(--muted); margin-top: 4px;">All-time purchase entries</div>
    </div>

    <div class="kpi-card" style="background: var(--card); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 18px 20px; box-shadow: var(--shadow-xs);">
      <div style="font-size: 0.75rem; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px;">TOTAL BILLED</div>
      <div id="vhkTotalBilled" style="font-size: 1.5rem; font-weight: 700; color: var(--text-strong);">₹0.00</div>
      <div style="font-size: 0.75rem; color: var(--muted); margin-top: 4px;">Purchase value including GST</div>
    </div>

    <div class="kpi-card" style="background: var(--card); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 18px 20px; box-shadow: var(--shadow-xs);">
      <div style="font-size: 0.75rem; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px;">TOTAL PAID</div>
      <div id="vhkTotalPaid" style="font-size: 1.5rem; font-weight: 700; color: var(--success);">₹0.00</div>
      <div style="font-size: 0.75rem; color: var(--muted); margin-top: 4px;">Settled against vendors</div>
    </div>

    <div class="kpi-card" style="background: var(--card); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 18px 20px; box-shadow: var(--shadow-xs);">
      <div style="font-size: 0.75rem; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px;">BALANCE DUE</div>
      <div id="vhkBalanceDue" style="font-size: 1.5rem; font-weight: 700; color: var(--warning, #d97706);">₹0.00</div>
      <div style="font-size: 0.75rem; color: var(--muted); margin-top: 4px;">Outstanding to vendors</div>
    </div>
  </div>

  <!-- Data Panel Card -->
  <div class="card-panel" style="background: var(--card); border: 1px solid var(--border); border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow-sm); padding: 0;">

    <div class="table-container" style="overflow-x: auto;">
      <table id="vendorHistorySummaryTable" class="data-table" style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
        <thead>
          <tr style="background: var(--surface-container-low); border-bottom: 1px solid var(--border); text-align: left;">
            <th style="padding: 14px 16px; font-weight: 700; color: var(--text-muted); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em;">DATE</th>
            <th style="padding: 14px 16px; font-weight: 700; color: var(--text-muted); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em;">PURCHASES</th>
            <th style="padding: 14px 16px; font-weight: 700; color: var(--text-muted); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em;">TOTAL BILLED</th>
            <th style="padding: 14px 16px; font-weight: 700; color: var(--text-muted); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em;">PAID</th>
            <th style="padding: 14px 16px; font-weight: 700; color: var(--text-muted); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em;">BALANCE DUE</th>
          </tr>
        </thead>
        <tbody>
          <!-- Rendered dynamically via vendor_history.js -->
        </tbody>
      </table>
    </div>

    <!-- Empty State Container -->
    <div id="vendorHistoryEmptyState" class="empty-state-card" style="padding: 64px 24px; text-align: center; display: none; flex-direction: column; align-items: center; justify-content: center;">
      <div class="empty-icon-circle" style="width: 56px; height: 56px; border-radius: 50%; background: var(--surface-container-low); display: flex; align-items: center; justify-content: center; margin-bottom: 16px; border: 1px solid var(--border);">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="color: var(--muted);">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
          <polyline points="14 2 14 8 20 8"></polyline>
          <line x1="16" y1="13" x2="8" y2="13"></line>
          <line x1="16" y1="17" x2="8" y2="17"></line>
          <polyline points="10 9 9 9 8 9"></polyline>
        </svg>
      </div>
      <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--text-strong); margin: 0 0 6px 0;">No vendor history records found</h3>
      <p style="font-size: 0.88rem; color: var(--muted); margin: 0 0 20px 0; max-width: 380px; line-height: 1.4;">Vendor purchase entries recorded from the Vendor List will appear here grouped by day.</p>
      <a href="/vendors" class="btn btn-outline btn-sm" style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; font-size: 0.85rem; font-weight: 600; border-radius: var(--radius-md); text-decoration: none;">
        Go to Vendor List
      </a>
    </div>

  </div>
</section>
