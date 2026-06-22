<?php
// Get vendor_id from URL
$vendorId = $_GET['vendor_id'] ?? '';

// If no vendor_id, show all vendors (or show an error)
if (empty($vendorId)) {
    // Option 1: Show all vendors
    $pageTitle = 'All Vendors – Purchase History';
    $vendorName = 'All Vendors';
} else {
    // Fetch vendor name from database
    try {
        $pdo = \Config\Database::getConnection();
        $stmt = $pdo->prepare("SELECT name FROM vendors WHERE id = ? AND user_id = current_setting('app.current_user_id')::uuid");
        $stmt->execute([$vendorId]);
        $vendor = $stmt->fetch(PDO::FETCH_ASSOC);
        $vendorName = $vendor['name'] ?? 'Vendor';
        $pageTitle = $vendorName . ' – Purchase History';
    } catch (Exception $e) {
        $vendorName = 'Vendor';
        $pageTitle = 'Vendor Purchase History';
    }
}
?>    
    
    <section id="vendorhistory" class="view-section">
          <div class="card-header">
            <div style="display:flex; align-items:center; gap:12px;">
              <button class="btn btn-outline btn-sm" onclick="switchTab('vendor_list')"
                style="display:flex; align-items:center; gap:6px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                  <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
                Back to Vendor List
              </button>
              <span id="vendorHistoryTitle"
                style="font-size:1.1rem; font-weight:600; color:var(--text-strong);">History</span>
              <span id="vendorHistorySubtitle" style="font-size:0.8rem; color:var(--muted);"></span>
            </div>
            <div style="display:flex; gap:10px; align-items:center;">
              <button class="btn btn-primary btn-sm" id="vendorRestockBtn">+ New Purchase</button>
            </div>
          </div>

          <!-- Product Analytics Cards for this vendor -->
          <div id="vendorProductAnalytics" style="margin-bottom: 1.5rem;">
            <!-- Rendered by JS -->
          </div>

          <!-- Financial Summary mini-cards (purchase mode) -->
          <div class="stats-grid" style="margin-bottom:1.5rem;" id="purchaseStats">
            <div class="stat-card">
              <div class="stat-label">Total</div>
              <div class="stat-value" id="vhTotalBilled">₹0.00</div>
            </div>
            <div class="stat-card">
              <div class="stat-label">Total Paid</div>
              <div class="stat-value" style="color:var(--ok)" id="vhTotalPaid">₹0.00</div>
            </div>
            <div class="stat-card">
              <div class="stat-label">Balance Due</div>
              <div class="stat-value" style="color:var(--danger)" id="vhBalance">₹0.00</div>
            </div>
          </div>

          <!-- Financial Summary mini-cards (payment mode, hidden by default) -->
          <div class="stats-grid" style="margin-bottom:1.5rem; display:none;" id="paymentStats">
            <div class="stat-card">
              <div class="stat-label">Total Payments</div>
              <div class="stat-value" id="vhPaymentTotal">₹0.00</div>
            </div>
            <div class="stat-card">
              <div class="stat-label">Payment Count</div>
              <div class="stat-value" id="vhPaymentCount">0</div>
            </div>
            <div class="stat-card">
              <div class="stat-label">Avg Payment</div>
              <div class="stat-value" id="vhAvgPayment">₹0.00</div>
            </div>
          </div>

          <!-- Toggle buttons -->
          <div style="display:flex; gap:6px; margin-bottom:1rem;">
            <button class="btn btn-sm btn-primary" id="togglePurchasesBtn" onclick="switchHistoryTab('purchases')">Purchase History</button>
            <button class="btn btn-sm btn-outline" id="togglePaymentsBtn" onclick="switchHistoryTab('payments')">Payment History</button>
          </div>

          <div class="card-panel">
            <div id="vendorHistoryBody">
              <!-- Date-grouped accordions rendered by JS -->
            </div>
            <div id="historyPaginationControls" style="display:none;"></div>
          </div>
        </section>
