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
            <div class="d-flex" style="gap: 10px;">
              <input type="text" id="invSearch" class="input-field" placeholder="Search batches..."
                style="width: 200px;" onkeyup="renderInventory()">
              <select id="invCatFilter" class="input-field" style="width: auto; min-width: 140px;"
                onchange="renderInventory()">
                <option value="">All Categories</option>
              </select>
              <button class="btn btn-primary btn-sm" onclick="resetBatchModal(); openModal('addStockModal')">+ Add New Stock</button>
              <button class="btn btn-outline btn-sm" onclick="openModal('lowStockAlertModal')"
                style="color:var(--warn); border-color:var(--warn);">🔔 Set Low Stock Alert</button>
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
        </section>
