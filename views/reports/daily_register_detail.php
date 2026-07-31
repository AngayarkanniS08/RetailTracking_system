<section id="daily_register_detail_section" class="view-section active">

  <!-- Page Header Row: Title, Date Picker & Name Badge -->
  <div class="page-header-card" style="background: var(--card); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 20px 24px; margin-bottom: 24px; box-shadow: var(--shadow-xs); display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap;">
    <div>
      <div style="font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 700; color: var(--accent); margin-bottom: 4px;">
        Daily Detail View
      </div>
      <h1 id="detailPageTitle" style="font-size: 1.5rem; font-weight: 800; color: var(--text-strong); margin: 0; letter-spacing: -0.02em;">
        Daily Summary for <span id="displaySelectedDate">—</span>
      </h1>
    </div>

    <!-- Date Navigation & Picker Controls -->
    <div style="display: flex; align-items: center; gap: 10px;">
      <button id="prevDayBtn" class="btn btn-sm btn-outline" style="padding: 6px 12px; font-size: 0.82rem; border-radius: var(--radius-md); cursor: pointer;" onclick="navigateDay(-1)">
        ◀ Prev Day
      </button>

      <div style="position: relative;">
        <input type="date" id="registerDatePicker" class="input-field" style="height: 36px; padding: 0 10px; font-size: 0.85rem; border-radius: var(--radius-md);" onchange="onDateSelectionChange(this.value)" />
      </div>

      <button id="nextDayBtn" class="btn btn-sm btn-outline" style="padding: 6px 12px; font-size: 0.82rem; border-radius: var(--radius-md); cursor: pointer;" onclick="navigateDay(1)">
        Next Day ▶
      </button>
    </div>

    <div style="display: flex; align-items: center; gap: 12px; background: var(--surface-container-low); padding: 10px 16px; border-radius: var(--radius-md); border: 1px solid var(--border);">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--muted);">
        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
        <line x1="16" y1="2" x2="16" y2="6"></line>
        <line x1="8" y1="2" x2="8" y2="6"></line>
        <line x1="3" y1="10" x2="21" y2="10"></line>
      </svg>
      <div>
        <div style="font-size: 0.72rem; color: var(--muted); text-transform: uppercase; font-weight: 600;">Selected Date</div>
        <div id="displaySelectedDateBadge" style="font-size: 0.95rem; font-weight: 700; color: var(--text-strong);">—</div>
      </div>
    </div>
  </div>

  <!-- KPI Metrics Grid -->
  <div class="kpi-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 16px; margin-bottom: 24px;">
    
    <!-- FORMS GENERATED KPI (Prominent requirement) -->
    <div class="kpi-card" style="background: var(--card); border: 2px solid var(--accent); border-radius: var(--radius-lg); padding: 20px; box-shadow: var(--shadow-sm); position: relative; overflow: hidden;">
      <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
        <span style="font-size: 0.75rem; font-weight: 700; color: var(--accent); text-transform: uppercase; letter-spacing: 0.05em;">FORMS GENERATED</span>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--accent);">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
          <polyline points="14 2 14 8 20 8"></polyline>
          <line x1="16" y1="13" x2="8" y2="13"></line>
          <line x1="16" y1="17" x2="8" y2="17"></line>
        </svg>
      </div>
      <div id="kpiFormsGenerated" style="font-size: 2.1rem; font-weight: 800; color: var(--text-strong); line-height: 1.1;">0</div>
      <div style="font-size: 0.75rem; color: var(--muted); margin-top: 6px;">For selected date</div>
    </div>

    <!-- GROSS REVENUE -->
    <div class="kpi-card" style="background: var(--card); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 20px; box-shadow: var(--shadow-xs);">
      <div style="font-size: 0.75rem; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px;">GROSS REVENUE</div>
      <div id="kpiGrossRevenue" style="font-size: 1.6rem; font-weight: 700; color: var(--text-strong);">₹0.00</div>
      <div style="font-size: 0.75rem; color: var(--success); margin-top: 4px; font-weight: 500;">Total invoice sales</div>
    </div>

    <!-- CASH COLLECTED -->
    <div class="kpi-card" style="background: var(--card); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 20px; box-shadow: var(--shadow-xs);">
      <div style="font-size: 0.75rem; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px;">CASH COLLECTED</div>
      <div id="kpiCashCollected" style="font-size: 1.6rem; font-weight: 700; color: var(--success);">₹0.00</div>
      <div style="font-size: 0.75rem; color: var(--muted); margin-top: 4px;">Settled payments</div>
    </div>

    <!-- CREDIT / PENDING -->
    <div class="kpi-card" style="background: var(--card); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 20px; box-shadow: var(--shadow-xs);">
      <div style="font-size: 0.75rem; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px;">CREDIT / OUTSTANDING</div>
      <div id="kpiCreditIssued" style="font-size: 1.6rem; font-weight: 700; color: var(--warning, #d97706);">₹0.00</div>
      <div style="font-size: 0.75rem; color: var(--muted); margin-top: 4px;">Customer ledger balance</div>
    </div>

    <!-- AVERAGE FORM VALUE -->
    <div class="kpi-card" style="background: var(--card); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 20px; box-shadow: var(--shadow-xs);">
      <div style="font-size: 0.75rem; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px;">AVG FORM VALUE</div>
      <div id="kpiAvgFormValue" style="font-size: 1.6rem; font-weight: 700; color: var(--text-strong);">₹0.00</div>
      <div style="font-size: 0.75rem; color: var(--muted); margin-top: 4px;">Per generated invoice</div>
    </div>
  </div>

  <!-- Generated Forms Section Panel -->
  <div class="card-panel" style="background: var(--card); border: 1px solid var(--border); border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow-sm); padding: 0;">

    <!-- Filter & Search Toolbar -->
    <div style="padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; background: var(--surface-container-low);">
      <div style="display: flex; align-items: center; gap: 8px;">
        <h3 style="font-size: 1.05rem; font-weight: 700; color: var(--text-strong); margin: 0;">Generated Forms &amp; Invoices</h3>
        <span id="formsBadgeCount" class="badge bg-primary rounded-pill" style="font-size: 0.75rem;">0</span>
      </div>

      <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
        <!-- Live Search Input -->
        <div class="search-input-wrapper" style="position: relative; width: 260px;">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--muted); pointer-events: none;">
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
          </svg>
          <input type="text" id="detailSearch" class="input-field" placeholder="Filter by invoice # or customer..." oninput="onDetailSearchInput()" style="padding-left: 36px; height: 36px; font-size: 0.82rem; border-radius: var(--radius-md);" />
        </div>

        <!-- Payment Status Filter -->
        <select id="statusFilterSelect" class="input-field" onchange="onStatusFilterChange()" style="height: 36px; font-size: 0.82rem; border-radius: var(--radius-md); width: 140px;">
          <option value="ALL">All Statuses</option>
          <option value="PAID">Paid</option>
          <option value="PENDING">Pending / Credit</option>
          <option value="PARTIAL">Partial</option>
          <option value="CANCELLED">Cancelled</option>
        </select>
      </div>
    </div>

    <!-- Concise Forms Table -->
    <div class="table-container" style="overflow-x: auto;">
      <table id="detailFormsTable" class="data-table" style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
        <thead>
          <tr style="background: var(--surface-container-low); border-bottom: 1px solid var(--border); text-align: left;">
            <th style="padding: 12px 16px; font-weight: 700; color: var(--text-muted); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em;">TIME</th>
            <th style="padding: 12px 16px; font-weight: 700; color: var(--text-muted); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em;">INVOICE / FORM #</th>
            <th style="padding: 12px 16px; font-weight: 700; color: var(--text-muted); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em;">CUSTOMER</th>
            <th style="padding: 12px 16px; font-weight: 700; color: var(--text-muted); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em;">OPERATOR</th>
            <th style="padding: 12px 16px; font-weight: 700; color: var(--text-muted); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em;">ITEMS SUMMARY</th>
            <th style="padding: 12px 16px; font-weight: 700; color: var(--text-muted); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em;">TOTAL AMOUNT</th>
            <th style="padding: 12px 16px; font-weight: 700; color: var(--text-muted); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em;">PAYMENT STATUS</th>
            <th style="padding: 12px 16px; font-weight: 700; color: var(--text-muted); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em; text-align: right;">ACTION</th>
          </tr>
        </thead>
        <tbody>
          <!-- Dynamic rendering via daily_register_detail.js -->
        </tbody>
      </table>
    </div>

    <!-- Empty State Container -->
    <div id="detailEmptyState" class="empty-state-card" style="padding: 56px 24px; text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center;">
      <div class="empty-icon-circle" style="width: 52px; height: 52px; border-radius: 50%; background: var(--surface-container-low); display: flex; align-items: center; justify-content: center; margin-bottom: 14px; border: 1px solid var(--border);">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="color: var(--muted);">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
          <polyline points="14 2 14 8 20 8"></polyline>
        </svg>
      </div>
      <h3 style="font-size: 1.05rem; font-weight: 700; color: var(--text-strong); margin: 0 0 4px 0;">No forms found</h3>
      <p style="font-size: 0.85rem; color: var(--muted); margin: 0; max-width: 360px;">No generated forms match the selected date, operator/name, or filter criteria.</p>
    </div>

  </div>

  <script type="module" src="/public/assets/js/pages/daily_register_detail.js"></script>
</section>
