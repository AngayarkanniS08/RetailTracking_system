<!-- 
  FEATURE DOCUMENTATION: Customer Credit (Kadan) View
  Full credit management: limit bar, outstanding, collect payment, return, bills history.
-->
<section id="credit_kadan" class="view-section active">
  
  <!-- Page Header Row -->
  <div class="page-header-row" style="display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 24px;">
    <div>
      <h1 style="font-size: 1.4rem; font-weight: 700; color: var(--text-strong); margin: 0 0 2px 0; letter-spacing: -0.02em;">Customer Credit (Kadan)</h1>
      <p style="font-size: 0.82rem; color: var(--muted); margin: 0;" id="creditHeaderCount">Loading...</p>
    </div>
    
    <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
      <!-- Search Input -->
      <div class="search-input-wrapper" style="position: relative; width: 280px;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--muted); pointer-events: none;">
          <circle cx="11" cy="11" r="8"></circle>
          <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
        <input type="text" id="creditSearch" class="input-field" placeholder="Search customers by name or phone..."
               oninput="onCreditSearchInput()" style="padding-left: 36px; height: 38px; font-size: 0.85rem; border-radius: var(--radius-md);" />
      </div>

      <!-- Add Customer Button -->
      <button class="btn btn-primary" onclick="openModal('addCustomerModal')" style="display: flex; align-items: center; gap: 6px; height: 38px; padding: 0 16px; font-weight: 600; font-size: 0.85rem; border-radius: var(--radius-md);">
        <span>+</span> Add Customer
      </button>
    </div>
  </div>

  <!-- Data Card Container -->
  <div class="card-panel" style="background: var(--card); border: 1px solid var(--border); border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow-sm); padding: 0;">
    <div class="table-container" style="overflow-x: auto;">
      <table id="creditTable" class="data-table" style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
        <thead>
          <tr style="background: var(--surface-container-low); border-bottom: 1px solid var(--border); text-align: left;">
            <th style="padding: 14px 16px; font-weight: 700; color: var(--text-muted); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em;">ID</th>
            <th style="padding: 14px 16px; font-weight: 700; color: var(--text-muted); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em;">CUSTOMER NAME</th>
            <th style="padding: 14px 16px; font-weight: 700; color: var(--text-muted); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em;">PHONE</th>
            <th style="padding: 14px 16px; font-weight: 700; color: var(--text-muted); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em;">TOTAL PURCHASES</th>
            <th style="padding: 14px 16px; font-weight: 700; color: var(--text-muted); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em;">TOTAL PAID</th>
            <th style="padding: 14px 16px; font-weight: 700; color: var(--text-muted); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em;">OUTSTANDING</th>
            <th style="padding: 14px 16px; font-weight: 700; color: var(--text-muted); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em;">CREDIT LIMIT</th>
            <th style="padding: 14px 16px; font-weight: 700; color: var(--text-muted); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em;">STATUS</th>
            <th style="padding: 14px 16px; font-weight: 700; color: var(--text-muted); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em; text-align: right;">ACTION</th>
          </tr>
        </thead>
        <tbody>
          <!-- Rendered dynamically via customers.js -->
        </tbody>
      </table>
    </div>

    <!-- Empty State -->
    <div id="creditEmptyState" class="empty-state-card" style="padding: 64px 24px; text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center;">
      <div class="empty-icon-circle" style="width: 56px; height: 56px; border-radius: 50%; background: var(--surface-container-low); display: flex; align-items: center; justify-content: center; margin-bottom: 16px; border: 1px solid var(--border);">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="color: var(--muted);">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
          <circle cx="9" cy="7" r="4"></circle>
          <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
          <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
        </svg>
      </div>
      <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--text-strong); margin: 0 0 6px 0;">No customers recorded yet</h3>
      <p style="font-size: 0.88rem; color: var(--muted); margin: 0 0 20px 0; max-width: 360px; line-height: 1.4;">Start tracking credit by adding your first customer to the system.</p>
      <button class="btn btn-outline btn-sm" onclick="openModal('addCustomerModal')" style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; font-size: 0.85rem; font-weight: 600; border-radius: var(--radius-md);">
        <span>+</span> Add Customer
      </button>
    </div>

  </div>

  <div id="creditPaginationControls" class="pagination" style="display:none; margin-top: 16px;"></div>
</section>

<script type="module">
  import { initCustomerCredit } from '/public/assets/js/pages/customers.js?v=<?= time(); ?>';
  initCustomerCredit();
</script>
