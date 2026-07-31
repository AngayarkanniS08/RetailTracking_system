<!-- 
  FEATURE DOCUMENTATION: Vendor List
  - Supply Chain Management: Tracks supplier relationships and history.
  - Vendor Metrics: Balances cards and transaction history per vendor.
  - Purchasing: "+ New Purchase" entry form to log incoming goods and update system batches.
-->
        <section id="vendor_list" class="view-section active">
          <div class="card-header">
            <span>Vendor List</span>
            <div class="d-flex">
              <input type="text" id="vendorSearch" class="input-field" placeholder="Search purchases..." style="width: 200px;"
                onkeyup="loadPurchases(1)">
              <button class="btn btn-primary btn-sm" onclick="openModal('addStockEntryModal'); loadProductsForVendor();">+ New Purchase</button>
            </div>
          </div>

          <!-- Summary Cards -->
          <div class="stats-grid" style="margin-bottom: 1.5rem;">
            <div class="stat-card">
              <div class="stat-label">Total Vendors</div>
              <div class="stat-value" id="slTotalVendors">0</div>
            </div>
            <div class="stat-card">
              <div class="stat-label">Total</div>
              <div class="stat-value" id="slTotalAmount">₹0.00</div>
            </div>
            <div class="stat-card">
              <div class="stat-label">Total Paid</div>
              <div class="stat-value" style="color:var(--ok)" id="slTotalPaid">₹0.00</div>
            </div>
            <div class="stat-card">
              <div class="stat-label">Balance Due</div>
              <div class="stat-value" style="color:var(--danger)" id="slTotalBalance">₹0.00</div>
            </div>
          </div>

          <div class="card-panel" style="background: var(--card); border: 1px solid var(--border); border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow-sm); padding: 0;">
            <div class="table-container" style="overflow-x: auto;">
              <table id="vendorPurchaseTable" style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                <thead>
                  <tr style="background: var(--surface-container-low); border-bottom: 1px solid var(--border); text-align: left;">
                    <th style="padding: 14px 16px; font-weight: 700; color: var(--text-muted); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em;">Vendor Name</th>
                    <th style="padding: 14px 16px; font-weight: 700; color: var(--text-muted); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em;">Contact</th>
                    <th style="padding: 14px 16px; font-weight: 700; color: var(--text-muted); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em;">Orders</th>
                    <th style="padding: 14px 16px; font-weight: 700; color: var(--text-muted); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em;">Total (₹)</th>
                    <th style="padding: 14px 16px; font-weight: 700; color: var(--text-muted); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em;">Paid (₹)</th>
                    <th style="padding: 14px 16px; font-weight: 700; color: var(--text-muted); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em;">Balance (₹)</th>
                    <th style="padding: 14px 16px; font-weight: 700; color: var(--text-muted); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em; text-align: right;">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <!-- Rendered via JS -->
                </tbody>
              </table>
            </div>
          </div>


          <div id="purchasePaginationControls" style="display:none;"></div>
        </section>
