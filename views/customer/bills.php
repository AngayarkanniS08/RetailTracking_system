<!-- 
  FEATURE DOCUMENTATION: Dedicated Customer Billing History Page
  Includes Back Navigation, Breadcrumbs, Executive Metrics, Modern SaaS Table, Payment Receipts & Bootstrap 5 Offcanvas Panel.
-->
<section id="customer_billing_history" class="view-section active">

  <!-- Page Header Row with Back Button and Title -->
  <div class="page-header-row" style="display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 20px;">
    <div style="display: flex; align-items: center; gap: 14px;">
      <a href="/customers" class="btn btn-outline btn-sm">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <polyline points="15 18 9 12 15 6"></polyline>
        </svg>
        <span>Back</span>
      </a>
      <div>
        <h1 style="font-size: 1.4rem; font-weight: 700; color: var(--text-strong); margin: 0; letter-spacing: -0.02em;">
          Billing & Payment History — <span id="cbCustomerNameTitle"><?= htmlspecialchars($customerName ?? 'Customer') ?></span>
        </h1>
        <p style="font-size: 0.85rem; color: var(--muted); margin: 2px 0 0 0;">
          All invoices, payments, and credit transactions for <strong id="cbCustomerNameSub"><?= htmlspecialchars($customerName ?? 'Customer') ?></strong>
        </p>
      </div>
    </div>
  </div>

  <!-- Summary Cards (data populated via bills.js → /api/customers/{id}/ledger/summary) -->
  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 24px;">
    <div class="card" style="padding: 16px 20px; border-radius: var(--radius-md); background: var(--bg-elevated); border: 1px solid var(--border);">
      <div style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: var(--muted); letter-spacing: 0.05em; margin-bottom: 4px;">Total Invoices</div>
      <div id="cbInvoiceCount" style="font-size: 1.5rem; font-weight: 700; color: var(--text-strong);">--</div>
    </div>
    <div class="card" style="padding: 16px 20px; border-radius: var(--radius-md); background: var(--bg-elevated); border: 1px solid var(--border);">
      <div style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: var(--muted); letter-spacing: 0.05em; margin-bottom: 4px;">Total Billed</div>
      <div id="cbTotalBilled" style="font-size: 1.5rem; font-weight: 700; color: var(--text-strong);">&#8377;0.00</div>
    </div>
    <div class="card" style="padding: 16px 20px; border-radius: var(--radius-md); background: var(--bg-elevated); border: 1px solid var(--border);">
      <div style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: var(--muted); letter-spacing: 0.05em; margin-bottom: 4px;">Total Paid</div>
      <div id="cbTotalPaid" style="font-size: 1.5rem; font-weight: 700; color: var(--ok, #10b981);">&#8377;0.00</div>
    </div>
    <div class="card" style="padding: 16px 20px; border-radius: var(--radius-md); background: var(--bg-elevated); border: 1px solid var(--border);">
      <div style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: var(--muted); letter-spacing: 0.05em; margin-bottom: 4px;">Balance Due</div>
      <div id="cbBalanceDue" style="font-size: 1.5rem; font-weight: 700; color: var(--danger, #ef4444);">&#8377;0.00</div>
    </div>
    <div class="card" style="padding: 16px 20px; border-radius: var(--radius-md); background: var(--bg-elevated); border: 1px solid var(--border); display:none;" id="cbCreditLimitCard">
      <div style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: var(--muted); letter-spacing: 0.05em; margin-bottom: 4px;">Credit Limit</div>
      <div id="cbCreditLimit" style="font-size: 1.5rem; font-weight: 700; color: var(--text-strong);">&#8377;0</div>
      <div style="font-size: 0.75rem; color: var(--muted); margin-top: 2px;">Available: <strong id="cbAvailableCredit" style="color: var(--ok,#10b981);">&#8377;0</strong></div>
    </div>
  </div>

  <!-- Table Card with Filter Tabs + Action Buttons -->
  <div class="card" style="border-radius: 8px; overflow: hidden; border: 1px solid var(--border); background: var(--bg-elevated); box-shadow: 0 1px 2px rgba(16, 24, 40, 0.05);">
    <div class="card-header" style="padding: 14px 20px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
      <div style="display: flex; gap: 6px; flex-wrap: wrap;" id="ledgerFilterTabs">
        <button class="btn btn-sm btn-primary ledger-tab" data-type="" style="padding: 5px 12px; font-size: 0.78rem; font-weight: 600;" onclick="setLedgerFilter('')">All</button>
        <button class="btn btn-sm btn-outline ledger-tab" data-type="invoice" style="padding: 5px 12px; font-size: 0.78rem;" onclick="setLedgerFilter('invoice')">&#128195; Invoices</button>
        <button class="btn btn-sm btn-outline ledger-tab" data-type="payment" style="padding: 5px 12px; font-size: 0.78rem;" onclick="setLedgerFilter('payment')">&#9989; Payments</button>
        <button class="btn btn-sm btn-outline ledger-tab" data-type="return" style="padding: 5px 12px; font-size: 0.78rem;" onclick="setLedgerFilter('return')">&#8617; Returns</button>
      </div>
      <div style="display: flex; gap: 8px;">
        <button id="billsCollectBtn" class="btn btn-sm btn-primary" onclick="openBillsPaymentModal()" style="padding: 5px 14px; font-size: 0.78rem; display:none;">&#43; Collect Payment</button>
        <button id="billsReturnBtn" class="btn btn-sm" onclick="openBillsReturnModal()" style="padding: 5px 14px; font-size: 0.78rem; display:none; background:rgba(239,68,68,0.1); color:var(--danger,#ef4444); border:1px solid rgba(239,68,68,0.3); font-weight:600;">&#8617; Record Return</button>
      </div>
    </div>

    <div class="table-responsive">
      <table id="customerBillsTable" style="width: 100%; border-collapse: separate; border-spacing: 0;">
        <thead>
          <tr style="background-color: var(--surface-container-low);">
            <th style="padding: 12px 20px; text-align: left; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: var(--muted-strong); border-bottom: 1px solid var(--border); white-space:nowrap;">Receipt / Inv #</th>
            <th style="padding: 12px 20px; text-align: left; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: var(--muted-strong); border-bottom: 1px solid var(--border); white-space:nowrap;">Date &amp; Time</th>
            <th style="padding: 12px 20px; text-align: left; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: var(--muted-strong); border-bottom: 1px solid var(--border);">Type / Notes</th>
            <th style="padding: 12px 20px; text-align: right; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: var(--muted-strong); border-bottom: 1px solid var(--border); white-space:nowrap;">Debit (&#8377;)</th>
            <th style="padding: 12px 20px; text-align: right; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: var(--muted-strong); border-bottom: 1px solid var(--border); white-space:nowrap;">Credit (&#8377;)</th>
            <th style="padding: 12px 20px; text-align: right; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: var(--muted-strong); border-bottom: 1px solid var(--border); white-space:nowrap;">Running Balance</th>
            <th style="padding: 12px 20px; text-align: center; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: var(--muted-strong); border-bottom: 1px solid var(--border);">Type</th>
            <th style="padding: 12px 20px; text-align: right; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: var(--muted-strong); border-bottom: 1px solid var(--border);">Action</th>
          </tr>
        </thead>
        <tbody id="customerBillsTbody">
          <tr>
            <td colspan="8" style="text-align: center; padding: 40px; color: var(--muted);">Loading ledger records...</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Ledger Pagination -->
    <div id="ledgerPaginationControls" style="display:none; padding: 0px; border-top: 1px solid var(--border); display:flex; align-items:center; gap:6px; justify-content:center;"></div>
  </div>


</section>

<!-- Offcanvas Backdrop Overlay -->
<div id="offcanvasBackdrop" class="offcanvas-backdrop" onclick="closeReceiptOffcanvas()"></div>

<!-- Payment Receipt Side Drawer -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="receiptOffcanvas" aria-labelledby="receiptOffcanvasLabel">

  <!-- Header -->
  <div class="offcanvas-header">
    <div>
      <p class="offcanvas-eyebrow">Transaction Record</p>
      <h5 class="offcanvas-title" id="receiptOffcanvasLabel">Payment Receipt</h5>
      <span id="rmReceiptNum" class="offcanvas-ref">--</span>
    </div>
    <button type="button" class="offcanvas-close-btn" onclick="closeReceiptOffcanvas()" aria-label="Close">
      <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="1" y1="1" x2="13" y2="13"/><line x1="13" y1="1" x2="1" y2="13"/></svg>
    </button>
  </div>

  <!-- Body -->
  <div class="offcanvas-body">

    <!-- Detail Card -->
    <div class="receipt-card">

      <!-- Customer Row -->
      <div class="receipt-row">
        <span class="receipt-label">Customer</span>
        <strong class="receipt-value" id="rmCustomerName"><?= htmlspecialchars($customerName ?? 'Customer') ?></strong>
      </div>

      <!-- Date Row -->
      <div class="receipt-row">
        <span class="receipt-label">Date &amp; Time</span>
        <strong class="receipt-value" id="rmDate">--</strong>
      </div>

      <!-- Type Row — pill badge -->
      <div class="receipt-row">
        <span class="receipt-label">Transaction Type</span>
        <span class="receipt-type-badge" id="rmType">--</span>
      </div>

      <!-- Notes Row -->
      <div class="receipt-row">
        <span class="receipt-label">Notes / Reference</span>
        <span class="receipt-value receipt-value--muted" id="rmNotes">--</span>
      </div>

      <div class="receipt-divider"></div>

      <!-- Amounts -->
      <div class="receipt-row">
        <span class="receipt-label">Billed Amount</span>
        <strong class="receipt-value" id="rmBilled">&#8377;0.00</strong>
      </div>
      <div class="receipt-row">
        <span class="receipt-label">Amount Paid</span>
        <strong class="receipt-value receipt-value--paid" id="rmPaid">&#8377;0.00</strong>
      </div>
      <div class="receipt-row receipt-row--last">
        <span class="receipt-label">Remaining Balance</span>
        <strong class="receipt-value receipt-value--danger" id="rmBalance">&#8377;0.00</strong>
      </div>
    </div>

    <!-- Spacer pushes buttons to bottom -->
    <div style="flex:1;"></div>

    <!-- Action Buttons -->
    <div class="offcanvas-actions">
      <button type="button" class="btn btn-primary offcanvas-btn" onclick="window.print()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
        Print Receipt
      </button>
      <button type="button" class="btn btn-outline offcanvas-btn" onclick="closeReceiptOffcanvas()">
        Close Panel
      </button>
    </div>

  </div>
</div>

<style>
  /* ── Offcanvas Container ── */
  .offcanvas.offcanvas-end {
    position: fixed;
    top: 0; right: 0; bottom: 0;
    z-index: 1050;
    display: flex;
    flex-direction: column;
    width: 440px;
    max-width: 92vw;
    background-color: var(--bg-elevated, #ffffff);
    border-left: 1px solid rgba(0,0,0,0.08);
    box-shadow: -10px 0 30px rgba(0,0,0,0.06), -2px 0 8px rgba(0,0,0,0.04);
    transform: translateX(100%);
    transition: transform 0.3s cubic-bezier(0.4,0,0.2,1), visibility 0.3s ease;
    visibility: hidden;
  }
  .offcanvas.offcanvas-end.show {
    transform: translateX(0);
    visibility: visible !important;
  }

  /* ── Header ── */
  .offcanvas-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    padding: 24px 28px 20px;
    border-bottom: 1px solid var(--border);
    flex-shrink: 0;
  }
  .offcanvas-eyebrow {
    margin: 0 0 2px;
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--muted);
  }
  .offcanvas-title {
    margin: 0 0 4px;
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--text-strong);
    line-height: 1.2;
  }
  .offcanvas-ref {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--accent);
    font-family: var(--font-mono, monospace);
  }
  .offcanvas-close-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    flex-shrink: 0;
    border-radius: 50%;
    border: 1px solid var(--border);
    background: var(--surface-container-low, #f9fafb);
    color: var(--muted);
    cursor: pointer;
    transition: background 0.15s, color 0.15s, border-color 0.15s;
    margin-top: 2px;
  }
  .offcanvas-close-btn:hover {
    background: var(--danger, #ef4444);
    border-color: var(--danger, #ef4444);
    color: #fff;
  }

  /* ── Body ── */
  .offcanvas-body {
    flex: 1;
    display: flex;
    flex-direction: column;
    padding: 28px;
    gap: 0;
    overflow-y: auto;
  }

  /* ── Detail Card ── */
  .receipt-card {
    background: var(--surface-container-low, #f9fafb);
    border-radius: 12px;
    border: 1px solid var(--border);
    overflow: hidden;
    box-shadow: 0 1px 4px rgba(0,0,0,0.04);
  }
  .receipt-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 13px 18px;
    border-bottom: 1px solid var(--border);
    gap: 12px;
  }
  .receipt-row--last {
    border-bottom: none;
  }
  .receipt-label {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--muted);
    white-space: nowrap;
    flex-shrink: 0;
  }
  .receipt-value {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-strong);
    text-align: right;
  }
  .receipt-value--muted {
    color: var(--muted);
    font-weight: 400;
    font-size: 0.82rem;
  }
  .receipt-value--paid  { color: var(--ok, #10b981); font-size: 1rem; font-weight: 700; }
  .receipt-value--danger { color: var(--danger, #ef4444); font-size: 0.92rem; }

  /* ── Transaction Type Pill Badge ── */
  .receipt-type-badge {
    display: inline-flex;
    align-items: center;
    padding: 3px 11px;
    border-radius: 9999px;
    font-size: 0.72rem;
    font-weight: 700;
    background: rgba(99,102,241,0.1);
    color: #6366f1;
    border: 1px solid rgba(99,102,241,0.25);
    text-transform: uppercase;
    letter-spacing: 0.04em;
  }

  /* ── Divider ── */
  .receipt-divider {
    height: 1px;
    background: repeating-linear-gradient(90deg, var(--border) 0, var(--border) 6px, transparent 6px, transparent 12px);
    margin: 0 18px;
  }

  /* ── Action Buttons ── */
  .offcanvas-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-top: 24px;
    flex-shrink: 0;
  }
  .offcanvas-btn {
    width: 100%;
    height: 48px;
    border-radius: 12px !important;
    font-weight: 700;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.2s cubic-bezier(0.4,0,0.2,1) !important;
  }
  .btn-primary.offcanvas-btn {
    box-shadow: 0 4px 12px rgba(99,102,241,0.2);
  }
  .btn-primary.offcanvas-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(99,102,241,0.3);
  }

  /* ── Backdrop ── */
  .offcanvas-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.35);
    backdrop-filter: blur(4px);
    z-index: 1040;
    opacity: 0;
    transition: opacity 0.3s ease;
    pointer-events: none;
  }
  .offcanvas-backdrop.show {
    opacity: 1;
    pointer-events: auto;
  }

  /* ── Bills Table Hover ── */
  #customerBillsTable tbody tr {
    transition: background-color 0.15s ease;
  }
  #customerBillsTable tbody tr:hover {
    background-color: var(--surface-hover, #f9fafb);
  }
  .status-badge {
    padding: 4px 12px;
    border-radius: 16px;
    font-size: 11px;
    font-weight: 700;
    display: inline-block;
    text-transform: uppercase;
    letter-spacing: 0.04em;
  }
  .status-paid   { background: rgba(16,185,129,0.12); color: #10b981; }
  .status-unpaid { background: rgba(239,68,68,0.12);  color: #ef4444; }
</style>


<script type="module">
  import { formatCurrency } from '/public/assets/js/utils/format.js';

  const customerId = '<?= htmlspecialchars($customerId ?? '') ?>';
  const customerName = '<?= htmlspecialchars($customerName ?? 'Customer') ?>';

  window.openReceiptOffcanvas = function(num, type, billed, paid, balance, date, notes) {
    document.getElementById('rmReceiptNum').textContent = num;
    document.getElementById('rmCustomerName').textContent = customerName;
    document.getElementById('rmDate').textContent = date;
    document.getElementById('rmType').textContent = type;
    document.getElementById('rmNotes').textContent = notes || '-';
    document.getElementById('rmBilled').textContent = formatCurrency(parseFloat(billed || 0));
    document.getElementById('rmPaid').textContent = formatCurrency(parseFloat(paid || 0));
    document.getElementById('rmBalance').textContent = formatCurrency(parseFloat(balance || 0));
    
    const panel = document.getElementById('receiptOffcanvas');
    const backdrop = document.getElementById('offcanvasBackdrop');
    if (panel) panel.classList.add('show');
    if (backdrop) backdrop.classList.add('show');
  };

  window.closeReceiptOffcanvas = function() {
    const panel = document.getElementById('receiptOffcanvas');
    const backdrop = document.getElementById('offcanvasBackdrop');
    if (panel) panel.classList.remove('show');
    if (backdrop) backdrop.classList.remove('show');
  };

  window.safeApiCall = async function safeApiCall(url, options = {}) {
    if (typeof window.apiRequest === 'function') {
      return window.apiRequest(url, options);
    }
    if (typeof window.fetchWithAuth === 'function') {
      const res = await window.fetchWithAuth(url, options);
      if (!res.ok) {
        const txt = await res.text();
        let err;
        try { err = JSON.parse(txt); } catch(_) {}
        throw new Error(err?.error || err?.message || `Server Error (${res.status})`);
      }
      return res.json();
    }
    const token = localStorage.getItem('auth_token');
    const headers = {
      'Content-Type': 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...(options.headers || {})
    };
    const res = await fetch(url, { ...options, headers, credentials: 'same-origin' });
    if (!res.ok) {
      const txt = await res.text();
      let err;
      try { err = JSON.parse(txt); } catch(_) {}
      throw new Error(err?.error || err?.message || `Server Error (${res.status})`);
    }
    return res.json();
  };

  async function loadCustomerBillsPage() {
    const tbody = document.getElementById('customerBillsTbody');
    if (!tbody) return;

    if (!customerId) {
      tbody.innerHTML = `
        <tr>
          <td colspan="8" class="empty-state-cell" style="text-align: center; padding: 48px 24px;">
            <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px;">
              <div style="width: 48px; height: 48px; border-radius: 50%; background: var(--surface-container-low); display: flex; align-items: center; justify-content: center; color: var(--muted); font-size: 1.4rem;">⚠️</div>
              <div style="font-size: 1rem; font-weight: 600; color: var(--text-strong);">No customer selected</div>
              <div style="font-size: 0.88rem; color: var(--muted); max-width: 320px;"><a href="/customers">Return to Customer Credit</a></div>
            </div>
          </td>
        </tr>`;
      return;
    }

    try {
      let invoices = [];
      let ledgerEntries = [];
      let currentBalance = 0;

      // 1. Fetch POS Invoices
      try {
        const invPath = `/api/invoices?customer_id=${encodeURIComponent(customerId)}&limit=100`;
        const res = await window.safeApiCall(invPath);
        invoices = res?.data || (Array.isArray(res) ? res : (res?.invoices || []));
      } catch (e) {
        console.warn('Invoices fetch fallback:', e);
      }

      // 2. Fetch Customer Ledger (Credit Payments & Balances)
      try {
        const ledgerPath = `/api/customers/${encodeURIComponent(customerId)}/ledger`;
        const ledgerRes = await window.safeApiCall(ledgerPath);
        if (ledgerRes) {
          ledgerEntries = ledgerRes.entries || [];
          currentBalance = parseFloat(ledgerRes.balance || 0);
        }
      } catch (e) {
        console.warn('Ledger fetch fallback:', e);
      }

      // Combine records
      let combinedRecords = [];

      if (ledgerEntries && ledgerEntries.length > 0) {
        ledgerEntries.forEach(entry => {
          const isPayment = entry.entry_type === 'payment';
          const isOpening = entry.entry_type === 'opening';

          let receiptNum = entry.payment_receipt;
          if (!receiptNum && entry.notes) {
            const m = entry.notes.match(/\[(PAY-\d+-\d+)\]/);
            if (m) receiptNum = m[1];
          }
          if (!receiptNum) {
            receiptNum = isPayment ? 'PAYMENT' : (isOpening ? 'OPENING' : 'REC');
          }

          combinedRecords.push({
            id: entry.id,
            number: receiptNum,
            date: entry.created_at,
            type: isPayment ? 'Payment Collection' : (isOpening ? 'Opening Balance' : 'Ledger Entry'),
            notes: entry.notes || '-',
            totalBilled: parseFloat(entry.debit || 0),
            amountPaid: parseFloat(entry.credit || 0),
            runningBalance: parseFloat(entry.balance || 0),
            status: isPayment ? 'Paid' : (parseFloat(entry.balance || 0) <= 0 ? 'Paid' : 'Unpaid')
          });
        });
      }

      // Also merge invoices if not already present
      invoices.forEach(inv => {
        const invNum = inv.invoice_number || `#INV-${(inv.id || '').substring(0, 6).toUpperCase()}`;
        const exists = combinedRecords.some(r => r.number === invNum || r.id === inv.id);
        if (!exists) {
          const grandTotal = parseFloat(inv.grand_total || inv.total_amount || 0);
          const amountPaid = parseFloat(inv.amount_paid || 0);
          const balance = Math.max(0, grandTotal - amountPaid);
          combinedRecords.push({
            id: inv.id,
            number: invNum,
            date: inv.created_at,
            type: 'POS Invoice',
            notes: inv.notes || 'Sales Invoice',
            totalBilled: grandTotal,
            amountPaid: amountPaid,
            runningBalance: balance,
            status: balance <= 0 ? 'Paid' : 'Unpaid'
          });
        }
      });

      // Sort descending by date
      combinedRecords.sort((a, b) => new Date(b.date || 0) - new Date(a.date || 0));

      if (combinedRecords.length === 0) {
        tbody.innerHTML = `
          <tr>
            <td colspan="8" class="empty-state-cell" style="text-align: center; padding: 48px 24px;">
              <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px;">
                <div style="width: 48px; height: 48px; border-radius: 50%; background: var(--surface-container-low); display: flex; align-items: center; justify-content: center; color: var(--muted); font-size: 1.4rem;">🧾</div>
                <div style="font-size: 1rem; font-weight: 600; color: var(--text-strong);">No billing history found</div>
                <div style="font-size: 0.88rem; color: var(--muted); max-width: 320px;">This customer hasn't been billed or made payments yet.</div>
              </div>
            </td>
          </tr>`;
        document.getElementById('cbInvoiceCount').textContent = '0';
        document.getElementById('cbTotalBilled').textContent = '₹0.00';
        document.getElementById('cbTotalPaid').textContent = '₹0.00';
        document.getElementById('cbBalanceDue').textContent = '₹0.00';
        return;
      }

      let totalBilledSum = 0;
      let totalPaidSum = 0;

      let html = '';
      combinedRecords.forEach(rec => {
        totalBilledSum += rec.totalBilled;
        totalPaidSum += rec.amountPaid;

        const dateStr = rec.date ? new Date(rec.date).toLocaleString('en-IN', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : '--';

        html += `
          <tr style="border-bottom: 1px solid var(--border);">
            <td style="padding: 16px 24px; font-weight: 600; font-family: var(--mono, monospace); color: var(--accent);">${rec.number}</td>
            <td style="padding: 16px 24px; color: var(--text); font-size: 14px;">${dateStr}</td>
            <td style="padding: 16px 24px; color: var(--text); font-size: 14px;">
              <span style="font-weight: 600; color: var(--text-strong);">${rec.type}</span>
              ${rec.notes ? `<div style="font-size: 0.78rem; color: var(--muted); margin-top: 2px;">${rec.notes}</div>` : ''}
            </td>
            <td style="padding: 16px 24px; text-align: right; font-weight: 600; color: var(--text-strong); font-size: 14px;">${formatCurrency(rec.totalBilled)}</td>
            <td style="padding: 16px 24px; text-align: right; font-weight: 600; color: var(--ok, #10b981); font-size: 14px;">${formatCurrency(rec.amountPaid)}</td>
            <td style="padding: 16px 24px; text-align: right; font-weight: 600; color: ${rec.runningBalance > 0 ? 'var(--danger, #ef4444)' : 'var(--muted)'}; font-size: 14px;">${formatCurrency(rec.runningBalance)}</td>
            <td style="padding: 16px 24px; text-align: center;">
              <span class="status-badge ${rec.status === 'Paid' ? 'status-paid' : 'status-unpaid'}">
                ${rec.status}
              </span>
            </td>
            <td style="padding: 16px 24px; text-align: right;">
              <button type="button" class="btn btn-sm btn-outline" onclick="openReceiptOffcanvas('${rec.number}', '${rec.type}', '${rec.totalBilled}', '${rec.amountPaid}', '${rec.runningBalance}', '${dateStr}', '${(rec.notes || '').replace(/'/g, "\\'")}')" style="padding: 4px 10px; font-size: 0.78rem;">
                View Receipt
              </button>
            </td>
          </tr>
        `;
      });

      tbody.innerHTML = html;
      document.getElementById('cbInvoiceCount').textContent = combinedRecords.length;
      document.getElementById('cbTotalBilled').textContent = formatCurrency(totalBilledSum);
      document.getElementById('cbTotalPaid').textContent = formatCurrency(totalPaidSum);
      document.getElementById('cbBalanceDue').textContent = formatCurrency(currentBalance);
    } catch (err) {
      console.error('Failed to load customer bills:', err);
      tbody.innerHTML = `<tr><td colspan="8" style="text-align: center; padding: 40px; color: var(--danger);">Failed to load transaction history: ${err.message}</td></tr>`;
    }
  }

  function initPage() {
    loadCustomerBillsPage();
    if (customerId) {
      // Defer summary load so loadCustomerBillsPage renders first
      setTimeout(() => loadLedgerSummary(customerId), 100);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPage);
  } else {
    initPage();
  }
</script>

<script>
// ── Ledger Filter Tabs ──────────────────────────────────────────────────────
let _ledgerFilter = '';
let _ledgerOffset = 0;
const LEDGER_PAGE_SIZE = 50;

window.setLedgerFilter = function(type) {
  _ledgerFilter = type;
  _ledgerOffset = 0;

  document.querySelectorAll('.ledger-tab').forEach(btn => {
    const isActive = btn.dataset.type === type;
    btn.classList.toggle('btn-primary', isActive);
    btn.classList.toggle('btn-outline', !isActive);
  });

  loadLedgerPage();
};

async function loadLedgerPage() {
  const tbody  = document.getElementById('customerBillsTbody');
  const params = new URLSearchParams();
  params.set('limit',  LEDGER_PAGE_SIZE);
  params.set('offset', _ledgerOffset);
  if (_ledgerFilter) params.set('type', _ledgerFilter);

  const customerId = new URLSearchParams(window.location.search).get('customer_id');
  if (!customerId) return;

  try {
    tbody.innerHTML = `<tr><td colspan="8" style="text-align:center; padding:32px; color:var(--muted);">Loading...</td></tr>`;
    const data = await window.safeApiCall(`/api/customers/${encodeURIComponent(customerId)}/ledger/entries?${params}`);
    const entries = data.entries || [];

    if (!entries.length) {
      tbody.innerHTML = `<tr><td colspan="8" style="text-align:center; padding:40px; color:var(--muted);">No ${_ledgerFilter || ''} entries found.</td></tr>`;
      return;
    }

    const typeLabels  = { invoice: 'Invoice', payment: 'Payment', return: 'Return', opening: 'Opening Balance' };
    const typeBadge   = { invoice: '#6366f1', payment: '#10b981', return: '#ef4444', opening: '#f59e0b' };

    tbody.innerHTML = entries.map(e => {
      const dateStr = e.created_at ? new Date(e.created_at).toLocaleString('en-IN', { year:'numeric', month:'short', day:'numeric', hour:'2-digit', minute:'2-digit' }) : '--';
      const label   = typeLabels[e.entry_type] || e.entry_type;
      const color   = typeBadge[e.entry_type]  || '#888';

      return `
        <tr style="border-bottom: 1px solid var(--border);">
          <td style="padding: 14px 20px; font-family: var(--mono, monospace); font-weight: 600; color: var(--accent); font-size: 0.82rem; white-space:nowrap;">
            ${e.payment_receipt || (e.invoice_id ? '#' + e.invoice_id.slice(-6).toUpperCase() : '-')}
          </td>
          <td style="padding: 14px 20px; color: var(--muted); font-size: 0.82rem; white-space:nowrap;">${dateStr}</td>
          <td style="padding: 14px 20px; font-size: 0.84rem;">
            <div style="color: var(--text-strong); font-weight: 600;">${label}</div>
            ${e.notes ? `<div style="font-size:0.75rem; color:var(--muted); margin-top:2px;">${e.notes}</div>` : ''}
          </td>
          <td style="padding: 14px 20px; text-align:right; font-weight: 600; color: ${e.debit > 0 ? 'var(--danger,#ef4444)' : 'var(--muted)'}; font-size: 0.84rem; white-space:nowrap;">
            ${e.debit > 0 ? '₹' + Number(e.debit).toLocaleString('en-IN', {minimumFractionDigits:2}) : '—'}
          </td>
          <td style="padding: 14px 20px; text-align:right; font-weight: 600; color: ${e.credit > 0 ? 'var(--ok,#10b981)' : 'var(--muted)'}; font-size: 0.84rem; white-space:nowrap;">
            ${e.credit > 0 ? '₹' + Number(e.credit).toLocaleString('en-IN', {minimumFractionDigits:2}) : '—'}
          </td>
          <td style="padding: 14px 20px; text-align:right; font-weight: 700; color: ${e.balance > 0 ? 'var(--danger,#ef4444)' : 'var(--muted)'}; font-size: 0.84rem; white-space:nowrap;">
            ₹${Number(e.balance).toLocaleString('en-IN', {minimumFractionDigits:2})}
          </td>
          <td style="padding: 14px 20px; text-align:center;">
            <span style="display:inline-block; padding:3px 10px; border-radius:99px; font-size:0.72rem; font-weight:700; background:${color}22; color:${color}; border:1px solid ${color}44;">
              ${label}
            </span>
          </td>
          <td style="padding: 14px 20px; text-align:right;">
            ${e.invoice_id
              ? `<a href="/customer-bills?invoice_id=${encodeURIComponent(e.invoice_id)}&customer_id=${encodeURIComponent(customerId)}" class="btn btn-sm btn-outline" style="padding:3px 8px; font-size:0.75rem;">View Invoice</a>`
              : `<button type="button" class="btn btn-sm btn-outline" onclick="openReceiptOffcanvas('${e.payment_receipt || label}', '${label}', '${e.debit}', '${e.credit}', '${e.balance}', '${dateStr}', '${(e.notes || '').replace(/'/g, "\\'")}')" style="padding:3px 8px; font-size:0.75rem;">View Receipt</button>`}
          </td>
        </tr>`;
    }).join('');

    renderLedgerPagination(data.total || entries.length);
  } catch (err) {
    tbody.innerHTML = `<tr><td colspan="8" style="text-align:center; padding:32px; color:var(--danger);">Failed to load entries: ${err.message}</td></tr>`;
  }
}

function renderLedgerPagination(total) {
  const container = document.getElementById('ledgerPaginationControls');
  if (!container) return;
  const totalPages = Math.ceil(total / LEDGER_PAGE_SIZE);
  if (totalPages <= 1) { container.style.display = 'none'; return; }
  container.style.display = 'flex';
  const currentPage = Math.floor(_ledgerOffset / LEDGER_PAGE_SIZE) + 1;
  container.innerHTML = `
    <button class="btn btn-sm btn-outline" onclick="_ledgerOffset=Math.max(0,_ledgerOffset-${LEDGER_PAGE_SIZE}); loadLedgerPage();" ${currentPage <= 1 ? 'disabled' : ''} style="padding:4px 10px; font-size:0.78rem;">← Prev</button>
    <span style="font-size:0.78rem; color:var(--muted); padding: 0 8px;">Page ${currentPage} of ${totalPages} · ${total} entries</span>
    <button class="btn btn-sm btn-outline" onclick="_ledgerOffset=_ledgerOffset+${LEDGER_PAGE_SIZE}; loadLedgerPage();" ${currentPage >= totalPages ? 'disabled' : ''} style="padding:4px 10px; font-size:0.78rem;">Next →</button>
  `;
}

// ── Load Ledger Summary (/api/customers/{id}/ledger/summary) ──────────────────
async function loadLedgerSummary(customerId) {
  try {
    const data = await window.safeApiCall(`/api/customers/${encodeURIComponent(customerId)}/ledger/summary`);

    const fmt = v => '₹' + Number(v || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 });

    document.getElementById('cbInvoiceCount').textContent = data.total_invoices ?? '--';
    document.getElementById('cbTotalBilled').textContent  = fmt(data.total_purchases);
    document.getElementById('cbTotalPaid').textContent    = fmt(data.total_paid);
    document.getElementById('cbBalanceDue').textContent   = fmt(data.current_balance);

    if (data.credit_limit > 0) {
      const card = document.getElementById('cbCreditLimitCard');
      if (card) card.style.display = '';
      const el = document.getElementById('cbCreditLimit');
      const av = document.getElementById('cbAvailableCredit');
      if (el) el.textContent = fmt(data.credit_limit);
      if (av) av.textContent = fmt(data.available_credit ?? 0);
    }

    // Show Collect + Return buttons after summary loads
    const collectBtn = document.getElementById('billsCollectBtn');
    const returnBtn  = document.getElementById('billsReturnBtn');
    if (collectBtn) collectBtn.style.display = '';
    if (returnBtn)  returnBtn.style.display  = '';

    // Store for modal use
    window._billsCustomerId   = customerId;
    window._billsCustomerName = document.getElementById('cbCustomerNameTitle')?.textContent || 'Customer';
  } catch (e) {
    console.warn('Ledger summary load failed:', e);
  }
}

// ── Collect / Return Actions from Bills Page ─────────────────────────────────
window.openBillsPaymentModal = function() {
  const cid  = window._billsCustomerId;
  const cname = window._billsCustomerName;
  if (!cid) return;

  const idInput  = document.getElementById('payCustId');
  const nameEl   = document.getElementById('payCustName');
  const amtEl    = document.getElementById('payOutstanding');
  const amtInput = document.getElementById('payAmount');
  const notesIn  = document.getElementById('payNotes');
  const feedback = document.getElementById('payLimitFeedback');

  if (idInput)  idInput.value = cid;
  if (nameEl)   nameEl.textContent = cname;
  if (amtEl)    amtEl.textContent = document.getElementById('cbBalanceDue')?.textContent || '₹0';
  if (amtInput) amtInput.value = '';
  if (notesIn)  notesIn.value = '';
  if (feedback) feedback.textContent = '';

  if (typeof window.openModal === 'function') window.openModal('paymentModal');
};

window.openBillsReturnModal = function() {
  const cid   = window._billsCustomerId || new URLSearchParams(window.location.search).get('customer_id');
  const cname = window._billsCustomerName || 'Customer';
  const urlParams = new URLSearchParams(window.location.search);
  const invId = urlParams.get('invoice_id');

  if (typeof window.openReturnModal === 'function') {
    window.openReturnModal(cid, cname, invId);
  } else if (typeof window.openModal === 'function') {
    window.openModal('creditReturnModal');
  }
};

</script>
