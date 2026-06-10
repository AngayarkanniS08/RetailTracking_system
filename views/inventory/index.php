<!-- 
  FEATURE DOCUMENTATION: Inventory
  - Batch Tracking: Table visualizing active product batches (Cost, Selling Price, Qty, Status).
  - Stock Entry: "+ Add New Stock" button/modal to bring new inventory into the system.
  - Alerts: "Set Low Stock Alert" modal to configure custom thresholds.
  - Filtering: Search bar and category dropdown filters.
-->
        <section id="inventory" class="view-section">
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
          <div class="card-panel">
            <div class="table-container">
              <table id="inventoryTable">
                <thead>
                  <tr>
                    <th>Batch ID</th>
                    <th>Date</th>
                    <th>Vendor</th>
                    <th>Product</th>
                    <th>Cost P.</th>
                    <th>Sell P. (Base)</th>
                    <th>Stock Qty</th>
                    <th>Status</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <!-- Rendered via JS -->
                </tbody>
              </table>
            </div>
          </div>
           <!-- Pagination -->          
          <div id="inventoryPaginationControls" class="pagination" style="argin-top: 1.5rem; display: flex; justify-content: center; gap: 1rem; align-items: center;"></div>

        </section>
