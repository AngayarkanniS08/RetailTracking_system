/**
 * customers.js — Customer Credit (Kadan) page controller
 */

import {
  createCustomerApi,
  recordCreditPaymentApi,
} from '../services/credit.service.js';


let _customers = [];
let _searchQuery = '';
let _searchTimer = null;
let _currentPage = 1;
let _totalPages  = 1;
let _totalCount  = 0;
const PAGE_LIMIT = 20;

export async function initCustomerCredit(page = 1) {
  window.viewCustomerBills = viewCustomerBills;
  _currentPage = page;

  const tbody = document.querySelector('#creditTable tbody');
  const emptyState = document.getElementById('creditEmptyState');

  if (tbody) {
    tbody.innerHTML =
      '<tr><td colspan="9" style="text-align:center; padding: 32px; color: var(--muted);">Loading credit accounts...</td></tr>';
  }
  if (emptyState) emptyState.style.display = 'none';

  try {
    const url = `/api/customers?page=${page}&limit=${PAGE_LIMIT}${_searchQuery ? '&search=' + encodeURIComponent(_searchQuery) : ''}`;
    const response = await (window.apiRequest ? window.apiRequest(url) : fetch(url, { credentials: 'same-origin' }).then(r => r.json()));

    if (Array.isArray(response)) {
      _customers = response;
      _totalCount = response.length;
      _totalPages = 1;
    } else if (response && Array.isArray(response.data)) {
      _customers = response.data;
      const pg = response.pagination || {};
      _totalCount = pg.total ?? response.data.length;
      _totalPages = pg.total_pages ?? Math.ceil(_totalCount / PAGE_LIMIT);
    } else if (response && Array.isArray(response.customers)) {
      _customers = response.customers;
      _totalCount = _customers.length;
      _totalPages = 1;
    } else {
      _customers = [];
      _totalCount = 0;
      _totalPages = 1;
    }
    renderCreditTable();
    renderPagination();
  } catch (err) {
    console.error('Failed to load customer credit records:', err);
    _customers = [];
    renderCreditTable();
  }
}

export function renderCreditTable() {
  const tbody = document.querySelector('#creditTable tbody');
  const emptyState = document.getElementById('creditEmptyState');
  const table = document.getElementById('creditTable');

  if (!tbody || !emptyState || !table) return;

  const filtered = _customers.filter((c) => {
    if (!_searchQuery) return true;
    const name = (c.name || '').toLowerCase();
    const phone = (c.phone || '').toLowerCase();
    const q = _searchQuery.toLowerCase();
    return name.includes(q) || phone.includes(q);
  });

  if (filtered.length === 0) {
    table.style.display = 'none';
    emptyState.style.display = 'flex';
    tbody.innerHTML = '';
    return;
  }

  table.style.display = 'table';
  emptyState.style.display = 'none';
  tbody.innerHTML = '';

  filtered.forEach((c) => {
    const rawId = String(c.id || c.customerId || '');
    const shortId = rawId.length > 8 ? rawId.slice(-6).toUpperCase() : rawId;

    const totalPurchases  = Number(c.total_purchases  ?? c.totalPurchases ?? c.purchases ?? 0);
    const totalPaid       = Number(c.total_paid       ?? c.totalPaid      ?? c.paid      ?? 0);
    const outstandingBalance = Number(c.balance ?? c.current_balance ?? c.outstandingBalance ?? c.outstanding_balance ?? 0);
    const creditLimit     = Number(c.credit_limit ?? c.creditLimit ?? 0);
    const isCleared       = outstandingBalance <= 0;

    // Credit limit bar logic
    const hasLimit    = creditLimit > 0;
    const usedPct     = hasLimit ? Math.min(100, (outstandingBalance / creditLimit) * 100) : 0;
    const barColor    = usedPct >= 90 ? '#ef4444' : usedPct >= 70 ? '#f59e0b' : '#10b981';
    const available   = hasLimit ? Math.max(0, creditLimit - outstandingBalance) : null;

    const limitCell = hasLimit ? `
      <div style="min-width:140px;">
        <div style="display:flex; justify-content:space-between; font-size:0.72rem; color:var(--muted); margin-bottom:4px;">
          <span style="color:${barColor}; font-weight:600;">₹${outstandingBalance.toLocaleString('en-IN',{minimumFractionDigits:0,maximumFractionDigits:0})} used</span>
          <span>₹${creditLimit.toLocaleString('en-IN',{minimumFractionDigits:0,maximumFractionDigits:0})}</span>
        </div>
        <div style="height:5px; background:var(--border); border-radius:99px; overflow:hidden;">
          <div style="height:100%; width:${usedPct.toFixed(1)}%; background:${barColor}; border-radius:99px; transition:width 0.4s;"></div>
        </div>
        <div style="font-size:0.7rem; color:var(--muted); margin-top:3px;">₹${available.toLocaleString('en-IN',{minimumFractionDigits:0,maximumFractionDigits:0})} available</div>
      </div>` : `<span style="font-size:0.78rem; color:var(--muted);">No limit</span>`;

    const tr = document.createElement('tr');
    tr.style.borderBottom = '1px solid var(--border)';
    tr.style.transition = 'background 0.15s';
    tr.innerHTML = `
      <td style="padding: 14px 16px; font-family: var(--font-mono, monospace); font-weight: 600; color: var(--accent); white-space:nowrap;">#${shortId}</td>
      <td style="padding: 14px 16px; font-weight: 600; color: var(--text-strong);">${c.name || 'Unknown'}</td>
      <td style="padding: 14px 16px; color: var(--muted); white-space:nowrap;">${c.phone || '-'}</td>
      <td style="padding: 14px 16px; font-weight: 500; white-space:nowrap;">₹${totalPurchases.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
      <td style="padding: 14px 16px; color: var(--success, #10b981); font-weight: 500; white-space:nowrap;">₹${totalPaid.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
      <td style="padding: 14px 16px; color: ${isCleared ? 'var(--muted)' : 'var(--danger,#ef4444)'}; font-weight: 700; white-space:nowrap;">₹${outstandingBalance.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
      <td style="padding: 14px 16px;">${limitCell}</td>
      <td style="padding: 14px 16px;">
        <span class="badge rounded-pill ${isCleared ? 'text-bg-success' : 'text-bg-warning'}" style="padding: 0.35em 0.75em; font-size: 0.75rem; font-weight: 700;">
          ${isCleared ? 'Cleared' : 'Pending'}
        </span>
      </td>
      <td style="padding: 14px 16px; text-align: right; white-space:nowrap;">
        <button class="btn btn-sm btn-outline" onclick="viewCustomerBills('${c.id || c.customerId}', '${(c.name || 'Customer').replace(/'/g, "\\'")}')" style="padding: 4px 10px; font-size: 0.78rem; margin-right: 4px;">Bills</button>
        <button class="btn btn-sm btn-outline" onclick="openPaymentModal('${c.id || c.customerId}')" style="padding: 4px 10px; font-size: 0.78rem; margin-right: 4px;">Collect</button>
        <button class="btn btn-sm" onclick="openReturnModal('${c.id || c.customerId}', '${(c.name || 'Customer').replace(/'/g, "\\'")}')" style="padding: 4px 10px; font-size: 0.78rem; background: rgba(239,68,68,0.1); color: var(--danger,#ef4444); border: 1px solid rgba(239,68,68,0.3); border-radius: var(--radius-sm); font-weight:600;">Return</button>
      </td>
    `;
    tbody.appendChild(tr);
  });

  // Update header subtitle
  const headerCount = document.getElementById('creditHeaderCount');
  if (headerCount) headerCount.textContent = `${_totalCount} customer${_totalCount !== 1 ? 's' : ''} · Page ${_currentPage} of ${_totalPages}`;
}

// Global search input listener — resets to page 1 and re-fetches
window.onCreditSearchInput = function () {
  clearTimeout(_searchTimer);
  _searchTimer = setTimeout(() => {
    const input = document.getElementById('creditSearch');
    _searchQuery = input ? input.value.trim() : '';
    initCustomerCredit(1);
  }, 300);
};

// ── Pagination ────────────────────────────────────────────────
export function renderPagination() {
  const container = document.getElementById('creditPaginationControls');
  if (!container) return;

  if (_totalPages <= 1) {
    container.style.display = 'none';
    return;
  }

  container.style.display = 'flex';
  container.style.alignItems = 'center';
  container.style.gap = '6px';
  container.style.justifyContent = 'center';

  const btn = (label, page, disabled, active) => {
    const b = document.createElement('button');
    b.textContent = label;
    b.className = 'btn btn-sm ' + (active ? 'btn-primary' : 'btn-outline');
    b.style.cssText = 'min-width:36px; padding:4px 10px; font-size:0.8rem; font-weight:600;';
    b.disabled = disabled;
    if (!disabled) b.onclick = () => initCustomerCredit(page);
    return b;
  };

  container.innerHTML = '';
  container.appendChild(btn('‹ Prev', _currentPage - 1, _currentPage <= 1, false));

  // Show at most 5 page buttons centred on current page
  let start = Math.max(1, _currentPage - 2);
  let end   = Math.min(_totalPages, start + 4);
  start = Math.max(1, end - 4);

  for (let p = start; p <= end; p++) {
    container.appendChild(btn(String(p), p, false, p === _currentPage));
  }

  container.appendChild(btn('Next ›', _currentPage + 1, _currentPage >= _totalPages, false));

  const info = document.createElement('span');
  info.textContent = `${_totalCount} customers`;
  info.style.cssText = 'font-size:0.78rem; color:var(--muted); margin-left:8px;';
  container.appendChild(info);
}

// Global modal action handlers
window.saveCustomer = async function () {
  const nameInput = document.getElementById('custName');
  const phoneInput = document.getElementById('custPhone');
  const limitInput = document.getElementById('custCreditLimit');
  const balanceInput = document.getElementById('custOpeningBalance');

  const name = nameInput ? nameInput.value.trim() : '';
  const phone = phoneInput ? phoneInput.value.trim() : '';
  const creditLimit = limitInput ? parseFloat(limitInput.value) || 0 : 0;
  const openingBalance = balanceInput ? parseFloat(balanceInput.value) || 0 : 0;

  if (!name) {
    if (window.notify) window.notify.warning('Please enter a valid customer name.');
    else showToast('Please enter a valid customer name.', 'warn');
    return;
  }
  if (!phone) {
    if (window.notify) window.notify.warning('Please enter a valid phone number.');
    else showToast('Please enter a valid phone number.', 'warn');
    return;
  }

  try {
    const payload = {
      name,
      phone,
      credit_limit: creditLimit,
      opening_balance: openingBalance,
    };
    await createCustomerApi(payload);

    if (window.notify) window.notify.success('Customer created successfully');
    else showToast('Customer created successfully', 'ok');

    // Clear form inputs
    if (nameInput) nameInput.value = '';
    if (phoneInput) phoneInput.value = '';
    if (limitInput) limitInput.value = '';
    if (balanceInput) balanceInput.value = '';

    // Close modal dialog
    if (typeof window.closeModal === 'function') {
      window.closeModal('addCustomerModal');
    }

    // Refresh customers list from page 1 (new customer always on first page)
    await initCustomerCredit(1);
  } catch (err) {
    console.error('Failed to create customer:', err);
    if (window.notify) window.notify.error(err.message || 'Failed to create customer. Please try again.');
    else showToast(err.message || 'Failed to create customer. Please try again.', 'danger');
  }
};

window.openPaymentModal = function (customerId) {
  const customer = _customers.find((c) => (c.id || c.customerId) == customerId);
  if (!customer) return;

  const outstanding = Number(customer.balance ?? customer.current_balance ?? customer.outstandingBalance ?? customer.outstanding_balance ?? 0);

  const idInput = document.getElementById('payCustId');
  const nameEl = document.getElementById('payCustName');
  const outEl = document.getElementById('payOutstanding');
  const amountInput = document.getElementById('payAmount');
  const notesInput = document.getElementById('payNotes');

  if (idInput) idInput.value = customer.id || customer.customerId;
  if (nameEl) nameEl.textContent = customer.name || '-';
  if (outEl) outEl.textContent = `₹${outstanding.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
  if (amountInput) amountInput.value = '';
  if (notesInput) notesInput.value = '';

  if (typeof window.openModal === 'function') {
    window.openModal('paymentModal');
  }
};

window.processPayment = async function () {
  const idInput = document.getElementById('payCustId');
  const amountInput = document.getElementById('payAmount');
  const notesInput = document.getElementById('payNotes');

  const customerId = idInput ? idInput.value : '';
  const amount = amountInput ? parseFloat(amountInput.value) || 0 : 0;
  const notes = notesInput ? notesInput.value.trim() : '';

  if (!customerId) {
    if (window.notify) window.notify.warning('Invalid customer selected.');
    else showToast('Invalid customer selected.', 'warn');
    return;
  }
  if (amount <= 0) {
    if (window.notify) window.notify.warning('Please enter a payment amount greater than ₹0.');
    else showToast('Please enter a payment amount greater than ₹0.', 'warn');
    return;
  }

  try {
    await recordCreditPaymentApi(customerId, { amount, notes });

    if (window.notify) window.notify.success('Payment recorded successfully');
    else showToast('Payment recorded successfully', 'ok');

    if (typeof window.closeModal === 'function') {
      window.closeModal('paymentModal');
    }

    await initCustomerCredit(_currentPage);
  } catch (err) {
    console.error('Failed to record payment:', err);
    if (window.notify) window.notify.error(err.message || 'Failed to record payment. Please try again.');
    else showToast(err.message || 'Failed to record payment. Please try again.', 'danger');
  }
};

window.viewCustomerBills = function (customerId, customerName) {
  window.location.href = `/customer-bills?customer_id=${encodeURIComponent(customerId)}&name=${encodeURIComponent(customerName || 'Customer')}`;
};

// ── Credit Return ──────────────────────────────────────────────
window.openReturnModal = async function (customerId, customerName, invoiceRef) {
  const customer = _customers.find((c) => (c.id || c.customerId) == customerId);

  const idInput     = document.getElementById('returnCustId');
  const nameEl      = document.getElementById('returnCustName');
  const balanceEl   = document.getElementById('returnCustBalance');
  const amountInput  = document.getElementById('returnAmount');
  const selectEl    = document.getElementById('returnInvoiceSelect');
  const hiddenRef   = document.getElementById('returnInvoiceRef');
  const manualGroup = document.getElementById('manualInvoiceGroup');
  const manualInput = document.getElementById('returnInvoiceManual');
  const infoEl      = document.getElementById('returnInvoiceInfo');
  const notesInput  = document.getElementById('returnNotes');

  if (idInput) idInput.value = customerId;
  if (nameEl)  nameEl.textContent = customerName || 'Customer';

  if (customer) {
    const bal = Number(customer.balance ?? customer.current_balance ?? customer.outstandingBalance ?? 0);
    if (balanceEl) balanceEl.textContent = `₹${bal.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
  }

  if (amountInput) amountInput.value = '';
  if (notesInput)  notesInput.value = '';
  if (hiddenRef)   hiddenRef.value = invoiceRef || '';
  if (manualGroup) manualGroup.style.display = 'none';
  if (manualInput) manualInput.value = '';
  if (infoEl)      infoEl.innerHTML = '';

  if (selectEl) {
    selectEl.innerHTML = '<option value="">Loading customer invoices...</option>';
    try {
      const url = `/api/invoices?customer_id=${encodeURIComponent(customerId)}&limit=100`;
      const data = typeof window.apiRequest === 'function'
        ? await window.apiRequest(url)
        : await (typeof window.fetchWithAuth === 'function'
            ? (await window.fetchWithAuth(url)).json()
            : (await fetch(url, { credentials: 'same-origin' })).json());

      const invoices = data?.data || (Array.isArray(data) ? data : data?.invoices || []);
      window._currentCustomerInvoices = invoices;

      if (!invoices.length) {
        selectEl.innerHTML = `
          <option value="">-- No POS Invoices Found --</option>
          <option value="__manual__">✏️ Enter Invoice Number Manually</option>`;
      } else {
        let opts = `<option value="">-- Select an Invoice (${invoices.length} available) --</option>`;
        invoices.forEach(inv => {
          const num = inv.invoice_number || `#INV-${(inv.id || '').substring(0, 6).toUpperCase()}`;
          const amt = Number(inv.grand_total || inv.total_amount || 0);
          const date = inv.created_at ? new Date(inv.created_at).toLocaleDateString('en-IN') : '';
          const selected = (invoiceRef && (inv.id === invoiceRef || inv.invoice_number === invoiceRef)) ? 'selected' : '';
          opts += `<option value="${inv.id}" data-num="${num}" data-amount="${amt}" ${selected}>${num} — ₹${amt.toLocaleString('en-IN',{minimumFractionDigits:2})} (${date})</option>`;
        });
        opts += `<option value="__manual__">✏️ Other / Enter Manually...</option>`;
        selectEl.innerHTML = opts;

        // Trigger change handler if auto-selected
        if (selectEl.value) {
          window.onReturnInvoiceSelectChange(selectEl);
        }
      }
    } catch (e) {
      console.warn('Failed to fetch invoices for return modal:', e);
      selectEl.innerHTML = `
        <option value="">-- Failed to load invoices --</option>
        <option value="__manual__" selected>✏️ Enter Invoice Number Manually</option>`;
      if (manualGroup) manualGroup.style.display = 'block';
    }
  }

  if (typeof window.openModal === 'function') window.openModal('creditReturnModal');
};

window.onReturnInvoiceSelectChange = function (selectEl) {
  const val         = selectEl.value;
  const hiddenRef   = document.getElementById('returnInvoiceRef');
  const manualGroup = document.getElementById('manualInvoiceGroup');
  const amountInput  = document.getElementById('returnAmount');
  const infoEl      = document.getElementById('returnInvoiceInfo');

  if (val === '__manual__') {
    if (manualGroup) manualGroup.style.display = 'block';
    if (hiddenRef)   hiddenRef.value = '';
    if (infoEl)      infoEl.innerHTML = '<span style="color:var(--muted);">Please enter the invoice reference manually above.</span>';
    return;
  }

  if (manualGroup) manualGroup.style.display = 'none';

  const selectedOpt = selectEl.options[selectEl.selectedIndex];
  if (!val || !selectedOpt) {
    if (hiddenRef)  hiddenRef.value = '';
    if (infoEl)     infoEl.innerHTML = '';
    return;
  }

  const num = selectedOpt.dataset.num || val;
  const amt = parseFloat(selectedOpt.dataset.amount || '0');

  if (hiddenRef)  hiddenRef.value = num;
  if (amountInput && amt > 0 && (!amountInput.value || parseFloat(amountInput.value) <= 0)) {
    amountInput.value = amt;
  }

  if (infoEl && amt > 0) {
    infoEl.innerHTML = `<span style="color:var(--accent); font-weight:600;">✓ Invoice Total: ₹${amt.toLocaleString('en-IN',{minimumFractionDigits:2})}</span> — Return amount pre-filled.`;
  }
};

window.processReturn = async function () {
  const idInput     = document.getElementById('returnCustId');
  const amountInput  = document.getElementById('returnAmount');
  const selectEl    = document.getElementById('returnInvoiceSelect');
  const hiddenRef   = document.getElementById('returnInvoiceRef');
  const manualInput = document.getElementById('returnInvoiceManual');
  const notesInput  = document.getElementById('returnNotes');

  const customerId = idInput ? idInput.value : '';
  const amount     = amountInput ? parseFloat(amountInput.value) || 0 : 0;
  let invoiceRef   = hiddenRef ? hiddenRef.value.trim() : '';

  if (selectEl && selectEl.value === '__manual__' && manualInput) {
    invoiceRef = manualInput.value.trim();
  }

  const notes = notesInput ? notesInput.value.trim() : '';

  if (!customerId) {
    if (window.notify) window.notify.warning('Invalid customer.');
    else showToast('Invalid customer.', 'warn');
    return;
  }
  if (amount <= 0) {
    if (window.notify) window.notify.warning('Please enter a return amount greater than ₹0.');
    else showToast('Please enter a return amount greater than ₹0.', 'warn');
    return;
  }
  if (!invoiceRef) {
    if (window.notify) window.notify.warning('Please select or enter an invoice reference.');
    else showToast('Please select or enter an invoice reference.', 'warn');
    return;
  }

  const btn = document.getElementById('processReturnBtn');
  if (btn) { btn.disabled = true; btn.textContent = 'Processing...'; }

  try {
    const url = `/api/customers/${encodeURIComponent(customerId)}/credit-return`;
    const payload = { invoice_id: invoiceRef, amount, notes: notes || `Return for invoice ${invoiceRef}` };

    if (typeof window.apiRequest === 'function') {
      await window.apiRequest(url, { method: 'POST', body: JSON.stringify(payload) });
    } else if (typeof window.fetchWithAuth === 'function') {
      const res = await window.fetchWithAuth(url, { method: 'POST', body: JSON.stringify(payload) });
      if (!res.ok) {
        const err = await res.json();
        throw new Error(err.error || 'Return failed');
      }
    } else {
      const token = localStorage.getItem('auth_token');
      const res = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          ...(token ? { Authorization: `Bearer ${token}` } : {})
        },
        body: JSON.stringify(payload)
      });
      if (!res.ok) {
        const err = await res.json();
        throw new Error(err.error || 'Return failed');
      }
    }

    if (window.notify) window.notify.success('Return processed successfully');
    else showToast('Return processed successfully', 'ok');

    if (typeof window.closeModal === 'function') window.closeModal('creditReturnModal');
    await initCustomerCredit(_currentPage);
  } catch (err) {
    console.error('Failed to process return:', err);
    if (window.notify) window.notify.error(err.message || 'Failed to process return. Please try again.');
    else showToast(err.message || 'Failed to process return. Please try again.', 'danger');
  } finally {
    if (btn) { btn.disabled = false; btn.textContent = 'Process Return'; }
  }
};

// ── Credit Limit Check (live feedback in payment modal) ───────
window.onPayAmountInput = async function () {
  const idInput     = document.getElementById('payCustId');
  const amountInput = document.getElementById('payAmount');
  const feedbackEl  = document.getElementById('payLimitFeedback');
  if (!idInput || !amountInput || !feedbackEl) return;

  const customerId = idInput.value;
  const amount     = parseFloat(amountInput.value) || 0;
  if (!customerId || amount <= 0) { feedbackEl.textContent = ''; return; }

  try {
    const url = `/api/customers/${encodeURIComponent(customerId)}/ledger/balance`;
    const data = typeof window.apiRequest === 'function'
      ? await window.apiRequest(url)
      : await (typeof window.fetchWithAuth === 'function'
          ? (await window.fetchWithAuth(url)).json()
          : (await fetch(url, { credentials: 'same-origin' })).json());

    const balance = data.balance ?? 0;
    const change  = Math.max(0, amount - balance);
    if (change > 0) {
      feedbackEl.innerHTML = `<span style="color:var(--success,#10b981);">✓ Change to return: ₹${change.toLocaleString('en-IN',{minimumFractionDigits:2})}</span>`;
    } else {
      feedbackEl.innerHTML = `<span style="color:var(--muted);">Remaining after payment: ₹${Math.max(0,balance-amount).toLocaleString('en-IN',{minimumFractionDigits:2})}</span>`;
    }
  } catch (_) {
    feedbackEl.textContent = '';
  }
};


