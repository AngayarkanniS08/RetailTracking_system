        <section id="vendorhistory" class="view-section">
          <div class="card-header">
            <div style="display:flex; align-items:center; gap:12px;">
              <button class="btn btn-outline btn-sm" onclick="switchTab('stocklist')"
                style="display:flex; align-items:center; gap:6px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                  <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
                Back to Vendor List
              </button>
              <span id="vendorHistoryTitle"
                style="font-size:1.1rem; font-weight:600; color:var(--text-strong);">Purchase History</span>
              <span id="vendorHistorySubtitle" style="font-size:0.8rem; color:var(--muted);"></span>
            </div>
            <div style="display:flex; gap:10px; align-items:center;">
              <button class="btn btn-outline btn-sm" onclick="openModal('lowStockAlertModal')"
                style="color:var(--warn); border-color:var(--warn);">🔔 Set Low Stock Alert</button>
              <button class="btn btn-primary btn-sm" id="vendorRestockBtn">+ New Purchase</button>
            </div>
          </div>

          <!-- Product Analytics Cards for this vendor -->
          <div id="vendorProductAnalytics" style="margin-bottom: 1.5rem;">
            <!-- Rendered by JS -->
          </div>

          <!-- Financial Summary mini-cards -->
          <div class="stats-grid" style="margin-bottom:1.5rem;">
            <div class="stat-card">
              <div class="stat-label">Total Billed</div>
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

          <div class="card-panel">
            <div id="vendorHistoryBody">
              <!-- Date-grouped accordions rendered by JS -->
            </div>
          </div>
        </section>
