/**
 * inventory.js — Inventory page controller module
 */

import { fetchInventoryItemsApi } from '../services/inventory.service.js';
import { formatCurrency, formatDate } from '../utils/format.js';
import { logger } from '../core/logger.js';

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

