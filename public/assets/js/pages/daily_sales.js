/**
 * daily_sales.js — Day to Day Selling page controller
 * Fetches invoices from /api/invoices, updates KPI cards, renders timeline table, and handles live search.
 */

import { apiRequest } from '../core/api.js';
import { formatCurrency, formatDate } from '../utils/format.js';
import { logger } from '../core/logger.js';
import { getToken } from '../core/storage.js';
import { API_BASE } from '../core/config.js';

let _cachedInvoices = [];

window.openReceipt = function (invoiceId) {
  const token = getToken();
  const url = `${API_BASE}/api/invoices/${invoiceId}/receipt?token=${encodeURIComponent(token || '')}`;
  window.open(url, 'receipt');
};

export async function initDayToDaySelling() {
  const table = document.getElementById('salesTimelineTable');
  const tbody = table?.querySelector('tbody');
  const emptyState = document.getElementById('salesEmptyState');

  try {
    const res = await apiRequest('/api/invoices?limit=100');
    const invoices = Array.isArray(res) ? res : (res?.data || res?.invoices || []);
    _cachedInvoices = invoices;

    renderSalesTimeline(invoices);
    renderKPISummary(invoices);
  } catch (err) {
    logger.error('daily_sales', 'Failed to load day-to-day sales:', err);
    if (emptyState) emptyState.style.display = 'flex';
  }
}

export function renderSalesTimeline(invoices = []) {
  const tableContainer = document.querySelector('#salesTimelineTable')?.closest('.table-container');
  const tbody = document.querySelector('#salesTimelineTable tbody');
  const emptyState = document.getElementById('salesEmptyState');

  if (tableContainer) tableContainer.style.display = 'block';

  if (!tbody) return;

  if (!invoices || invoices.length === 0) {
    if (emptyState) emptyState.style.display = 'none';
    tbody.innerHTML = `
      <tr>
        <td colspan="8" style="text-align: center; padding: 48px 24px; color: var(--muted);">
          <div style="font-weight: 600; font-size: 0.95rem; margin-bottom: 4px; color: var(--text-strong);">No sales records found</div>
          <div style="font-size: 0.82rem;">Sales completed through POS Billing will appear here in real-time.</div>
        </td>
      </tr>
    `;
    return;
  }

  if (emptyState) emptyState.style.display = 'none';

  tbody.innerHTML = invoices.map(inv => {
    const dateStr = inv.billedAt || inv.created_at || inv.billed_at || '';
    const formattedDate = dateStr ? formatDate(dateStr) : '—';
    const invNumber = inv.invoiceNumber || inv.invoice_number || ('INV-' + String(inv.id).slice(0, 8));
    const customer = inv.customerName || inv.customer_name_snapshot || 'Walk-in Customer';
    const itemsText = inv.itemsSummary || inv.items_summary || (
      Array.isArray(inv.items) && inv.items.length > 0
        ? inv.items.map(i => `${i.product_name || i.productName || 'Item'} (x${i.quantity || 1})`).join(', ')
        : '1 item'
    );
    const grandTotal = inv.grandTotal ?? inv.grand_total ?? 0;
    const paymentMode = (inv.paymentStatus || inv.payment_mode || 'cash').toUpperCase();
    const status = (inv.invoiceStatus || inv.invoice_status || 'completed').toUpperCase();

    const getPaymentBadge = (mode) => {
      switch (mode) {
        case 'CREDIT': return 'bg-warning text-dark';
        case 'CARD':   return 'bg-primary';
        case 'UPI':    return 'bg-info text-dark';
        case 'CASH':   return 'bg-success';
        default:       return 'bg-secondary';
      }
    };

    const getStatusBadge = (st) => {
      switch (st) {
        case 'CANCELLED': return 'bg-danger';
        case 'PENDING':
        case 'PARTIAL':   return 'bg-warning text-dark';
        case 'RETURNED':  return 'bg-secondary';
        case 'COMPLETED':
        case 'PAID':
        default:          return 'bg-success';
      }
    };

    const paymentBadgeClass = getPaymentBadge(paymentMode);
    const statusBadgeClass  = getStatusBadge(status);

    return `
      <tr style="border-bottom: 1px solid var(--border);">
        <td style="padding: 14px 16px; font-weight: 500; color: var(--text-strong);">${formattedDate}</td>
        <td style="padding: 14px 16px; font-weight: 600; color: var(--accent);">${invNumber}</td>
        <td style="padding: 14px 16px;">
          <div style="font-weight: 600; color: var(--text-strong);">${customer}</div>
          <div style="font-size: 0.75rem; color: var(--muted);">${inv.customerPhone || inv.customer_phone_snapshot || ''}</div>
        </td>
        <td style="padding: 14px 16px; max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--text-muted);">${itemsText}</td>
        <td style="padding: 14px 16px; font-weight: 700; color: var(--text-strong);">${formatCurrency(grandTotal)}</td>
        <td style="padding: 14px 16px;">
          <span class="badge rounded-pill ${paymentBadgeClass}">${paymentMode}</span>
        </td>
        <td style="padding: 14px 16px;">
          <span class="badge rounded-pill ${statusBadgeClass}">${status}</span>
        </td>
        <td style="padding: 14px 16px; text-align: right;">
          <button onclick="openReceipt('${inv.id}')" class="btn btn-xs btn-outline" style="padding: 4px 8px; font-size: 0.75rem; border-radius: 4px; cursor:pointer;">Print Receipt</button>
        </td>
      </tr>
    `;
  }).join('');
}

export function renderKPISummary(invoices = []) {
  const kpiTodaySales = document.getElementById('kpiTodaySales');
  const kpiTotalBills = document.getElementById('kpiTotalBills');
  const kpiAvgBill = document.getElementById('kpiAvgBill');
  const kpiRatio = document.getElementById('kpiRatio');

  const totalBillsCount = invoices.length;
  const totalSalesSum = invoices.reduce((acc, inv) => acc + (parseFloat(inv.grandTotal ?? inv.grand_total) || 0), 0);
  const avgBillVal = totalBillsCount > 0 ? (totalSalesSum / totalBillsCount) : 0;

  const cashSales = invoices.filter(i => (i.paymentStatus || i.payment_mode || '').toLowerCase() === 'cash').length;
  const cashPercentage = totalBillsCount > 0 ? Math.round((cashSales / totalBillsCount) * 100) : 100;

  if (kpiTodaySales) kpiTodaySales.textContent = formatCurrency(totalSalesSum);
  if (kpiTotalBills) kpiTotalBills.textContent = String(totalBillsCount);
  if (kpiAvgBill) kpiAvgBill.textContent = formatCurrency(avgBillVal);
  if (kpiRatio) kpiRatio.textContent = `${cashPercentage}% Cash`;
}

window.initDayToDaySelling = initDayToDaySelling;
window.renderSalesTimeline = renderSalesTimeline;
window.onSalesSearchInput = function () {
  const searchInput = document.getElementById('salesSearch');
  if (!searchInput) return;
  const query = searchInput.value.toLowerCase().trim();

  const filtered = _cachedInvoices.filter(inv => {
    const num = (inv.invoiceNumber || inv.invoice_number || '').toLowerCase();
    const cust = (inv.customerName || inv.customer_name_snapshot || '').toLowerCase();
    const phone = (inv.customerPhone || inv.customer_phone_snapshot || '').toLowerCase();
    const mode = (inv.paymentStatus || inv.payment_mode || '').toLowerCase();
    return num.includes(query) || cust.includes(query) || phone.includes(query) || mode.includes(query);
  });

  renderSalesTimeline(filtered);
};
