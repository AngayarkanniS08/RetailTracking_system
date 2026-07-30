/**
 * inventory.js — Inventory page controller module
 */

import { fetchInventoryItemsApi } from '../services/inventory.service.js';
import { formatCurrency, formatDate } from '../utils/format.js';
import { logger } from '../core/logger.js';
import { apiRequest } from '../core/api.js';
import { openModal, closeModal } from '../ui/modal.js';
import { showToast } from '../ui/toast.js';

export async function initInventoryPage() {
  try {
    const items = await fetchInventoryItemsApi();
    renderInventoryTable(items);
  } catch (err) {
    logger.error('inventory', err);
    renderInventoryTable([]);
  }
}

/**
 * Render inventory items into table or display empty state
 * @param {Array} items
 */
export function renderInventoryTable(items = []) {
  const tableContainer = document.querySelector('#inventoryTable')?.closest('.table-container');
  const tbody = document.querySelector('#inventoryTable tbody');
  const emptyState = document.getElementById('inventoryEmptyState');

  if (!items || items.length === 0) {
    if (tableContainer) tableContainer.style.display = 'none';
    if (emptyState) emptyState.style.display = 'flex';
    return;
  }

  if (tableContainer) tableContainer.style.display = 'block';
  if (emptyState) emptyState.style.display = 'none';

  if (!tbody) return;

  tbody.innerHTML = items.map((item) => `
    <tr>
      <td style="font-weight: 600; color: var(--text-strong);">${item.batch_id || item.id || '—'}</td>
      <td>${item.date ? formatDate(item.date) : '—'}</td>
      <td>${item.vendor_name || '—'}</td>
      <td style="font-weight: 500;">${item.product_name || item.name || '—'}</td>
      <td class="tabular-nums">${formatCurrency(item.cost_price ?? item.purchase_price ?? 0)}</td>
      <td class="tabular-nums">${formatCurrency(item.selling_price ?? 0)}</td>
      <td style="font-weight: 600;">${item.stock_qty ?? item.quantity ?? 0} ${item.unit || ''}</td>
      <td>
        <span class="badge ${getStatusBadgeClass(item.stock_qty ?? item.quantity ?? 0, item.min_threshold ?? 10)}">
          ${getStatusText(item.stock_qty ?? item.quantity ?? 0, item.min_threshold ?? 10)}
        </span>
      </td>
      <td style="text-align: right;">
        <button class="btn btn-xs btn-outline" onclick="openStockIntelligenceModal('${item.id}')">Details</button>
      </td>
    </tr>
  `).join('');
}

function getStatusBadgeClass(qty, threshold) {
  if (qty <= 0) return 'badge-danger';
  if (qty <= threshold) return 'badge-warning';
  return 'badge-success';
}

function getStatusText(qty, threshold) {
  if (qty <= 0) return 'Out of Stock';
  if (qty <= threshold) return 'Low Stock';
  return 'In Stock';
}

// ── Global Handler: SET ALERT Modal ──────────────────────────────────────────

window.openLowStockAlertModal = async function () {
  if (typeof openModal === 'function') {
    openModal('lowStockAlertModal');
  }

  const select = document.getElementById('alertProductSelect');
  if (select) {
    select.innerHTML = '<option value="">Loading products...</option>';
    try {
      const res = await apiRequest('/api/products');
      const products = res.data || res.products || (Array.isArray(res) ? res : []);
      if (Array.isArray(products) && products.length > 0) {
        select.innerHTML = '<option value="">Select a Product...</option>' +
          products.map(p => `<option value="${p.id}">${p.name}</option>`).join('');
      } else {
        select.innerHTML = '<option value="">No products found</option>';
      }
    } catch (err) {
      logger.error('Failed to load products for alert modal:', err);
      select.innerHTML = '<option value="">Failed to load products</option>';
    }
  }

  // Reset input fields
  const lt = document.getElementById('alertLeadTime');
  const ds = document.getElementById('alertDailySale');
  const es = document.getElementById('alertEmergencyStock');
  const th = document.getElementById('alertThreshold');

  if (lt) lt.value = '';
  if (ds) ds.value = '';
  if (es) es.value = '';
  if (th) th.value = '';
};

window.calculateReorderPoint = function () {
  const leadTime = parseInt(document.getElementById('alertLeadTime')?.value || '0', 10);
  const dailySales = parseInt(document.getElementById('alertDailySale')?.value || '0', 10);
  const emergencyStock = parseInt(document.getElementById('alertEmergencyStock')?.value || '0', 10);

  const reorderPoint = (leadTime * dailySales) + emergencyStock;
  const thresholdEl = document.getElementById('alertThreshold');
  if (thresholdEl) {
    thresholdEl.value = reorderPoint > 0 ? reorderPoint : '';
  }
};

window.saveLowStockAlert = async function () {
  const productId = document.getElementById('alertProductSelect')?.value;
  const leadTime = parseInt(document.getElementById('alertLeadTime')?.value || '0', 10);
  const dailySales = parseInt(document.getElementById('alertDailySale')?.value || '0', 10);
  const emergencyStock = parseInt(document.getElementById('alertEmergencyStock')?.value || '0', 10);

  if (!productId) {
    showToast('Please select a product for the alert', 'warning');
    return;
  }

  try {
    await apiRequest('/api/inventory/alerts', {
      method: 'POST',
      body: JSON.stringify({
        product_id: productId,
        lead_time: leadTime,
        daily_sales: dailySales,
        emergency_stock: emergencyStock
      })
    });

    closeModal('lowStockAlertModal');
    showToast('Low stock alert configured successfully!', 'success');

    if (typeof window.openActiveAlertsModal === 'function') {
      window.openActiveAlertsModal();
    }
  } catch (err) {
    logger.error('Failed to save low stock alert:', err);
    showToast('Failed to save low stock alert: ' + (err.message || 'Unknown error'), 'error');
  }
};

// ── Global Handler: Add Stock Batch Modal ──────────────────────────────────────

window.saveStock = async function () {
  const productId = document.getElementById('stockProduct')?.value || document.getElementById('stockProductInput')?.value;
  const vendorName = document.getElementById('stockVendor')?.value || '';
  const batchNumber = document.getElementById('stockBatchId')?.value || '';
  const purchasePrice = parseFloat(document.getElementById('stockPP')?.value || '0');
  const sellingPrice = parseFloat(document.getElementById('stockSP')?.value || '0');
  const retailPrice = parseFloat(document.getElementById('retailSP')?.value || '0');
  const quantity = parseInt(document.getElementById('stockQty')?.value || '0', 10);
  const dateVal = document.getElementById('stockDate')?.value || '';

  if (!productId) {
    showToast('Please select or enter a product for the batch', 'warning');
    return;
  }

  if (!batchNumber) {
    showToast('Please enter a Batch ID / Number', 'warning');
    return;
  }

  if (quantity <= 0) {
    showToast('Quantity must be greater than 0', 'warning');
    return;
  }

  try {
    await apiRequest('/api/inventory/batches', {
      method: 'POST',
      body: JSON.stringify({
        product_id: productId,
        batch_number: batchNumber,
        vendor_name: vendorName,
        initial_qty: quantity,
        quantity: quantity,
        cost_price: purchasePrice,
        purchase_price: purchasePrice,
        selling_price: sellingPrice,
        retail_price: retailPrice,
        created_at: dateVal ? dateVal + ' 00:00:00' : undefined
      })
    });

    closeModal('addStockModal');
    showToast('Stock batch added successfully!', 'success');
    initInventoryPage();
  } catch (err) {
    logger.error('Failed to save stock batch:', err);
    showToast('Failed to save stock batch: ' + (err.message || 'Unknown error'), 'error');
  }
};

window.saveStockEntry = window.saveStock;


