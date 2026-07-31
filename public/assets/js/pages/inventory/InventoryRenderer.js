/**
 * InventoryRenderer.js — pure presentation helpers.
 *
 * Renders server-computed values only. Stock status, inventory value and
 * restock recommendations arrive pre-computed from the API — never derived
 * here.
 */

import { formatCurrency, formatDate } from '../../utils/format.js';

export function escapeHtml(value) {
  if (value === null || value === undefined) return '';
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

/**
 * @param {object} summary  Server-computed InventorySummaryDTO
 */
export function renderStats(summary = {}) {
  const grid = document.getElementById('inventoryStats');
  if (!grid) return;

  const lowStock = Number(summary.low_stock_count ?? 0);
  const outStock = Number(summary.out_of_stock_count ?? 0);

  grid.innerHTML = `
    <div class="stat-card">
      <div class="stat-label">Current Stock Value</div>
      <div class="stat-value" style="color:var(--info)">${formatCurrency(summary.total_stock_value ?? 0)}</div>
      <div style="font-size:0.75rem; color:var(--muted); margin-top:4px;">Based on purchase cost</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Stock Sold Value</div>
      <div class="stat-value" style="color:var(--ok)">${formatCurrency(summary.stock_sold_value ?? 0)}</div>
      <div style="font-size:0.75rem; color:var(--muted); margin-top:4px;">Total revenue from sales</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Total Batches</div>
      <div class="stat-value">${summary.total_batches ?? 0}</div>
      <div style="font-size:0.75rem; color:var(--muted); margin-top:4px;">Across all products</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Low / Out of Stock</div>
      <div class="stat-value" style="color:${(lowStock + outStock) > 0 ? 'var(--warn)' : 'var(--ok)'}">${lowStock + outStock}</div>
      <div style="font-size:0.75rem; color:var(--muted); margin-top:4px;">Batches needing attention</div>
    </div>
  `;
}

/**
 * @param {Array<object>} items  Server-computed InventoryBatchDTO list
 */
export function renderTable(items = []) {
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

  tbody.innerHTML = items.map((item) => {
    const batchLabel = escapeHtml(item.batch_number || item.id || '—');
    const formattedDate = escapeHtml(item.created_at ? formatDate(item.created_at) : '—');
    const vendorName = escapeHtml(item.vendor_name || '—');
    const productName = escapeHtml(item.product_name || '—');
    const unit = escapeHtml(item.unit || 'pcs');
    const safeId = escapeHtml(item.id || '');

    return `
      <tr>
        <td style="font-weight: 600; color: var(--text-strong);">${batchLabel}</td>
        <td>${formattedDate}</td>
        <td>${vendorName}</td>
        <td style="font-weight: 500;">${productName}</td>
        <td class="tabular-nums">${formatCurrency(item.cost_price ?? 0)}</td>
        <td class="tabular-nums">${formatCurrency(item.selling_price ?? 0)}</td>
        <td style="font-weight: 600;">${item.quantity ?? 0} ${unit}</td>
        <td>
          <span class="badge rounded-pill ${escapeHtml(item.status_badge_class || 'bg-success')}">
            ${escapeHtml(item.status_text || 'In Stock')}
          </span>
        </td>
        <td>
          <div class="inventory-actions">
            <button class="btn btn-xs btn-outline" data-stock-details="${safeId}" title="Details">Details</button>
            <button class="btn-icon restock-btn" data-restock="${safeId}" title="Restock">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
            </button>
            <button class="btn-icon edit-btn" data-edit-batch="${safeId}" title="Edit Batch">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </button>
          </div>
        </td>
      </tr>
    `;
  }).join('');
}

/**
 * @param {object} pagination  Server-computed pagination object
 */
export function renderPagination(pagination = {}) {
  const container = document.getElementById('inventoryPaginationControls');
  if (!container) return;

  const totalPages = Number(pagination.total_pages ?? 1);
  const current = Number(pagination.current_page ?? 1);

  if (totalPages <= 1) {
    container.style.display = 'none';
    container.innerHTML = '';
    return;
  }

  container.style.display = 'flex';
  container.innerHTML = `
    <button class="pagination-btn" id="prevInvPageBtn" ${!pagination.has_prev ? 'disabled' : ''}>← Previous</button>
    <span class="pagination-info">Page ${current} of ${totalPages}</span>
    <button class="pagination-btn" id="nextInvPageBtn" ${!pagination.has_next ? 'disabled' : ''}>Next →</button>
  `;
}
