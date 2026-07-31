import { apiRequest } from '../core/api.js';
import { formatCurrency, formatDate, escapeHtml } from '../utils/format.js';
import { logger } from '../core/logger.js';
import { getToken } from '../core/storage.js';
import { API_BASE } from '../core/config.js';

let _allInvoices = [];
let _currentPage = 1;
let _totalPages = 1;
const PER_PAGE = 200;

window.openReceipt = function (invoiceId) {
  const token = getToken();
  const url = `${API_BASE}/api/invoices/${invoiceId}/receipt?token=${encodeURIComponent(token || '')}`;
  window.open(url, 'receipt');
};

export async function initDayToDaySelling() {
  _allInvoices = [];
  _currentPage = 1;
  _totalPages = 1;
  await loadPage(1, true);
}

async function loadPage(page, replace = false) {
  const tbody = document.querySelector('#salesTimelineTable tbody');
  const emptyState = document.getElementById('salesEmptyState');
  const loadMoreBtn = document.getElementById('salesLoadMore');

  try {
    const res = await apiRequest(`/api/invoices?page=${page}&limit=${PER_PAGE}`);
    const invoices = Array.isArray(res) ? res : (res?.data || res?.invoices || []);
    const pagination = res?.pagination || {};

    if (replace) {
      _allInvoices = invoices;
    } else {
      _allInvoices = _allInvoices.concat(invoices);
    }

    _currentPage = pagination?.current_page || page;
    _totalPages = pagination?.total_pages || 1;

    renderSalesTimeline(_allInvoices);
    renderKPISummary(_allInvoices);

    if (loadMoreBtn) {
      const hasMore = _currentPage < _totalPages;
      loadMoreBtn.style.display = hasMore ? 'inline-flex' : 'none';
      loadMoreBtn.disabled = false;
      loadMoreBtn.textContent = hasMore
        ? `Load More (${_currentPage}/${_totalPages})`
        : 'All loaded';
    }

    if (emptyState) emptyState.style.display = invoices.length === 0 ? 'flex' : 'none';
  } catch (err) {
    logger.error('daily_sales', 'Failed to load invoices:', err);
    if (emptyState) emptyState.style.display = 'flex';
  }
}

window.loadMoreSales = async function () {
  const btn = document.getElementById('salesLoadMore');
  if (btn) { btn.disabled = true; btn.textContent = 'Loading...'; }
  await loadPage(_currentPage + 1, false);
};

export function groupInvoicesByDate(invoices = []) {
  const groups = new Map();

  invoices.forEach(inv => {
    const dateStr = inv.billedAt || inv.created_at || inv.billed_at || '';
    const dateKey = dateStr ? new Date(dateStr).toLocaleDateString('en-CA') : '';
    if (!dateKey) return;

    if (!groups.has(dateKey)) {
      groups.set(dateKey, {
        date: dateKey,
        invoiceCount: 0,
        totalSales: 0,
        paidCount: 0,
        creditCount: 0,
      });
    }

    const g = groups.get(dateKey);
    g.invoiceCount += 1;
    g.totalSales += parseFloat(inv.grandTotal ?? inv.grand_total) || 0;

    const status = (inv.invoiceStatus || inv.invoice_status || '').toUpperCase();
    if (status === 'CANCELLED') return;

    const payment = (inv.paymentStatus || inv.payment_mode || '').toUpperCase();
    if (payment === 'PAID' || payment === 'CASH' || payment === 'CARD' || payment === 'UPI') {
      g.paidCount += 1;
    } else {
      g.creditCount += 1;
    }
  });

  return Array.from(groups.values()).sort((a, b) => (a.date < b.date ? 1 : -1));
}

export function renderSalesTimeline(invoices = []) {
  const tableContainer = document.querySelector('#salesTimelineTable')?.closest('.table-container');
  const tbody = document.querySelector('#salesTimelineTable tbody');

  if (tableContainer) tableContainer.style.display = 'block';
  if (!tbody) return;

  const groups = groupInvoicesByDate(invoices);

  if (groups.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="5" style="text-align: center; padding: 48px 24px; color: var(--muted);">
          <div style="font-weight: 600; font-size: 0.95rem; margin-bottom: 4px; color: var(--text-strong);">No sales records found</div>
          <div style="font-size: 0.82rem;">Sales completed through POS Billing will appear here in real-time.</div>
        </td>
      </tr>
    `;
    return;
  }

  tbody.innerHTML = groups.map(g => {
    const safeDate = escapeHtml(g.date);
    const formattedDate = escapeHtml(formatDate(g.date));
    const invoiceCount = escapeHtml(g.invoiceCount);
    const paidCount = escapeHtml(g.paidCount);
    const creditCount = escapeHtml(g.creditCount);
    const detailUrl = `/daily-sales/detail?date=${encodeURIComponent(g.date)}`;
    return `
      <tr style="border-bottom: 1px solid var(--border);">
        <td style="padding: 14px 16px; font-weight: 600;">
          <a href="${detailUrl}" title="View all ${invoiceCount} invoice(s) generated on ${formattedDate}" style="color: var(--accent); font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 5px;">
            ${formattedDate}
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity: 0.7;">
              <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
              <polyline points="15 3 21 3 21 9"></polyline>
              <line x1="10" y1="14" x2="21" y2="3"></line>
            </svg>
          </a>
        </td>
        <td style="padding: 14px 16px;">
          <span class="badge rounded-pill bg-primary">${invoiceCount}</span>
        </td>
        <td style="padding: 14px 16px; font-weight: 700; color: var(--text-strong);">${formatCurrency(g.totalSales)}</td>
        <td style="padding: 14px 16px;">
          <span class="badge rounded-pill bg-success">${paidCount} Paid</span>
        </td>
        <td style="padding: 14px 16px;">
          <span class="badge rounded-pill bg-warning text-dark">${creditCount} Credit / Pending</span>
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
window.groupInvoicesByDate = groupInvoicesByDate;
window.onSalesSearchInput = function () {
  const searchInput = document.getElementById('salesSearch');
  if (!searchInput) return;
  const query = searchInput.value.toLowerCase().trim();

  const filtered = _allInvoices.filter(inv => {
    const num = (inv.invoiceNumber || inv.invoice_number || '').toLowerCase();
    const cust = (inv.customerName || inv.customer_name_snapshot || '').toLowerCase();
    const phone = (inv.customerPhone || inv.customer_phone_snapshot || '').toLowerCase();
    const mode = (inv.paymentStatus || inv.payment_mode || '').toLowerCase();
    return num.includes(query) || cust.includes(query) || phone.includes(query) || mode.includes(query);
  });

  renderSalesTimeline(filtered);
};
