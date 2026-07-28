<!-- 
  FEATURE DOCUMENTATION: Inventory
  - Batch Tracking: Table visualizing active product batches (Cost, Selling Price, Qty, Status).
  - Stock Entry: "+ Add New Stock" button/modal to bring new inventory into the system.
  - Alerts: "Set Low Stock Alert" modal to configure custom thresholds.
  - Filtering: Search bar and category dropdown filters.
-->
        <section id="inventory" class="view-section active">
          <div class="card-header">
            <span>Inventory & Batches</span>
            <div class="d-flex" style="gap: 8px; align-items: center;">
              <input type="text" id="invSearch" class="input-field" placeholder="Search batches..."
                style="width: 180px; padding: 5px 10px; font-size: 0.82rem; height: 32px;" oninput="handleSearchInput()">
              <div class="combobox" id="invCatCombobox" style="width: auto; min-width: 150px;">
                  <input type="text" id="invCatInput" class="input-field" placeholder="All Categories" autocomplete="off" style="height: 32px; font-size: 0.82rem;">
                  <input type="hidden" id="invCatFilter" value="">
                  <div id="invCatDropdown" class="combobox-dropdown"></div>
              </div>
              <div class="combobox" id="invSubCatCombobox" style="width: auto; min-width: 150px;">
                  <input type="text" id="invSubCatInput" class="input-field" placeholder="All Subcategories" autocomplete="off" style="height: 32px; font-size: 0.82rem;">
                  <input type="hidden" id="invSubCatFilter" value="">
                  <div id="invSubCatDropdown" class="combobox-dropdown"></div>
              </div>
              <button class="btn btn-primary btn-sm" style="padding: 5px 12px; font-size: 0.82rem; height: 32px; font-weight: 500;" onclick="openModal('addStockModal')">+ Add New Stock</button>
              <button class="btn btn-outline btn-sm" onclick="openLowStockAlertModal()"
                style="color:var(--warn); border-color:var(--warn); padding: 5px 12px; font-size: 0.82rem; height: 32px; font-weight: 500; white-space: nowrap;">🔔 Set Alert</button>
            </div>
          </div>
          <!-- Inventory Stats -->
          <div class="stats-grid" id="inventoryStats" style="margin-bottom: 1rem;">
            <!-- Rendered via JS -->
          </div>
          <div class="card-panel" style="background: var(--card); border: 1px solid var(--border); border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow-sm); padding: 0;">
            <div class="table-container" style="overflow-x: auto;">
              <table id="inventoryTable" style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                <thead>
                  <tr style="background: var(--surface-container-low); border-bottom: 1px solid var(--border); text-align: left;">
                    <th style="padding: 14px 16px; font-weight: 700; color: var(--text-muted); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em;">Batch ID</th>
                    <th style="padding: 14px 16px; font-weight: 700; color: var(--text-muted); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em;">Date</th>
                    <th style="padding: 14px 16px; font-weight: 700; color: var(--text-muted); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em;">Vendor</th>
                    <th style="padding: 14px 16px; font-weight: 700; color: var(--text-muted); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em;">Product</th>
                    <th style="padding: 14px 16px; font-weight: 700; color: var(--text-muted); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em;">Cost P.</th>
                    <th style="padding: 14px 16px; font-weight: 700; color: var(--text-muted); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em;">Sell P. (Base)</th>
                    <th style="padding: 14px 16px; font-weight: 700; color: var(--text-muted); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em;">Stock Qty</th>
                    <th style="padding: 14px 16px; font-weight: 700; color: var(--text-muted); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em;">Status</th>
                    <th style="padding: 14px 16px; font-weight: 700; color: var(--text-muted); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em; text-align: right;">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <!-- Rendered via JS -->
                </tbody>
              </table>
            </div>

            <!-- Empty State Component -->
            <div id="inventoryEmptyState" class="empty-state-card" style="padding: 64px 24px; text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center;">
              <div class="empty-icon-circle" style="width: 56px; height: 56px; border-radius: 50%; background: var(--surface-container-low); display: flex; align-items: center; justify-content: center; margin-bottom: 16px; border: 1px solid var(--border);">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="color: var(--muted);">
                  <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                  <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                  <line x1="12" y1="22.08" x2="12" y2="12"></line>
                </svg>
              </div>
              <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--text-strong); margin: 0 0 6px 0;">No inventory batches recorded</h3>
              <p style="font-size: 0.88rem; color: var(--muted); margin: 0 0 20px 0; max-width: 360px; line-height: 1.4;">Add new stock batches to manage inventory levels, costs, and selling prices.</p>
              <button class="btn btn-outline btn-sm" onclick="openModal('addStockModal')" style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; font-size: 0.85rem; font-weight: 600; border-radius: var(--radius-md);">
                <span>+</span> Add First Item
              </button>
            </div>
          </div>

           <!-- Pagination -->          
          <div id="inventoryPaginationControls" class="pagination" style="argin-top: 1.5rem; display: flex; justify-content: center; gap: 1rem; align-items: center;"></div>

        </section>
