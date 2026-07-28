<!-- 
  FEATURE DOCUMENTATION: Day to Day Selling View
  Modernized Enterprise UX matching reference screenshot layout.
-->
<section id="day_to_day_selling" class="view-section active">

  <!-- Page Header Row: Title on Left, Search Input on Right -->
  <div class="page-header-row" style="display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 24px;">
    <h1 style="font-size: 1.4rem; font-weight: 700; color: var(--text-strong); margin: 0; letter-spacing: -0.02em;">Day to Day Selling</h1>
    
    <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
      <div class="search-input-wrapper" style="position: relative; width: 300px;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--muted); pointer-events: none;">
          <circle cx="11" cy="11" r="8"></circle>
          <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
        <input type="text" id="salesSearch" class="input-field" placeholder="Search by invoice number or date..."
               oninput="onSalesSearchInput()" style="padding-left: 36px; height: 38px; font-size: 0.85rem; border-radius: var(--radius-md);" />
      </div>
    </div>
  </div>

  <!-- KPI Summary Cards Grid -->
  <div class="kpi-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
    <div class="kpi-card" style="background: var(--card); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 18px 20px; box-shadow: var(--shadow-xs);">
      <div style="font-size: 0.75rem; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px;">TODAY SALES</div>
      <div id="kpiTodaySales" style="font-size: 1.5rem; font-weight: 700; color: var(--text-strong);">₹0.00</div>
      <div style="font-size: 0.75rem; color: var(--success); margin-top: 4px; font-weight: 500;">Live POS data</div>
    </div>

    <div class="kpi-card" style="background: var(--card); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 18px 20px; box-shadow: var(--shadow-xs);">
      <div style="font-size: 0.75rem; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px;">TOTAL BILLS</div>
      <div id="kpiTotalBills" style="font-size: 1.5rem; font-weight: 700; color: var(--text-strong);">0</div>
      <div style="font-size: 0.75rem; color: var(--muted); margin-top: 4px;">Completed orders</div>
    </div>

    <div class="kpi-card" style="background: var(--card); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 18px 20px; box-shadow: var(--shadow-xs);">
      <div style="font-size: 0.75rem; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px;">AVG BILL VALUE</div>
      <div id="kpiAvgBill" style="font-size: 1.5rem; font-weight: 700; color: var(--text-strong);">₹0.00</div>
      <div style="font-size: 0.75rem; color: var(--muted); margin-top: 4px;">Per customer</div>
    </div>

    <div class="kpi-card" style="background: var(--card); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 18px 20px; box-shadow: var(--shadow-xs);">
      <div style="font-size: 0.75rem; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px;">CASH / CREDIT RATIO</div>
      <div id="kpiRatio" style="font-size: 1.5rem; font-weight: 700; color: var(--accent);">100% Cash</div>
      <div style="font-size: 0.75rem; color: var(--muted); margin-top: 4px;">Payment breakdown</div>
    </div>
  </div>

  <!-- Data Panel Card -->
  <div class="card-panel" style="background: var(--card); border: 1px solid var(--border); border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow-sm); padding: 0;">

    
    <div class="table-container" style="overflow-x: auto;">
      <table id="salesTimelineTable" class="data-table" style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
        <thead>
          <tr style="background: var(--surface-container-low); border-bottom: 1px solid var(--border); text-align: left;">
            <th style="padding: 14px 16px; font-weight: 700; color: var(--text-muted); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em;">DATE</th>
            <th style="padding: 14px 16px; font-weight: 700; color: var(--text-muted); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em;">INVOICE #</th>
            <th style="padding: 14px 16px; font-weight: 700; color: var(--text-muted); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em;">CUSTOMER</th>
            <th style="padding: 14px 16px; font-weight: 700; color: var(--text-muted); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em;">ITEMS</th>
            <th style="padding: 14px 16px; font-weight: 700; color: var(--text-muted); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em;">TOTAL AMOUNT</th>
            <th style="padding: 14px 16px; font-weight: 700; color: var(--text-muted); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em;">PAYMENT TYPE</th>
            <th style="padding: 14px 16px; font-weight: 700; color: var(--text-muted); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em;">STATUS</th>
            <th style="padding: 14px 16px; font-weight: 700; color: var(--text-muted); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em; text-align: right;">ACTION</th>
          </tr>
        </thead>
        <tbody>
          <!-- Rendered dynamically via daily_sales.js -->
        </tbody>
      </table>
    </div>

    <!-- Empty State Container (Matching Reference Screenshot Design) -->
    <div id="salesEmptyState" class="empty-state-card" style="padding: 64px 24px; text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center;">
      <div class="empty-icon-circle" style="width: 56px; height: 56px; border-radius: 50%; background: var(--surface-container-low); display: flex; align-items: center; justify-content: center; margin-bottom: 16px; border: 1px solid var(--border);">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="color: var(--muted);">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
          <polyline points="14 2 14 8 20 8"></polyline>
          <line x1="16" y1="13" x2="8" y2="13"></line>
          <line x1="16" y1="17" x2="8" y2="17"></line>
          <polyline points="10 9 9 9 8 9"></polyline>
        </svg>
      </div>
      <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--text-strong); margin: 0 0 6px 0;">No sales records found</h3>
      <p style="font-size: 0.88rem; color: var(--muted); margin: 0 0 20px 0; max-width: 380px; line-height: 1.4;">Sales completed through POS Billing will appear here in real-time.</p>
      <a href="/billing" class="btn btn-outline btn-sm" style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; font-size: 0.85rem; font-weight: 600; border-radius: var(--radius-md); text-decoration: none;">
        Go to Billing (POS)
      </a>
    </div>

  </div>

  <div id="salesTimelinePagination" style="display:none; justify-content:center; align-items:center; gap:12px; margin-top:16px;"></div>
</section>
