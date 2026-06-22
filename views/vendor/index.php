<!-- 
  FEATURE DOCUMENTATION: Vendor List
  - Supply Chain Management: Tracks supplier relationships and history.
  - Vendor Metrics: Balances cards and transaction history per vendor.
  - Purchasing: "+ New Purchase" entry form to log incoming goods and update system batches.
-->
        <section id="vendor_list" class="view-section">
          <div class="card-header">
            <span>Vendor List</span>
            <div class="d-flex" style="gap: 10px;">
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

          <div class="card-panel">
            <div class="data-table">
              <table id="vendorPurchaseTable">
                <thead>
                  <tr>
                    <th>Vendor Name</th>
                    <th>Contact</th>
                    <th>Orders</th>
                    <th>Total (₹)</th>
                    <th>Paid (₹)</th>
                    <th>Balance (₹)</th>
                    <th>Action</th>
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
