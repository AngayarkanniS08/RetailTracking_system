/**
 * vendors.js — Vendor list page controller module
 * The Vendor History page (/vendors/history) is handled by vendor_history.js
 * and vendor_history_detail.js (master-detail date workflow).
 */

import { fetchVendorsApi } from '../services/vendor.service.js';
import { fetchProductsApi } from '../services/product.service.js';
import { apiRequest } from '../core/api.js';
import { formatCurrency, escapeHtml } from '../utils/format.js';
import { logger } from '../core/logger.js';
import { showToast } from '../ui/toast.js';

/**
 * Load vendor purchases table
 * @param {number} [page=1]
 */
export async function loadPurchases(page = 1) {
  const queryInput = document.getElementById('vendorSearch');
  const query = queryInput?.value ?? '';

  try {
    const res = await fetchVendorsApi({ query, page });
    const vendors = Array.isArray(res?.data) ? res.data : (Array.isArray(res) ? res : []);
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

  const elVendors = document.getElementById('slTotalVendors');
  const elAmount = document.getElementById('slTotalAmount');
  const elPaid = document.getElementById('slTotalPaid');
  const elBalance = document.getElementById('slTotalBalance');

  if (tableContainer) tableContainer.style.display = 'block';
  if (emptyState) emptyState.style.display = 'none';

  if (!vendors || !Array.isArray(vendors) || vendors.length === 0) {
    if (elVendors) elVendors.textContent = '0';
    if (elAmount) elAmount.textContent = formatCurrency(0);
    if (elPaid) elPaid.textContent = formatCurrency(0);
    if (elBalance) elBalance.textContent = formatCurrency(0);

    if (tbody) {
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
    }
    return;
  }

  // Calculate summary stat totals across all vendor records
  let totalVendorsCount = vendors.length;
  let totalBilledSum = 0;
  let totalPaidSum = 0;
  let balanceDueSum = 0;

  vendors.forEach((v) => {
    totalBilledSum += parseFloat(v.total_amount ?? v.totalBilled ?? 0) || 0;
    totalPaidSum += parseFloat(v.total_paid ?? v.totalPaid ?? 0) || 0;
    balanceDueSum += parseFloat(v.balance_due ?? v.balanceDue ?? 0) || 0;
  });

  if (elVendors) elVendors.textContent = totalVendorsCount;
  if (elAmount) elAmount.textContent = formatCurrency(totalBilledSum);
  if (elPaid) elPaid.textContent = formatCurrency(totalPaidSum);
  if (elBalance) elBalance.textContent = formatCurrency(balanceDueSum);

  if (tbody) {
    tbody.innerHTML = vendors.map((v) => {
      const name = escapeHtml(v.name ?? 'Unknown Vendor');
      const phone = escapeHtml(v.contact_phone || v.phone || '—');
      const totalOrders = escapeHtml(v.total_orders ?? 0);
      const safeId = escapeHtml(v.id || '');

      return `
        <tr>
          <td class="t-name" style="font-weight: 600; color: var(--text-strong);">${name}</td>
          <td>${phone}</td>
          <td>${totalOrders}</td>
          <td class="tabular-nums">${formatCurrency(v.total_amount ?? 0)}</td>
          <td class="tabular-nums text-ok" style="font-weight: 500;">${formatCurrency(v.total_paid ?? 0)}</td>
          <td class="tabular-nums text-danger" style="font-weight: 600;">${formatCurrency(v.balance_due ?? 0)}</td>
          <td style="text-align: right;">
            <button class="btn btn-xs btn-outline" onclick="openVendorHistory('${safeId}')">History</button>
          </td>
        </tr>
      `;
    }).join('');
  }
}


/**
 * Load product options for vendor purchase entry modal
 */
export async function loadProductsForVendor() {
  try {
    const res = await fetchProductsApi();
    const products = Array.isArray(res?.data) ? res.data : (Array.isArray(res) ? res : []);
    const selects = [
      document.getElementById('slStockName'),
      document.getElementById('newPurchaseProductSelect'),
      document.getElementById('stockProductSelect')
    ].filter(Boolean);

    if (selects.length === 0) return;

    const optionsHtml = (!products || products.length === 0)
      ? '<option value="">No products available</option>'
      : '<option value="">Select Product...</option>' +
        products.map((p) => {
          const pid = escapeHtml(p.id || '');
          const pname = escapeHtml(p.name || '');
          const punit = escapeHtml(p.unit || 'pcs');
          return `<option value="${pid}">${pname} (${punit})</option>`;
        }).join('');

    selects.forEach(select => {
      select.innerHTML = optionsHtml;
    });
  } catch (err) {
    logger.error('vendors:loadProductsForVendor', err);
  }
}

/**
 * Save new stock purchase entry
 */
export async function saveStockEntry() {
  const productId = document.getElementById('slStockName')?.value ?? '';
  const vendorName = document.getElementById('slVendorName')?.value?.trim() ?? '';
  const phone = document.getElementById('slVendorPhone')?.value?.trim() ?? '';
  const qty = parseFloat(document.getElementById('slQty')?.value ?? 0);
  const baseAmount = parseFloat(document.getElementById('slAmount')?.value ?? 0);
  const gstRate = parseFloat(document.getElementById('purchaseGstRate')?.value ?? 0);
  const amountPaid = parseFloat(document.getElementById('slPaid')?.value ?? 0);
  const purchaseDate = document.getElementById('slPurchaseDate')?.value || new Date().toISOString().split('T')[0];

  if (!vendorName) {
    showToast('Please enter vendor name', 'error');
    return;
  }
  if (!productId) {
    showToast('Please select a product', 'error');
    return;
  }
  if (qty <= 0) {
    showToast('Please enter a valid quantity', 'error');
    return;
  }
  if (baseAmount <= 0) {
    showToast('Please enter a valid base amount', 'error');
    return;
  }

  const payload = {
    vendor_name: vendorName,
    phone: phone,
    purchase_date: purchaseDate,
    base_amount: baseAmount,
    amount_paid: amountPaid,
    items: [
      {
        product_id: productId,
        quantity: qty,
        unit_price: qty > 0 ? (baseAmount / qty) : baseAmount,
        gst_rate: gstRate
      }
    ]
  };

  try {
    const saveBtn = document.getElementById('savePurchaseBtn');
    if (saveBtn) saveBtn.disabled = true;

    const res = await apiRequest('/api/purchases', {
      method: 'POST',
      body: JSON.stringify(payload)
    });

    showToast('Purchase entry saved successfully!', 'success');
    if (typeof window.closeModal === 'function') {
      window.closeModal('addStockEntryModal');
    }
    await loadPurchases(1);
  } catch (err) {
    logger.error('vendors:saveStockEntry', err);
    showToast(err.message || 'Failed to save purchase entry', 'error');
  } finally {
    const saveBtn = document.getElementById('savePurchaseBtn');
    if (saveBtn) saveBtn.disabled = false;
  }
}

/** Open vendor history view for a specific vendor ID */
export function openVendorHistory(vendorId) {
  if (!vendorId) return;
  window.location.href = `/vendors/history?vendor_id=${encodeURIComponent(vendorId)}`;
}

/** Initialise Vendor Page */
export async function initVendorsPage() {
  await loadPurchases(1);
}

// Expose functions globally on window for inline HTML onclick/onkeyup attributes
window.loadPurchases = loadPurchases;
window.loadProductsForVendor = loadProductsForVendor;
window.saveStockEntry = saveStockEntry;
window.openVendorHistory = openVendorHistory;
window.initVendorsPage = initVendorsPage;
