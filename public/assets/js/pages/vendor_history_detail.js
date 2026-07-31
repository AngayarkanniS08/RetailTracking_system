/**
 * vendor_history_detail.js — Vendor History Detail Page Controller
 * Reads the selected date (and optional vendor_id) from the URL, loads the
 * complete vendor history for that date from /api/vendors/history/detail,
 * renders KPI cards and the transactions table, and supports previous/next
 * day + date picker navigation via the `date` query parameter.
 */

import { apiRequest } from '../core/api.js';
import { formatCurrency, formatDate, formatDateTime } from '../utils/format.js';
import { logger } from '../core/logger.js';
import { showToast } from '../ui/toast.js';

let _cachedDetailData = null;
let _currentDate = new Date().toISOString().split('T')[0];
let _vendorId = new URLSearchParams(window.location.search).get('vendor_id') || '';

// Settlement modal state (never derive financial state from the DOM)
let _selectedPurchase = null;
let _isSubmitting = false;

function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
  }[c]));
}

function buildUrl(params) {
  if (_vendorId) params.set('vendor_id', _vendorId);
  return `${window.location.pathname}?${params.toString()}`;
}

window.navigateVendorHistoryDay = function (deltaDays) {
  const dt = new Date(_currentDate);
  dt.setDate(dt.getDate() + deltaDays);
  const newDateStr = dt.toISOString().split('T')[0];

  const params = new URLSearchParams(window.location.search);
  params.set('date', newDateStr);
  window.location.href = buildUrl(params);
};

window.onVendorHistoryDateChange = function (newDateStr) {
  if (!newDateStr) return;
  const params = new URLSearchParams(window.location.search);
  params.set('date', newDateStr);
  window.location.href = buildUrl(params);
};

export async function initVendorHistoryDetail() {
  const urlParams = new URLSearchParams(window.location.search);
  _currentDate = urlParams.get('date') || new Date().toISOString().split('T')[0];
  _vendorId = urlParams.get('vendor_id') || '';

  const datePicker = document.getElementById('vendorDetailDatePicker');
  if (datePicker) datePicker.value = _currentDate;

  const displayDateEl = document.getElementById('vendorDetailSelectedDate');
  if (displayDateEl) displayDateEl.textContent = formatDate(_currentDate);

  const displayDateBadge = document.getElementById('vendorDetailSelectedDateBadge');
  if (displayDateBadge) displayDateBadge.textContent = formatDate(_currentDate);

  await loadVendorHistoryDetail(_currentDate);
}

async function loadVendorHistoryDetail(date) {
  try {
    const params = new URLSearchParams({ date });
    if (_vendorId) params.set('vendor_id', _vendorId);

    const res = await apiRequest(`/api/vendors/history/detail?${params.toString()}`);
    const data = res?.data || res;

    _cachedDetailData = data;
    renderDetailKPIs(data.summary || {});
    filterAndRenderDetail();
  } catch (err) {
    logger.error('vendor_history_detail', 'Failed to load vendor history detail:', err);
    showDetailEmptyState(true);
  }
}

function renderDetailKPIs(summary) {
  const totalPurchasesEl = document.getElementById('kpiDetailTotalPurchases');
  const totalBilledEl = document.getElementById('kpiDetailTotalBilled');
  const totalPaidEl = document.getElementById('kpiDetailTotalPaid');
  const balanceDueEl = document.getElementById('kpiDetailBalanceDue');
  const avgPurchaseEl = document.getElementById('kpiDetailAvgPurchase');
  const badgeEl = document.getElementById('vendorDetailBadgeCount');

  const totalPurchases = summary.total_purchases || 0;

  if (totalPurchasesEl) totalPurchasesEl.textContent = String(totalPurchases);
  if (badgeEl) badgeEl.textContent = String(totalPurchases);
  if (totalBilledEl) totalBilledEl.textContent = formatCurrency(summary.total_amount || 0);
  if (totalPaidEl) totalPaidEl.textContent = formatCurrency(summary.total_paid || 0);
  if (balanceDueEl) balanceDueEl.textContent = formatCurrency(summary.balance_due || 0);
  if (avgPurchaseEl) avgPurchaseEl.textContent = formatCurrency(summary.avg_purchase_value || 0);
}

function getTimeFromRecord(record) {
  const raw = record.purchaseDate || record.purchase_date || record.created_at || '';
  if (!raw) return '—';
  const parts = String(raw).split(' ');
  if (parts.length > 1 && parts[1]) {
    const timePart = parts[1];
    const [hh, mm] = timePart.split(':');
    if (hh !== undefined) {
      const hours = parseInt(hh, 10);
      const suffix = hours >= 12 ? 'PM' : 'AM';
      const displayHour = ((hours % 12) || 12).toString().padStart(2, '0');
      return `${displayHour}:${mm || '00'} ${suffix}`;
    }
  }
  return formatDateTime(raw) || '—';
}

function renderVendorTransactions(purchases = []) {
  const tbody = document.querySelector('#vendorDetailTable tbody');
  if (!tbody) return;

  if (!purchases || purchases.length === 0) {
    showDetailEmptyState(true);
    tbody.innerHTML = '';
    return;
  }

  showDetailEmptyState(false);

  tbody.innerHTML = purchases.map((p) => {
    const status = (p.status || 'pending').toUpperCase();
    const getStatusBadgeClass = (st) => {
      switch (st) {
        case 'PAID': return 'bg-success';
        case 'PARTIAL': return 'bg-info text-dark';
        case 'PENDING':
        default: return 'bg-warning text-dark';
      }
    };

    const itemsSummary = Array.isArray(p.items) && p.items.length > 0
      ? p.items.map(i => `${i.product_name || 'Product'} (x${i.quantity})`).join(', ')
      : 'Stock Purchase Order';

    const total = parseFloat(p.totalAmount ?? p.amount ?? 0) || 0;
    const paid = parseFloat(p.amountPaid ?? 0) || 0;
    const balance = Math.max(0, total - paid);

    const actionCell = balance > 0
      ? `<button type="button" class="btn btn-xs btn-primary" style="padding: 4px 12px; font-size: 0.75rem; border-radius: 4px; font-weight: 600; cursor: pointer; white-space: nowrap;" onclick="openVendorPaymentModal('${p.id}')">Settle</button>`
      : `<span class="badge rounded-pill bg-success" style="font-size: 0.72rem;">Paid ✓</span>`;

    return `
      <tr style="border-bottom: 1px solid var(--border);">
        <td style="padding: 12px 16px; font-weight: 500; color: var(--muted); font-size: 0.82rem;">${getTimeFromRecord(p)}</td>
        <td style="padding: 12px 16px;">
          <div style="font-weight: 600; color: var(--text-strong);">${escapeHtml(p.vendorName || 'Vendor Purchase')}</div>
          ${p.vendorPhone ? `<div style="font-size: 0.75rem; color: var(--muted);">${escapeHtml(p.vendorPhone)}</div>` : ''}
        </td>
        <td style="padding: 12px 16px; max-width: 280px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--text-muted); font-size: 0.82rem;">
          ${escapeHtml(itemsSummary)}
        </td>
        <td style="padding: 12px 16px;">
          <span class="badge rounded-pill ${getStatusBadgeClass(status)}">${status}</span>
        </td>
        <td style="padding: 12px 16px; font-weight: 700; color: var(--text-strong);">${formatCurrency(total)}</td>
        <td style="padding: 12px 16px; font-weight: 600; color: var(--success);">${formatCurrency(paid)}</td>
        <td style="padding: 12px 16px; font-weight: 600; color: var(--warning, #d97706);">${formatCurrency(balance)}</td>
        <td style="padding: 12px 16px; text-align: right;">${actionCell}</td>
      </tr>
    `;
  }).join('');
}

function showDetailEmptyState(show) {
  const emptyState = document.getElementById('vendorDetailEmptyState');
  const tableContainer = document.querySelector('#vendorDetailTable')?.closest('.table-container');

  if (emptyState) emptyState.style.display = show ? 'flex' : 'none';
  if (tableContainer) tableContainer.style.display = show ? 'none' : 'block';
}

window.onVendorDetailSearchInput = function () {
  filterAndRenderDetail();
};

window.onVendorDetailStatusChange = function () {
  filterAndRenderDetail();
};

function filterAndRenderDetail() {
  if (!_cachedDetailData || !Array.isArray(_cachedDetailData.purchases)) return;

  const searchQuery = (document.getElementById('vendorDetailSearch')?.value || '').toLowerCase().trim();
  const statusFilter = (document.getElementById('vendorDetailStatusFilter')?.value || 'ALL').toUpperCase();

  const filtered = _cachedDetailData.purchases.filter((p) => {
    const name = (p.vendorName || '').toLowerCase();
    const phone = (p.vendorPhone || '').toLowerCase();
    const items = Array.isArray(p.items)
      ? p.items.map(i => `${i.product_name || ''} ${i.quantity || ''}`).join(' ').toLowerCase()
      : '';

    const matchesSearch = !searchQuery || name.includes(searchQuery) || phone.includes(searchQuery) || items.includes(searchQuery);
    const matchesStatus = statusFilter === 'ALL' || (p.status || 'pending').toUpperCase() === statusFilter;

    return matchesSearch && matchesStatus;
  });

  renderVendorTransactions(filtered);
}

window.initVendorHistoryDetail = initVendorHistoryDetail;

/**
 * ── Settlement workflow ─────────────────────────────────────────────────────
 * Opens the shared vendorPaymentModal pre-filled from the already-loaded row
 * data (no extra fetch). The backend remains the source of truth for balances.
 */

window.openVendorPaymentModal = function (purchaseId) {
  if (!_cachedDetailData || !Array.isArray(_cachedDetailData.purchases)) {
    showToast('Data is still loading. Please try again.', 'warning');
    return;
  }

  const purchase = _cachedDetailData.purchases.find((p) => p.id === purchaseId);
  if (!purchase) {
    showToast('Purchase not found.', 'danger');
    return;
  }

  const total = parseFloat(purchase.totalAmount ?? 0) || 0;
  const paid = parseFloat(purchase.amountPaid ?? 0) || 0;
  const outstanding = Math.max(0, total - paid);

  if (outstanding <= 0) {
    showToast('This purchase is already fully paid.', 'warning');
    return;
  }

  _selectedPurchase = purchase;

  const setText = (id, value) => {
    const el = document.getElementById(id);
    if (el) el.textContent = value;
  };

  const purchaseIdEl = document.getElementById('vpPurchaseId');
  if (purchaseIdEl) purchaseIdEl.value = purchase.id;
  const vendorIdEl = document.getElementById('vpVendorId');
  if (vendorIdEl) vendorIdEl.value = purchase.vendor_id || purchase.vendorId || '';

  setText('vpVendorName', purchase.vendorName || 'Vendor Purchase');
  setText('vpPurchaseRef', (purchase.id || '').slice(0, 8).toUpperCase());
  setText('vpTotalAmount', formatCurrency(total));
  setText('vpPaidAmount', formatCurrency(paid));
  setText('vpOutstandingAmount', formatCurrency(outstanding));

  const amountInput = document.getElementById('slAmountPaying');
  if (amountInput) {
    amountInput.value = '';
    amountInput.max = String(outstanding);
    amountInput.disabled = false;
  }

  const dateInput = document.getElementById('vpPaymentDate');
  if (dateInput) dateInput.value = new Date().toISOString().slice(0, 10);

  const balanceSpan = document.getElementById('slBalanceText');
  if (balanceSpan) {
    balanceSpan.dataset.outstanding = String(outstanding);
    balanceSpan.textContent = `Balance After Payment: ${formatCurrency(outstanding)}`;
  }

  // Live "remaining balance" preview while typing
  if (amountInput && balanceSpan) {
    amountInput.oninput = function () {
      const outstandingValue = parseFloat(balanceSpan.dataset.outstanding) || 0;
      const entered = parseFloat(this.value);
      const remaining = Number.isFinite(entered) && entered > 0
        ? Math.max(0, outstandingValue - entered)
        : outstandingValue;
      balanceSpan.textContent = `Balance After Payment: ${formatCurrency(remaining)}`;
    };
  }

  setVendorPaymentSubmitting(false);
  openModal('vendorPaymentModal');
};

window.submitVendorPayment = async function () {
  if (_isSubmitting) return;
  _isSubmitting = true;

  const purchaseId = (document.getElementById('vpPurchaseId')?.value || '').trim();
  const amountInput = document.getElementById('slAmountPaying');
  const dateInput = document.getElementById('vpPaymentDate');
  const outstanding = parseFloat(document.getElementById('slBalanceText')?.dataset.outstanding) || 0;

  const rawAmount = (amountInput?.value || '').trim();
  const amount = Number(rawAmount);

  const fail = (message) => {
    showToast(message, 'danger');
    _isSubmitting = false;
    setVendorPaymentSubmitting(false);
  };

  if (!purchaseId) {
    return fail('Purchase reference is missing. Please reopen the settle form.');
  }
  if (rawAmount === '' || !Number.isFinite(amount) || amount <= 0) {
    if (amountInput) amountInput.focus();
    return fail('Please enter a valid positive amount.');
  }
  if (amount > outstanding) {
    if (amountInput) amountInput.focus();
    return fail(`Amount cannot exceed the outstanding balance of ${formatCurrency(outstanding)}.`);
  }

  const paymentDate = (dateInput?.value || '').trim();
  if (!/^\d{4}-\d{2}-\d{2}$/.test(paymentDate) || Number.isNaN(Date.parse(paymentDate))) {
    if (dateInput) dateInput.focus();
    return fail('Please choose a valid payment date.');
  }

  setVendorPaymentSubmitting(true);

  try {
    const res = await apiRequest(`/api/purchases/${purchaseId}/pay`, {
      method: 'POST',
      body: JSON.stringify({ amount, payment_date: paymentDate }),
    });

    if (res?.success) {
      closeModal('vendorPaymentModal');
      resetVendorPaymentForm();
      showToast(res.message || 'Payment recorded successfully.', 'success');
      await refreshDetailData();
    } else {
      showToast(res?.error || 'Failed to record payment.', 'danger');
    }
  } catch (err) {
    if (err?.name === 'TypeError' || /fetch|network|timeout|offline/i.test(err?.message || '')) {
      showToast('Network error — please check your connection and retry.', 'danger');
    } else if ((err?.message || '').toLowerCase().includes('session expired')) {
      showToast('Your session expired. Please log in again.', 'danger');
    } else {
      showToast(err?.message || 'Failed to record payment.', 'danger');
    }
  } finally {
    _isSubmitting = false;
    setVendorPaymentSubmitting(false);
  }
};

function setVendorPaymentSubmitting(submitting) {
  const btn = document.getElementById('submitVendorPaymentBtn');
  const label = document.getElementById('submitVendorPaymentLabel');
  const spinner = document.getElementById('submitVendorPaymentSpinner');
  if (!btn) return;
  btn.disabled = submitting;
  if (label) label.textContent = submitting ? 'Processing…' : 'Record Payment';
  if (spinner) spinner.style.display = submitting ? 'inline-block' : 'none';
}

function resetVendorPaymentForm() {
  _selectedPurchase = null;

  const amountInput = document.getElementById('slAmountPaying');
  if (amountInput) {
    amountInput.value = '';
    amountInput.max = '';
    amountInput.oninput = null;
  }

  const dateInput = document.getElementById('vpPaymentDate');
  if (dateInput) dateInput.value = '';

  const balanceSpan = document.getElementById('slBalanceText');
  if (balanceSpan) {
    balanceSpan.dataset.outstanding = '';
    balanceSpan.textContent = 'Balance After Payment: ₹0.00';
  }

  setVendorPaymentSubmitting(false);
}

async function refreshDetailData() {
  await loadVendorHistoryDetail(_currentDate);
}
