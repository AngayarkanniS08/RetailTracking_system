<!-- 
  FEATURE DOCUMENTATION: Dashboard
  - Key KPIs: High-level metrics including "Today's Sales", "Month's Sales", "Outstanding Credit", and "Total Inventory Value".
  - Visual Analytics: Circular gauges showing "Today's Selling Status" and "Outstanding Credit vs Collected".
  - Progress Bar: Indicating "Stock Level & Health Status".
-->
        <section id="dashboard" class="view-section active">
          <div class="page-title" style="margin-bottom: 1.5rem;">Dashboard</div>

          <!-- Time Period Summary Cards -->
          <div
            style="font-size:0.8rem; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; color:var(--muted); margin-bottom:0.75rem;">
            📊 Sales History Summary</div>
          <div class="time-cards">
            <div class="time-card today" onclick="switchTab('day_to_day_selling')">
              <div class="time-card-icon">📅</div>
              <div class="time-card-label">Today</div>
              <div class="time-card-revenue" id="tcTodayRev">₹0.00</div>
              <div class="time-card-meta">
                <span>🧾 <strong id="tcTodayBills">0</strong> bills</span>
                <span>📊 Avg: <strong id="tcTodayAvg">₹0</strong></span>
              </div>
            </div>
            <div class="time-card week" onclick="switchTab('day_to_day_selling')">
              <div class="time-card-icon">📊</div>
              <div class="time-card-label">This Week</div>
              <div class="time-card-revenue" id="tcWeekRev">₹0.00</div>
              <div class="time-card-meta">
                <span>🧾 <strong id="tcWeekBills">0</strong> bills</span>
                <span>📊 Avg: <strong id="tcWeekAvg">₹0</strong></span>
              </div>
            </div>
            <div class="time-card month" onclick="switchTab('day_to_day_selling')">
              <div class="time-card-icon">📆</div>
              <div class="time-card-label">This Month</div>
              <div class="time-card-revenue" id="tcMonthRev">₹0.00</div>
              <div class="time-card-meta">
                <span>🧾 <strong id="tcMonthBills">0</strong> bills</span>
                <span>📊 Avg: <strong id="tcMonthAvg">₹0</strong></span>
              </div>
            </div>
          </div>

          <!-- Purchase Time Period Summary Cards -->
          <div
            style="font-size:0.8rem; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; color:var(--muted); margin-bottom:0.75rem; margin-top:1.5rem;">
            📊 Purchase Vendor Summary</div>
          <div class="time-cards" style="grid-template-columns: repeat(2, 1fr);">
            <div class="time-card week" onclick="switchTab('vendorhistory')" style="cursor: pointer;">
              <div class="time-card-icon">📊</div>
              <div class="time-card-label">This Week</div>
              <div class="time-card-revenue" id="pcWeekAmount">₹0.00</div>
              <div class="time-card-meta">
                <span>🧾 <strong id="pcWeekPurchases">0</strong> purchases</span>
                <span>💰 Paid: <strong id="pcWeekPaid">₹0</strong></span>
              </div>
            </div>
            <div class="time-card month" onclick="switchTab('vendorhistory')" style="cursor: pointer;">
              <div class="time-card-icon">📆</div>
              <div class="time-card-label">This Month</div>
              <div class="time-card-revenue" id="pcMonthAmount">₹0.00</div>
              <div class="time-card-meta">
                <span>🧾 <strong id="pcMonthPurchases">0</strong> purchases</span>
                <span>💰 Paid: <strong id="pcMonthPaid">₹0</strong></span>
              </div>
            </div>
          </div>

          <!-- Row 2: High Selling, Low Selling, Old Stock -->
          <div class="pos-grid" style="grid-template-columns: 1fr 1fr 1fr; margin-top: 1.5rem;">
            <div class="card-panel">
              <div class="card-header" style="color: var(--ok);">🔥
                High Selling <span
                  style="font-size:0.7rem; color:var(--muted); float:right; margin-top:4px; display:flex; gap:8px; align-items:center;"><span
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
            <div class="card-panel">
              <div class="card-header" style="color: var(--warn);">📉
                Low Selling <span
                  style="font-size:0.7rem; color:var(--muted); float:right; margin-top:4px; display:flex; gap:8px; align-items:center;"><span
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
            <div class="card-panel">
              <div class="card-header" style="color: var(--danger);">📦
                Old Stock</div>
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
        </section>
