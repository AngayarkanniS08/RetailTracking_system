/**
 * vendor_history.js — Vendor History Summary Page Controller
 * Loads vendor purchase history from the API, groups records by date
 * (Asia/Kolkata clock values), renders one row per date, and links each
 * date to the master-detail view via a `date` query parameter.
 */

import { apiRequest } from '../core/api.js';
import { formatCurrency, formatDate } from '../utils/format.js';
import { logger } from '../core/logger.js';

let _allHistory = [];

/**
 * Date key extraction — the server returns `purchaseDate` already converted
 * to Asia/Kolkata as a naive timestamp ("YYYY-MM-DD HH:MM:SS"), so the date
 * portion is the exact calendar day used by the backend date filter.
 */
function extractDateKey(record) {
  const raw = record.purchaseDate || record.purchase_date || record.date || record.created_at || record.createdAt || '';
  const str = String(raw);
  return str.slice(0, 10) || '';
}

export function groupVendorHistoryByDate(records = []) {
  const groups = new Map();

  records.forEach((rec) => {
    const dateKey = extractDateKey(rec);
    if (!dateKey) return;

    if (!groups.has(dateKey)) {
      groups.set(dateKey, {
        date: dateKey,
        purchaseCount: 0,
        totalBilled: 0,
        totalPaid: 0,
        balanceDue: 0,
      });
    }

    const g = groups.get(dateKey);
    const total = parseFloat(rec.totalAmount ?? rec.amount ?? rec.total_amount ?? 0) || 0;
    const paid = parseFloat(rec.amountPaid ?? rec.amount_paid ?? 0) || 0;

    g.purchaseCount += 1;
    g.totalBilled += total;
    g.totalPaid += paid;
    g.balanceDue += Math.max(0, total - paid);
  });

  return Array.from(groups.values()).sort((a, b) => (a.date < b.date ? 1 : -1));
}

export async function initVendorHistorySummary() {
  _allHistory = [];
  await loadVendorHistory();
}

async function loadVendorHistory() {
  try {
    const urlParams = new URLSearchParams(window.location.search);
    const vendorId = urlParams.get('vendor_id') || urlParams.get('id') || '';
    const endpoint = vendorId
      ? `/api/vendors/${encodeURIComponent(vendorId)}/history`
      : '/api/vendors/history/all';

    const res = await apiRequest(endpoint);
    const records = Array.isArray(res) ? res : (res?.data || res?.history || []);

    _allHistory = records;
    renderVendorHistorySummary(_allHistory);
    renderVendorHistoryKPIs(_allHistory);
  } catch (err) {
    logger.error('vendor_history', 'Failed to load vendor history:', err);
    const emptyState = document.getElementById('vendorHistoryEmptyState');
    const tableContainer = document.querySelector('#vendorHistorySummaryTable')?.closest('.table-container');
    if (tableContainer) tableContainer.style.display = 'none';
    if (emptyState) emptyState.style.display = 'flex';
  }
}

export function renderVendorHistorySummary(records = []) {
  const tableContainer = document.querySelector('#vendorHistorySummaryTable')?.closest('.table-container');
  const emptyState = document.getElementById('vendorHistoryEmptyState');
  const tbody = document.querySelector('#vendorHistorySummaryTable tbody');
  if (!tbody) return;

  const groups = groupVendorHistoryByDate(records);

  if (groups.length === 0) {
    if (tableContainer) tableContainer.style.display = 'none';
    if (emptyState) emptyState.style.display = 'flex';
    tbody.innerHTML = '';
    return;
  }

  if (tableContainer) tableContainer.style.display = 'block';
  if (emptyState) emptyState.style.display = 'none';

  const urlParams = new URLSearchParams(window.location.search);
  const vendorId = urlParams.get('vendor_id') || urlParams.get('id') || '';

  tbody.innerHTML = groups.map((g) => {
    const detailUrl = vendorId
      ? `/vendors/history/detail?date=${encodeURIComponent(g.date)}&vendor_id=${encodeURIComponent(vendorId)}`
      : `/vendors/history/detail?date=${encodeURIComponent(g.date)}`;

    return `
      <tr style="border-bottom: 1px solid var(--border);">
        <td style="padding: 14px 16px; font-weight: 600;">
          <a href="${detailUrl}" title="View all ${g.purchaseCount} purchase(s) recorded on ${formatDate(g.date)}" style="color: var(--accent); font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 5px;">
            ${formatDate(g.date)}
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity: 0.7;">
              <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
              <polyline points="15 3 21 3 21 9"></polyline>
              <line x1="10" y1="14" x2="21" y2="3"></line>
            </svg>
          </a>
        </td>
        <td style="padding: 14px 16px;">
          <span class="badge rounded-pill bg-primary">${g.purchaseCount}</span>
        </td>
        <td style="padding: 14px 16px; font-weight: 700; color: var(--text-strong);">${formatCurrency(g.totalBilled)}</td>
        <td style="padding: 14px 16px;">
          <span class="badge rounded-pill bg-success">${formatCurrency(g.totalPaid)} Paid</span>
        </td>
        <td style="padding: 14px 16px;">
          <span class="badge rounded-pill bg-warning text-dark">${formatCurrency(g.balanceDue)} Due</span>
        </td>
      </tr>
    `;
  }).join('');
}

export function renderVendorHistoryKPIs(records = []) {
  const totalPurchases = records.length;
  const totalBilled = records.reduce((acc, r) => acc + (parseFloat(r.totalAmount ?? r.amount ?? r.total_amount ?? 0) || 0), 0);
  const totalPaid = records.reduce((acc, r) => acc + (parseFloat(r.amountPaid ?? r.amount_paid ?? 0) || 0), 0);
  const balanceDue = Math.max(0, totalBilled - totalPaid);

  const setText = (id, value) => {
    const el = document.getElementById(id);
    if (el) el.textContent = value;
  };

  setText('vhkTotalPurchases', String(totalPurchases));
  setText('vhkTotalBilled', formatCurrency(totalBilled));
  setText('vhkTotalPaid', formatCurrency(totalPaid));
  setText('vhkBalanceDue', formatCurrency(balanceDue));
}

window.onVendorHistorySearchInput = function () {
  const searchInput = document.getElementById('vendorHistorySearch');
  if (!searchInput) return;
  const query = searchInput.value.toLowerCase().trim();

  const filtered = _allHistory.filter((rec) => {
    const name = (rec.vendorName || rec.vendor_name || '').toLowerCase();
    const phone = (rec.vendorPhone || rec.vendor_phone || '').toLowerCase();
    return name.includes(query) || phone.includes(query);
  });

  renderVendorHistorySummary(filtered);
};

window.initVendorHistorySummary = initVendorHistorySummary;
window.groupVendorHistoryByDate = groupVendorHistoryByDate;
window.renderVendorHistorySummary = renderVendorHistorySummary;
window.renderVendorHistoryKPIs = renderVendorHistoryKPIs;
