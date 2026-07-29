<!-- 
  FEATURE DOCUMENTATION: Dashboard
  - Key KPIs: High-level metrics including "Today's Sales", "Month's Sales", "Outstanding Credit", and "Total Inventory Value".
  - Visual Analytics: Circular gauges showing "Today's Selling Status" and "Outstanding Credit vs Collected".
  - Progress Bar: Indicating "Stock Level & Health Status".
-->
<section id="dashboard" class="view-section active">
  <div class="page-title" style="margin-bottom: 2rem;">Dashboard</div>

  <!-- Time Period Summary Cards (Sales) -->
  <div class="label-sm" style="margin-bottom: 0.75rem;">📊 Sales History Summary</div>
  <div class="grid-12">
    <div class="time-card today col-span-4" onclick="switchTab('day_to_day_selling')">
      <div class="time-card-icon">📅</div>
      <div class="time-card-label">Today</div>
      <div class="time-card-revenue" id="tcTodayRev">₹0.00</div>
      <div class="time-card-meta">
        <span>🧾 <strong id="tcTodayBills">0</strong> bills</span>
        <span>📊 Avg: <strong id="tcTodayAvg">₹0</strong></span>
      </div>
    </div>
    <div class="time-card week col-span-4" onclick="switchTab('day_to_day_selling')">
      <div class="time-card-icon">📊</div>
      <div class="time-card-label">This Week</div>
      <div class="time-card-revenue" id="tcWeekRev">₹0.00</div>
      <div class="time-card-meta">
        <span>🧾 <strong id="tcWeekBills">0</strong> bills</span>
        <span>📊 Avg: <strong id="tcWeekAvg">₹0</strong></span>
      </div>
    </div>
    <div class="time-card month col-span-4" onclick="switchTab('day_to_day_selling')">
      <div class="time-card-icon">📆</div>
      <div class="time-card-label">This Month</div>
      <div class="time-card-revenue" id="tcMonthRev">₹0.00</div>
      <div class="time-card-meta">
        <span>🧾 <strong id="tcMonthBills">0</strong> bills</span>
        <span>📊 Avg: <strong id="tcMonthAvg">₹0</strong></span>
      </div>
    </div>
  </div>

  <!-- Purchase Time Period Summary Cards (Vendor) -->
  <div class="label-sm" style="margin-bottom: 0.75rem; margin-top: 2rem;">📊 Purchase Vendor Summary</div>
  <div class="grid-12">
    <div class="time-card week col-span-6" onclick="switchTab('vendorhistory')" style="cursor: pointer;">
      <div class="time-card-icon">📊</div>
      <div class="time-card-label">This Week</div>
      <div class="time-card-revenue" id="pcWeekAmount">₹0.00</div>
      <div class="time-card-meta">
        <span>🧾 <strong id="pcWeekPurchases">0</strong> purchases</span>
        <span>💰 Paid: <strong id="pcWeekPaid">₹0</strong></span>
      </div>
    </div>
    <div class="time-card month col-span-6" onclick="switchTab('vendorhistory')" style="cursor: pointer;">
      <div class="time-card-icon">📆</div>
      <div class="time-card-label">This Month</div>
      <div class="time-card-revenue" id="pcMonthAmount">₹0.00</div>
      <div class="time-card-meta">
        <span>🧾 <strong id="pcMonthPurchases">0</strong> purchases</span>
        <span>💰 Paid: <strong id="pcMonthPaid">₹0</strong></span>
      </div>
    </div>
  </div>

  <!-- Row 2: High Selling, Normal Selling, Low Selling -->
  <div class="grid-12" style="margin-top: 2rem;">
    <div class="card-panel col-span-4">
      <div class="card-header" style="color: var(--ok);">🔥
        High Selling <span
          style="font-size:0.7rem; color:var(--muted); float:right; display:flex; gap:8px; align-items:center;"><span
            onclick="event.stopPropagation(); openModal('lowStockAlertModal')"
            style="color:var(--warn); cursor:pointer;" title="Set Low Stock Alert">🔔</span></span>
      </div>
      <table class="data-table" style="font-size: 0.9rem;" id="highSellingTable">
        <thead>
          <tr>
            <th>Product</th>
            <th>Qty Sold</th>
            <th>Revenue</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
    <div class="card-panel col-span-4">
      <div class="card-header" style="color: var(--accent-2);">⚖️
        Normal Selling</div>
      <table class="data-table" style="font-size: 0.9rem;" id="normalSellingTable">
        <thead>
          <tr>
            <th>Product</th>
            <th>Qty Sold</th>
            <th>Revenue</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
    <div class="card-panel col-span-4">
      <div class="card-header" style="color: var(--warn);">📉
        Low Selling <span
          style="font-size:0.7rem; color:var(--muted); float:right; display:flex; gap:8px; align-items:center;"><span
            onclick="event.stopPropagation(); openModal('lowStockAlertModal')"
            style="color:var(--warn); cursor:pointer;" title="Set Low Stock Alert">🔔</span></span>
      </div>
      <table class="data-table" style="font-size: 0.9rem;" id="lowSellingTable">
        <thead>
          <tr>
            <th>Product</th>
            <th>Qty Sold</th>
            <th>Revenue</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>

  <!-- Row 3: Old Stock & Weekly Sales Comparison Canvas Chart -->
  <div class="grid-12" style="margin-top: 2rem;">
    <div class="card-panel col-span-6">
      <div class="card-header" style="color: var(--danger); padding: var(--space-md) var(--space-lg); border-bottom: 1px solid var(--border);">📦 Old Stock Items</div>
      <div id="oldStockContainer">
        <table class="data-table" style="font-size: 0.9rem;" id="oldStockTable">
          <thead>
            <tr>
              <th>Product</th>
              <th>Batch</th>
              <th>Age (days)</th>
              <th>Qty</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>

    <!-- Canvas Weekly Sales Comparison Chart (Pure JS Task 3) -->
    <div class="card-panel col-span-6">
      <div class="card-header" style="color: var(--accent); padding: var(--space-md) var(--space-lg); border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
        <span>📊 Weekly Sales Comparison</span>
        <div style="display: flex; gap: 12px; font-size: 0.75rem; font-weight: 500;">
          <span style="display: inline-flex; align-items: center; gap: 4px;"><span style="width: 10px; height: 10px; background: #10b981; border-radius: 2px; display: inline-block;"></span> This Week</span>
          <span style="display: inline-flex; align-items: center; gap: 4px;"><span style="width: 10px; height: 10px; background: #3b82f6; border-radius: 2px; display: inline-block;"></span> Last Week</span>
        </div>
      </div>
      <div class="canvas-wrapper" style="padding: var(--space-md); position: relative; width: 100%; box-sizing: border-box; display: flex; justify-content: center; align-items: center;">
        <canvas id="salesComparisonChart" style="width: 100%; max-height: 240px; border-radius: var(--radius-md);"></canvas>
      </div>
    </div>
  </div>

  <!-- Global Dashboard Empty State CTA Container (Task 2) -->
  <div id="dashboardEmptyState" class="card-panel col-span-12" style="display: none; padding: var(--space-2xl); text-align: center; margin-top: 2rem;">
    <div style="max-width: 480px; margin: 0 auto; display: flex; flex-direction: column; align-items: center; gap: var(--space-md);">
      <div style="width: 64px; height: 64px; border-radius: 50%; background: var(--surface-container-low); display: flex; align-items: center; justify-content: center;">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--muted)" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
          <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
          <line x1="3" y1="6" x2="21" y2="6"></line>
          <path d="M16 10a4 4 0 0 1-8 0"></path>
        </svg>
      </div>
      <h3 style="margin: 0; font-size: var(--font-xl); color: var(--text-strong);">No sales recorded yet</h3>
      <p style="margin: 0; font-size: var(--font-md); color: var(--muted); line-height: 1.5;">No sales yet. Add a product to get started and begin tracking retail inventory, billing, and day-to-day analytics.</p>
      <a href="/products" class="btn btn-primary" style="margin-top: var(--space-xs); padding: 10px 24px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
        <span>📦 Go to Product Master</span>
      </a>
    </div>
  </div>
</section>
