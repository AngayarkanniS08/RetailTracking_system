/**
 * daily_register_detail.js — Daily Register Detail Page Controller
 * Handles URL query parsing, API fetching from /api/daily-register/detail,
 * KPI card updates, concise generated form list rendering, and live filtering.
 */

import { apiRequest } from '../core/api.js';
import { formatCurrency, formatDate, formatDateTime } from '../utils/format.js';
import { logger } from '../core/logger.js';
import { getToken } from '../core/storage.js';
import { API_BASE } from '../core/config.js';

let _cachedDetailData = null;
let _currentDate = new Date().toISOString().split('T')[0];

window.openReceipt = function (invoiceId) {
  const token = getToken();
  const url = `${API_BASE}/api/invoices/${invoiceId}/receipt?token=${encodeURIComponent(token || '')}`;
  window.open(url, 'receipt');
};

window.navigateDay = function (deltaDays) {
  const dt = new Date(_currentDate);
  dt.setDate(dt.getDate() + deltaDays);
  const newDateStr = dt.toISOString().split('T')[0];

  const params = new URLSearchParams(window.location.search);
  params.set('date', newDateStr);

  window.location.search = params.toString();
};

window.onDateSelectionChange = function (newDateStr) {
  if (!newDateStr) return;
  const params = new URLSearchParams(window.location.search);
  params.set('date', newDateStr);

  window.location.search = params.toString();
};

export async function initDailyRegisterDetail() {
  const urlParams = new URLSearchParams(window.location.search);
  _currentDate = urlParams.get('date') || new Date().toISOString().split('T')[0];

  const datePicker = document.getElementById('registerDatePicker');
  if (datePicker) datePicker.value = _currentDate;

  const displayDateEl = document.getElementById('displaySelectedDate');
  if (displayDateEl) displayDateEl.textContent = formatDate(_currentDate);

  const displayDateBadge = document.getElementById('displaySelectedDateBadge');
  if (displayDateBadge) displayDateBadge.textContent = formatDate(_currentDate);

  const breadcrumbDateEl = document.getElementById('breadcrumbDate');
  if (breadcrumbDateEl) breadcrumbDateEl.textContent = _currentDate;

  await loadDailyDetailData(_currentDate);
}

async function loadDailyDetailData(date) {
  try {
    const query = new URLSearchParams({ date });

    const res = await apiRequest(`/api/daily-register/detail?${query.toString()}`);
    const data = res?.data || res;

    _cachedDetailData = data;
    renderKPICards(data.summary || {});
    renderFormsList(data.invoices || []);
  } catch (err) {
    logger.error('daily_register_detail', 'Failed to load daily register detail:', err);
    showEmptyState(true);
  }
}

function renderKPICards(summary) {
  const formsGenEl    = document.getElementById('kpiFormsGenerated');
  const grossRevEl    = document.getElementById('kpiGrossRevenue');
  const cashCollEl    = document.getElementById('kpiCashCollected');
  const creditEl      = document.getElementById('kpiCreditIssued');
  const avgValEl      = document.getElementById('kpiAvgFormValue');
  const formsBadgeEl  = document.getElementById('formsBadgeCount');

  const formsCount = summary.forms_generated || 0;

  if (formsGenEl)    formsGenEl.textContent = String(formsCount);
  if (formsBadgeEl)  formsBadgeEl.textContent = String(formsCount);
  if (grossRevEl)    grossRevEl.textContent = formatCurrency(summary.gross_revenue || 0);
  if (cashCollEl)    cashCollEl.textContent = formatCurrency(summary.cash_collected || 0);
  if (creditEl)      creditEl.textContent = formatCurrency(summary.credit_issued || 0);
  if (avgValEl)      avgValEl.textContent = formatCurrency(summary.avg_form_value || 0);
}

function renderFormsList(invoices = []) {
  const tbody = document.querySelector('#detailFormsTable tbody');
  if (!tbody) return;

  if (!invoices || invoices.length === 0) {
    showEmptyState(true);
    tbody.innerHTML = '';
    return;
  }

  showEmptyState(false);

  tbody.innerHTML = invoices.map(inv => {
    const billedTime = inv.billed_at ? formatDateTime(inv.billed_at).split(',')[1] || formatDate(inv.billed_at) : '—';
    const status = (inv.invoice_status || 'COMPLETED').toUpperCase();
    const payment = (inv.payment_status || 'PAID').toUpperCase();

    const getStatusBadgeClass = (st) => {
      switch (st) {
        case 'CANCELLED': return 'bg-danger';
        case 'RETURNED':  return 'bg-secondary';
        case 'COMPLETED':
        default:          return 'bg-success';
      }
    };

    const getPaymentBadgeClass = (pm) => {
      switch (pm) {
        case 'PENDING': return 'bg-warning text-dark';
        case 'PARTIAL': return 'bg-info text-dark';
        case 'PAID':
        default:        return 'bg-success';
      }
    };

    return `
      <tr style="border-bottom: 1px solid var(--border);">
        <td style="padding: 12px 16px; font-weight: 500; color: var(--muted); font-size: 0.82rem;">${billedTime}</td>
        <td style="padding: 12px 16px; font-weight: 700; color: var(--accent); font-size: 0.88rem;">${inv.invoice_number}</td>
        <td style="padding: 12px 16px;">
          <div style="font-weight: 600; color: var(--text-strong);">${inv.customer_name}</div>
          ${inv.customer_phone ? `<div style="font-size: 0.75rem; color: var(--muted);">${inv.customer_phone}</div>` : ''}
        </td>
        <td style="padding: 12px 16px; font-weight: 500; color: var(--text-strong); font-size: 0.82rem;">
          ${inv.operator_name || 'System Operator'}
        </td>
        <td style="padding: 12px 16px; max-width: 260px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--text-muted); font-size: 0.82rem;">
          ${inv.items_summary}
        </td>
        <td style="padding: 12px 16px; font-weight: 700; color: var(--text-strong);">${formatCurrency(inv.grand_total)}</td>
        <td style="padding: 12px 16px;">
          <span class="badge rounded-pill ${getPaymentBadgeClass(payment)}" style="margin-right: 4px;">${payment}</span>
          <span class="badge rounded-pill ${getStatusBadgeClass(status)}">${status}</span>
        </td>
        <td style="padding: 12px 16px; text-align: right;">
          <button onclick="openReceipt('${inv.id}')" class="btn btn-xs btn-outline" style="padding: 4px 10px; font-size: 0.75rem; border-radius: 4px; cursor: pointer;">
            Print Receipt
          </button>
        </td>
      </tr>
    `;
  }).join('');
}

function showEmptyState(show) {
  const emptyState = document.getElementById('detailEmptyState');
  const tableContainer = document.querySelector('#detailFormsTable')?.closest('.table-container');

  if (emptyState) emptyState.style.display = show ? 'flex' : 'none';
  if (tableContainer) tableContainer.style.display = show ? 'none' : 'block';
}

window.onDetailSearchInput = function () {
  filterAndRender();
};

window.onStatusFilterChange = function () {
  filterAndRender();
};

function filterAndRender() {
  if (!_cachedDetailData || !_cachedDetailData.invoices) return;

  const searchQuery = (document.getElementById('detailSearch')?.value || '').toLowerCase().trim();
  const statusFilter = (document.getElementById('statusFilterSelect')?.value || 'ALL').toUpperCase();

  const filtered = _cachedDetailData.invoices.filter(inv => {
    const invNum = (inv.invoice_number || '').toLowerCase();
    const cust = (inv.customer_name || '').toLowerCase();
    const phone = (inv.customer_phone || '').toLowerCase();
    const status = (inv.invoice_status || '').toUpperCase();
    const payment = (inv.payment_status || '').toUpperCase();

    const matchesSearch = !searchQuery || invNum.includes(searchQuery) || cust.includes(searchQuery) || phone.includes(searchQuery);
    const matchesStatus = statusFilter === 'ALL' || payment === statusFilter || status === statusFilter;

    return matchesSearch && matchesStatus;
  });

  renderFormsList(filtered);
}

document.addEventListener('DOMContentLoaded', () => {
  initDailyRegisterDetail();
});
