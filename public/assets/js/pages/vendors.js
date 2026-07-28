/**
 * vendors.js — Vendor management & history page controller module
 */

import { fetchVendorsApi, fetchVendorHistoryApi, saveVendorPurchaseApi } from '../services/vendor.service.js';
import { fetchProductsApi } from '../services/product.service.js';
import { formatCurrency, formatDate } from '../utils/format.js';
import { logger } from '../core/logger.js';
import { showToast } from '../ui/toast.js';

let _activeVendorId = null;
let _vendorHistoryTab = 'purchases';

/**
 * Load vendor purchases table
 * @param {number} [page=1]
 */
export async function loadPurchases(page = 1) {
  const queryInput = document.getElementById('vendorSearch');
  const query = queryInput?.value ?? '';

  try {
    const vendors = await fetchVendorsApi({ query, page });
    renderVendorListTable(vendors);
  } catch (err) {
    logger.error('vendors:loadPurchases', err);
  }
}

/** Render vendor summary table */
function renderVendorListTable(vendors) {
  const tableContainer = document.querySelector('#vendorPurchaseTable')?.closest('.table-container');
  const tbody = document.querySelector('#vendorPurchaseTable tbody');
  const emptyState = document.getElementById('vendorEmptyState');

  if (tableContainer) tableContainer.style.display = 'block';
  if (emptyState) emptyState.style.display = 'none';

  if (!tbody) return;

  if (!vendors || vendors.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="7" style="padding: 48px 24px; text-align: center;">
          <div style="display: flex; flex-direction: column; align-items: center; justify-content: center;">
            <div style="width: 48px; height: 48px; border-radius: 50%; background: var(--surface-container-low); display: flex; align-items: center; justify-content: center; margin-bottom: 12px; border: 1px solid var(--border);">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="color: var(--muted);">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
              </svg>
            </div>
            <h3 style="font-size: 1rem; font-weight: 700; color: var(--text-strong); margin: 0 0 4px 0;">No vendor records found</h3>
            <p style="font-size: 0.85rem; color: var(--muted); margin: 0 0 16px 0; max-width: 340px; line-height: 1.4;">Vendor purchase records and balances will appear here in real-time.</p>
            <button class="btn btn-outline btn-sm" onclick="openModal('addStockEntryModal'); loadProductsForVendor();" style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; font-size: 0.82rem; font-weight: 600; border-radius: var(--radius-md);">
              <span>+</span> New Purchase
            </button>
          </div>
        </td>
      </tr>`;
    return;
  }

  tbody.innerHTML = vendors.map((v) => `

    <tr>
      <td class="t-name" style="font-weight: 600; color: var(--text-strong);">${v.name ?? 'Unknown Vendor'}</td>
      <td>${v.contact_phone || v.phone || '—'}</td>
      <td>${v.total_orders ?? 0}</td>
      <td class="tabular-nums">${formatCurrency(v.total_amount ?? 0)}</td>
      <td class="tabular-nums text-ok" style="font-weight: 500;">${formatCurrency(v.total_paid ?? 0)}</td>
      <td class="tabular-nums text-danger" style="font-weight: 600;">${formatCurrency(v.balance_due ?? 0)}</td>
      <td style="text-align: right;">
        <button class="btn btn-xs btn-outline" onclick="openVendorHistory('${v.id}')">History</button>
      </td>
    </tr>
  `).join('');
}


/**
 * Load product options for vendor purchase entry modal
 */
export async function loadProductsForVendor() {
  try {
    const products = await fetchProductsApi();
    const select = document.getElementById('newPurchaseProductSelect') || document.getElementById('stockProductSelect');
    if (!select) return;

    if (!products || products.length === 0) {
      select.innerHTML = '<option value="">No products available</option>';
      return;
    }

    select.innerHTML = '<option value="">Select Product...</option>' +
      products.map((p) => `<option value="${p.id}">${p.name} (${p.unit || 'pcs'})</option>`).join('');
  } catch (err) {
    logger.error('vendors:loadProductsForVendor', err);
  }
}

/**
 * Search vendor history by month or exact date
 */
export async function searchVendorHistory() {
  const monthInput = document.getElementById('vhMonthSearch');
  const dateInput = document.getElementById('vhDateSearch');

  const month = monthInput?.value ?? '';
  const date = dateInput?.value ?? '';

  logger.debug('vendors:searchHistory', { month, date, vendorId: _activeVendorId });

  try {
    const history = await fetchVendorHistoryApi(_activeVendorId, { month, date });
    renderVendorHistoryData(history);
    showToast('Vendor history filtered', 'info');
  } catch (err) {
    logger.error('vendors:searchHistory', err);
  }
}

/**
 * Clear search inputs in vendor history
 */
export function clearVendorHistorySearch() {
  const monthInput = document.getElementById('vhMonthSearch');
  const dateInput = document.getElementById('vhDateSearch');

  if (monthInput) monthInput.value = '';
  if (dateInput) dateInput.value = '';

  searchVendorHistory();
}

/**
 * Switch vendor history tab ('purchases' vs 'payments')
 * @param {'purchases'|'payments'} tab
 */
export function switchHistoryTab(tab) {
  _vendorHistoryTab = tab;

  const purchasesBtn = document.getElementById('togglePurchasesBtn');
  const paymentsBtn = document.getElementById('togglePaymentsBtn');
  const purchaseStats = document.getElementById('purchaseStats');
  const paymentStats = document.getElementById('paymentStats');

  if (purchasesBtn) purchasesBtn.className = tab === 'purchases' ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-outline';
  if (paymentsBtn) paymentsBtn.className = tab === 'payments' ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-outline';

  if (purchaseStats) purchaseStats.style.display = tab === 'purchases' ? 'grid' : 'none';
  if (paymentStats) paymentStats.style.display = tab === 'payments' ? 'grid' : 'none';
}

/** Open vendor history view for a specific vendor ID */
export function openVendorHistory(vendorId) {
  _activeVendorId = vendorId;
  if (typeof window.switchTab === 'function') {
    window.switchTab('vendorhistory');
  }
  searchVendorHistory();
}

/** Render vendor history records */
function renderVendorHistoryData(history) {
  const container = document.getElementById('vendorHistoryBody');
  if (!container) return;

  if (!history || history.length === 0) {
    container.innerHTML = '<div class="empty-state">No transaction history found for this vendor.</div>';
    return;
  }

  container.innerHTML = history.map((item) => `
    <div class="backup-file-row">
      <div>
        <strong>${formatDate(item.date || item.created_at)}</strong>
        <span class="text-muted ms-2">${item.reference || item.type || 'Purchase Order'}</span>
      </div>
      <div class="tabular-nums font-semibold">${formatCurrency(item.amount ?? 0)}</div>
    </div>
  `).join('');
}

/** Initialise Vendor Page */
export async function initVendorsPage() {
  loadPurchases(1);
}

// Expose functions globally on window for inline HTML onclick/onkeyup attributes
window.loadPurchases = loadPurchases;
window.loadProductsForVendor = loadProductsForVendor;
window.searchVendorHistory = searchVendorHistory;
window.clearVendorHistorySearch = clearVendorHistorySearch;
window.switchHistoryTab = switchHistoryTab;
window.openVendorHistory = openVendorHistory;
