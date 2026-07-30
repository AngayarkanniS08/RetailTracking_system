<!-- 
  ENTERPRISE DASHBOARD UI/UX — Retail Management System
  Strict 5-Second Executive Decision Architecture
-->
<section id="dashboard" class="view-section active">

  <!-- Error Banner Container -->
  <div id="dashErrorContainer"></div>

  <!-- 1. Executive Dashboard Header & Global Filter Bar -->
  <div class="dash-header">
    <div class="dash-header-left">
      <h1 class="dash-title">Dashboard</h1>
      <div class="dash-subtitle">
        <span class="store-badge">🏢 Main Store</span>
        <span class="pulse-indicator"></span>
        <span class="live-status">Live Analytics</span>
      </div>
    </div>

    <div class="dash-header-right">
      <!-- Global Date Selector (Single Dashboard Filter) -->
      <div class="global-filter-container">
        <label for="dashGlobalFilter" class="filter-label">Period:</label>
        <select id="dashGlobalFilter" class="global-filter-select">
          <option value="today" selected>Today</option>
          <option value="yesterday">Yesterday</option>
          <option value="week">This Week</option>
          <option value="month">This Month</option>
          <option value="quarter">This Quarter</option>
          <option value="year">This Year</option>
        </select>
      </div>

      <div class="header-actions">
        <button class="btn btn-primary btn-sm" onclick="switchTab('billing')">
          <span>+ New Sale</span>
        </button>
        <button class="btn btn-outline btn-sm" onclick="switchTab('inventory')">
          <span>+ Add Stock</span>
        </button>
      </div>
    </div>
  </div>

  <!-- 2. Executive KPI Hero Row (4 Cards Only) -->
  <div class="dash-kpi-grid">
    <!-- KPI 1: Revenue -->
    <div class="kpi-card kpi-revenue">
      <div class="kpi-header">
        <span class="kpi-label">Revenue</span>
        <span class="kpi-icon">💰</span>
      </div>
      <div class="kpi-value" id="kpiRevenue">—</div>
      <div class="kpi-footer">
        <span class="kpi-trend trend-up" id="kpiRevTrend">↑ 0%</span>
        <span class="kpi-comparison" id="kpiRevComp">vs prev period</span>
      </div>
    </div>

    <!-- KPI 2: Bills & Orders -->
    <div class="kpi-card kpi-bills">
      <div class="kpi-header">
        <span class="kpi-label">Completed Bills</span>
        <span class="kpi-icon">🧾</span>
      </div>
      <div class="kpi-value" id="kpiBills">0</div>
      <div class="kpi-footer">
        <span class="kpi-meta">Avg Ticket: <strong id="kpiAvgTicket">—</strong></span>
      </div>
    </div>

    <!-- KPI 3: Gross Profit Margin -->
    <div class="kpi-card kpi-profit">
      <div class="kpi-header">
        <span class="kpi-label">Gross Profit</span>
        <span class="kpi-icon">📈</span>
      </div>
      <div class="kpi-value" id="kpiProfit">—</div>
      <div class="kpi-footer">
        <span class="kpi-badge badge-success" id="kpiProfitMargin">—</span>
      </div>
    </div>

    <!-- KPI 4: Outstanding Customer Credit -->
    <div class="kpi-card kpi-credit">
      <div class="kpi-header">
        <span class="kpi-label">Outstanding Credit</span>
        <span class="kpi-icon">💳</span>
      </div>
      <div class="kpi-value text-warn" id="kpiCredit">—</div>
      <div class="kpi-footer">
        <span class="kpi-meta" id="kpiCreditMeta">Pending Customer Receivables</span>
      </div>
    </div>
  </div>

  <!-- 4. Filtered Business Summaries (Sales & Purchase Rows) -->
  <div class="dash-summary-grid">
    <!-- Filtered Sales Summary Widget -->
    <div class="card-panel summary-card">
      <div class="card-header summary-header">
        <div class="summary-title">
          <span>🛍️ Sales Summary</span>
          <span class="period-chip" id="salesSummaryChip">Today</span>
        </div>
      </div>
      <div class="summary-body">
        <div class="summary-metric-row">
          <div class="metric-item">
            <div class="metric-label">Filtered Revenue</div>
            <div class="metric-val" id="sumSalesRev">—</div>
          </div>
          <div class="metric-item">
            <div class="metric-label">Bills Billed</div>
            <div class="metric-val" id="sumSalesBills">0</div>
          </div>
          <div class="metric-item">
            <div class="metric-label">Avg Bill Value</div>
            <div class="metric-val" id="sumSalesAvg">—</div>
          </div>
        </div>
        <div class="summary-chips-row">
          <div class="summary-chip">Today: <strong id="chipTodayRev">—</strong></div>
          <div class="summary-chip">Week: <strong id="chipWeekRev">—</strong></div>
          <div class="summary-chip">Month: <strong id="chipMonthRev">—</strong></div>
        </div>
      </div>
    </div>

    <!-- Filtered Purchase Summary Widget -->
    <div class="card-panel summary-card">
      <div class="card-header summary-header">
        <div class="summary-title">
          <span>📦 Purchase & Vendor Summary</span>
          <span class="period-chip" id="purchaseSummaryChip">This Month</span>
        </div>
      </div>
      <div class="summary-body">
        <div class="summary-metric-row">
          <div class="metric-item">
            <div class="metric-label">Total Purchased</div>
            <div class="metric-val" id="sumPurAmount">—</div>
          </div>
          <div class="metric-item">
            <div class="metric-label">Paid to Vendors</div>
            <div class="metric-val text-ok" id="sumPurPaid">—</div>
          </div>
          <div class="metric-item">
            <div class="metric-label">Pending Payables</div>
            <div class="metric-val text-warn" id="sumPurPending">—</div>
          </div>
        </div>
        <div class="summary-chips-row">
          <div class="summary-chip">Purchases Count: <strong id="chipPurCount">0</strong></div>
          <div class="summary-chip">Avg Order: <strong id="chipPurAvg">—</strong></div>
        </div>
      </div>
    </div>
  </div>

  <!-- 5. Unified Tabbed Product Performance Component -->
  <div class="card-panel product-perf-card">
    <div class="card-header perf-header">
      <div class="perf-title-group">
        <span class="perf-icon">📦</span>
        <span class="perf-title">Product Performance</span>
      </div>

      <!-- Single Filter Tabs -->
      <div class="perf-tabs" role="tablist">
        <button class="perf-tab active" data-tab="all" role="tab" aria-selected="true">All Products</button>
        <button class="perf-tab" data-tab="high" role="tab" aria-selected="false">🔥 High Selling</button>
        <button class="perf-tab" data-tab="normal" role="tab" aria-selected="false">⚖️ Normal</button>
        <button class="perf-tab" data-tab="low" role="tab" aria-selected="false">📉 Low Selling</button>
      </div>

      <!-- Inline Search -->
      <div class="perf-search-container">
        <input type="text" id="prodPerfSearch" class="input-field perf-search-input" placeholder="Filter product name..." />
      </div>
    </div>

    <div class="table-responsive">
      <div id="stockIntelSkeleton" class="skeleton-loader" style="display: none; padding: 24px;">
        <div class="skeleton-box" style="width: 100%; height: 20px; margin-bottom: 12px;"></div>
        <div class="skeleton-box" style="width: 100%; height: 20px; margin-bottom: 12px;"></div>
        <div class="skeleton-box" style="width: 60%; height: 20px;"></div>
      </div>
      <table class="data-table" id="productPerformanceTable">
        <thead>
          <tr>
            <th>Product Name</th>
            <th>Qty Sold</th>
            <th>Revenue</th>
            <th>Sales Rank / Velocity</th>
            <th>Stock Status</th>
            <th style="text-align: right;">Action</th>
          </tr>
        </thead>
        <tbody id="productPerformanceBody">
          <!-- Dynamically populated rows -->
        </tbody>
      </table>
    </div>
  </div>

  <!-- 6. Inventory Health & Credit Breakdown Row -->
  <div class="dash-bottom-grid">
    <!-- Inventory Health Summary -->
    <div class="card-panel widget-card">
      <div class="card-header">
        <div class="widget-title">
          <span>🩺 Inventory Health</span>
        </div>
        <button class="btn btn-link btn-xs" onclick="switchTab('inventory')">View All</button>
      </div>
      <div class="widget-body">
        <div class="health-grid">
          <div class="health-item item-danger">
            <div class="health-val" id="invOutStockCount">0</div>
            <div class="health-lbl">Out of Stock</div>
          </div>
          <div class="health-item item-warn">
            <div class="health-val" id="invLowStockCount">0</div>
            <div class="health-lbl">Low Stock</div>
          </div>
          <div class="health-item item-ok">
            <div class="health-val" id="invHealthyCount">0</div>
            <div class="health-lbl">Healthy Stock</div>
          </div>
          <div class="health-item item-info">
            <div class="health-val" id="invTotalValue">—</div>
            <div class="health-lbl">Catalog Stock Value</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Outstanding Customer Credit Summary -->
    <div class="card-panel widget-card">
      <div class="card-header">
        <div class="widget-title">
          <span>💳 Outstanding Credit Ledger</span>
        </div>
        <button class="btn btn-link btn-xs" onclick="switchTab('credit_student')">Manage Credits</button>
      </div>
      <div class="widget-body">
        <div class="credit-summary-box">
          <div class="credit-main-val" id="creditTotalBalance">—</div>
          <div class="credit-sub-text">Total pending receivables from active customers</div>
          <div class="credit-action-row">
            <button class="btn btn-outline btn-sm" onclick="switchTab('credit_student')">View Customer Statements</button>
          </div>
        </div>
      </div>
    </div>
  </div>



</section>
