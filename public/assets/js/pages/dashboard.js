import { fetchDashboardStatsApi } from '../services/dashboard.service.js';
import { apiRequest } from '../core/api.js';
import { formatCurrency, escapeHtml } from '../utils/format.js';
import { setText } from '../utils/dom.js';
import { logger } from '../core/logger.js';
import { mapDashboardStats, mapStockIntel } from '../services/dashboard.dto.js';
import { validateShape, dashboardStatsSchema, stockIntelSchema, ValidationError } from '../utils/validate.js';
import { showToast } from '../ui/toast.js';

const DASH = '—';

function displayCurrency(value) {
  if (value === null || value === undefined || value === false) return DASH;
  return formatCurrency(value);
}

function displayPercent(value) {
  if (value === null || value === undefined || value === false) return DASH;
  return `${value}% Margin`;
}

function displayCount(value, suffix = '') {
  if (value === null || value === undefined) return DASH;
  return `${value}${suffix}`;
}

const STOCK_STATUS_MAP = {
  in_stock: { label: 'In Stock', badgeClass: 'bg-success' },
  healthy: { label: 'Healthy Stock', badgeClass: 'bg-success' },
  low_stock: { label: 'Low Stock', badgeClass: 'bg-warning text-dark' },
  out_of_stock: { label: 'Out of Stock', badgeClass: 'bg-danger' },
  unknown: { label: 'Unknown', badgeClass: 'bg-secondary' },
};

const state = {
  activePeriod: 'today',
  activeProductTab: 'all',
  productFilterText: '',
  stockIntelData: null,
  dashboardStats: null,
  uiState: 'loading',
  stockIntelState: 'loading',
};

function renderSkeletonLoading() {
  const kpiIds = ['kpiRevenue', 'kpiBills', 'kpiProfit', 'kpiCredit', 'sumSalesRev', 'sumPurAmount'];
  kpiIds.forEach((id) => {
    const el = document.getElementById(id);
    if (el) {
      el.dataset.originalHtml = el.innerHTML;
      el.innerHTML = `<span class="skeleton-box" style="display:inline-block; width: 90px; height: 1.75rem; vertical-align: middle;"></span>`;
    }
  });
}

function clearSkeletonLoading() {
  const kpiIds = ['kpiRevenue', 'kpiBills', 'kpiProfit', 'kpiCredit', 'sumSalesRev', 'sumPurAmount'];
  kpiIds.forEach((id) => {
    const el = document.getElementById(id);
    if (el && el.dataset.originalHtml && el.querySelector('.skeleton-box')) {
      el.innerHTML = el.dataset.originalHtml;
    }
  });
}

function showErrorBanner(message, onRetry) {
  const container = document.getElementById('dashErrorContainer');
  if (!container) return;
  const retryBtn = typeof onRetry === 'function'
    ? `<button class="btn btn-sm btn-outline" style="margin-left: 12px;" data-retry>Retry</button>`
    : '';
  container.innerHTML = `<div class="alert alert-danger" style="margin-bottom: 1rem; display: flex; align-items: center; justify-content: space-between;">${message}${retryBtn}</div>`;
  const retryEl = container.querySelector('[data-retry]');
  if (retryEl) retryEl.addEventListener('click', () => onRetry());
}

function clearErrorBanner() {
  const container = document.getElementById('dashErrorContainer');
  if (container) container.innerHTML = '';
}

function renderUIState() {
  if (state.uiState === 'loading') {
    renderSkeletonLoading();
  } else if (state.uiState === 'empty') {
    showToast('No dashboard data available yet. Start by adding sales or inventory.', 'info', 5000);
  } else if (state.uiState === 'forbidden') {
    showToast('You do not have access to view dashboard data.', 'danger', 0);
  } else if (state.uiState === 'offline') {
    showToast('Could not reach the server. Check your connection.', 'danger', 0);
  } else if (state.uiState === 'ready') {
    clearSkeletonLoading();
  }
}

function renderExecutiveMetrics(stats) {
  if (!stats) return;

  const kpis = stats.executive_kpis || {};
  const salesSum = stats.sales_summary || {};
  const purSum = stats.purchase_summary || {};

  const revVal = kpis.revenue?.value ?? null;
  const revGrowth = kpis.revenue?.growth_pct ?? null;
  setText(document.getElementById('kpiRevenue'), displayCurrency(revVal));
  const revTrendEl = document.getElementById('kpiRevTrend');
  if (revTrendEl) {
    if (revGrowth === null) {
      revTrendEl.textContent = DASH;
      revTrendEl.className = 'kpi-trend';
    } else {
      revTrendEl.textContent = `${revGrowth >= 0 ? '↑' : '↓'} ${Math.abs(revGrowth)}%`;
      revTrendEl.className = `kpi-trend ${revGrowth >= 0 ? 'trend-up' : 'trend-down'}`;
    }
  }

  const billCount = kpis.bills?.count ?? null;
  const avgTicket = kpis.bills?.avg_ticket ?? null;
  setText(document.getElementById('kpiBills'), displayCount(billCount));
  setText(document.getElementById('kpiAvgTicket'), displayCurrency(avgTicket));

  setText(document.getElementById('kpiProfit'), displayCurrency(kpis.profit?.value));
  setText(document.getElementById('kpiProfitMargin'), displayPercent(kpis.profit?.margin_pct));

  setText(document.getElementById('kpiCredit'), displayCurrency(kpis.outstanding_credit));
  setText(document.getElementById('creditTotalBalance'), displayCurrency(kpis.outstanding_credit));

  setText(document.getElementById('sumSalesRev'), displayCurrency(salesSum.revenue));
  setText(document.getElementById('sumSalesBills'), displayCount(salesSum.bills));
  setText(document.getElementById('sumSalesAvg'), displayCurrency(salesSum.avg_ticket));

  setText(document.getElementById('chipTodayRev'), displayCurrency(stats.today?.revenue));
  setText(document.getElementById('chipWeekRev'), displayCurrency(stats.week?.revenue));
  setText(document.getElementById('chipMonthRev'), displayCurrency(stats.month?.revenue));

  const periodLabelMap = {
    today: 'Today',
    yesterday: 'Yesterday',
    week: 'This Week',
    month: 'This Month',
    quarter: 'This Quarter',
    year: 'This Year',
  };
  const activeLabel = periodLabelMap[state.activePeriod] || 'Today';
  setText(document.getElementById('salesSummaryChip'), activeLabel);
  setText(document.getElementById('purchaseSummaryChip'), activeLabel);

  setText(document.getElementById('sumPurAmount'), displayCurrency(purSum.amount));
  setText(document.getElementById('sumPurPaid'), displayCurrency(purSum.paid));
  setText(document.getElementById('sumPurPending'), displayCurrency(purSum.pending));
  setText(document.getElementById('chipPurCount'), displayCount(purSum.count));
  setText(document.getElementById('chipPurAvg'), displayCurrency(purSum.avg_purchase));

  setText(document.getElementById('invTotalValue'), displayCurrency(stats.stock_value));

  const hasData = revVal !== null && revVal > 0;
  if (!hasData && state.uiState !== 'loading') {
    state.uiState = 'empty';
    renderUIState();
  } else if (hasData && state.uiState === 'empty') {
    state.uiState = 'ready';
    renderUIState();
  }
}

function drawSalesCanvasChart(canvasId, chartData) {
  const canvas = document.getElementById(canvasId);
  if (!canvas) return;

  const labels = chartData?.labels;
  const thisValues = chartData?.thisWeek;
  const lastValues = chartData?.lastWeek;
  const hasData = labels && thisValues && thisValues.length > 0;

  const ctx = canvas.getContext('2d');
  const dpr = window.devicePixelRatio || 1;
  const rect = canvas.getBoundingClientRect();
  const width = rect.width || 600;
  const height = 240;

  ctx.setTransform(1, 0, 0, 1, 0, 0);
  canvas.width = width * dpr;
  canvas.height = height * dpr;
  ctx.scale(dpr, dpr);

  ctx.clearRect(0, 0, width, height);

  if (!hasData) {
    ctx.fillStyle = '#64748b';
    ctx.font = '14px sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText('No chart data available', width / 2, height / 2);
    return;
  }

  const padding = { top: 30, right: 20, bottom: 40, left: 50 };
  const chartWidth = width - padding.left - padding.right;
  const chartHeight = height - padding.top - padding.bottom;
  const maxVal = Math.max(...thisValues, ...lastValues, 1000);

  ctx.strokeStyle = 'rgba(148, 163, 184, 0.2)';
  ctx.fillStyle = '#64748b';
  ctx.font = '11px sans-serif';
  ctx.textAlign = 'right';

  const gridSteps = 4;
  for (let i = 0; i <= gridSteps; i++) {
    const y = padding.top + (chartHeight / gridSteps) * i;
    const val = Math.round(maxVal - (maxVal / gridSteps) * i);
    ctx.beginPath();
    ctx.moveTo(padding.left, y);
    ctx.lineTo(width - padding.right, y);
    ctx.stroke();
    ctx.fillText(`₹${val}`, padding.left - 8, y + 4);
  }

  const groupWidth = chartWidth / labels.length;
  const barWidth = Math.min(groupWidth * 0.35, 18);
  const gap = 4;

  labels.forEach((label, index) => {
    const groupX = padding.left + index * groupWidth + groupWidth / 2;

    const hLast = (lastValues[index] / maxVal) * chartHeight;
    const yLast = padding.top + chartHeight - hLast;
    ctx.fillStyle = '#2563eb';
    ctx.beginPath();
    if (typeof ctx.roundRect === 'function') {
      ctx.roundRect(groupX - barWidth - gap / 2, yLast, barWidth, hLast, [3, 3, 0, 0]);
    } else {
      ctx.rect(groupX - barWidth - gap / 2, yLast, barWidth, hLast);
    }
    ctx.fill();

    const hThis = (thisValues[index] / maxVal) * chartHeight;
    const yThis = padding.top + chartHeight - hThis;
    ctx.fillStyle = '#10b981';
    ctx.beginPath();
    if (typeof ctx.roundRect === 'function') {
      ctx.roundRect(groupX + gap / 2, yThis, barWidth, hThis, [3, 3, 0, 0]);
    } else {
      ctx.rect(groupX + gap / 2, yThis, barWidth, hThis);
    }
    ctx.fill();

    ctx.fillStyle = '#64748b';
    ctx.textAlign = 'center';
    ctx.fillText(label, groupX, height - 12);
  });
}

async function fetchWithRetry(fn, maxRetries = 3) {
  let lastErr;
  for (let attempt = 0; attempt <= maxRetries; attempt++) {
    try {
      return await fn();
    } catch (err) {
      lastErr = err;
      if (attempt < maxRetries) {
        const delay = Math.pow(2, attempt) * 1000;
        await new Promise(r => setTimeout(r, delay));
      }
    }
  }
  throw lastErr;
}

async function fetchStockIntelData() {
  state.stockIntelState = 'loading';
  const skeletonEl = document.getElementById('stockIntelSkeleton');
  if (skeletonEl) skeletonEl.style.display = 'block';

  try {
    const raw = await fetchWithRetry(() => apiRequest('/api/dashboard/stock-intel'));
    validateShape(raw, stockIntelSchema);
    state.stockIntelData = mapStockIntel(raw);
    state.stockIntelState = 'ready';
    renderTabbedProductPerformance();
    renderInventoryHealthAndAlerts();
  } catch (err) {
    logger.error('dashboard-stock-intel', err);
    state.stockIntelState = 'error';
    showErrorBanner('Failed to load stock intelligence data.', () => fetchStockIntelData());
  } finally {
    if (skeletonEl) skeletonEl.style.display = 'none';
  }
}

function renderStockStatusCell(status) {
  const key = (status || '').toLowerCase().replace(/\s+/g, '_');
  const s = STOCK_STATUS_MAP[key] || STOCK_STATUS_MAP[status] || STOCK_STATUS_MAP.unknown;
  return `<span class="badge rounded-pill ${s.badgeClass}">${s.label}</span>`;
}

function renderTabbedProductPerformance() {
  const tbody = document.getElementById('productPerformanceBody');
  if (!tbody) return;

  const data = state.stockIntelData || {};
  let products = [];

  const highList = (data.high_selling || []).map(p => ({ ...p, rank: 'High Selling', badgeClass: 'bg-success' }));
  const normalList = (data.normal_selling || []).map(p => ({ ...p, rank: 'Normal', badgeClass: 'bg-info text-dark' }));
  const lowList = (data.low_selling || []).map(p => ({ ...p, rank: 'Low Selling', badgeClass: 'bg-warning text-dark' }));

  if (state.activeProductTab === 'high') products = highList;
  else if (state.activeProductTab === 'normal') products = normalList;
  else if (state.activeProductTab === 'low') products = lowList;
  else products = [...highList, ...normalList, ...lowList];

  if (state.productFilterText.trim()) {
    const query = state.productFilterText.toLowerCase();
    products = products.filter(p => (p.name || '').toLowerCase().includes(query));
  }

  if (products.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="6" style="text-align: center; padding: 24px; color: var(--muted);">
          No product performance records found for this view.
        </td>
      </tr>
    `;
    return;
  }

  tbody.innerHTML = products.map(item => {
    const name = escapeHtml(item.name || 'Product');
    const qty = item.qty_sold;
    const rev = item.revenue;
    const rank = escapeHtml(item.rank || 'Normal');
    const badgeClass = escapeHtml(item.badgeClass || 'bg-info text-dark');
    const stockStatus = item.stock_status || 'unknown';

    return `
      <tr>
        <td style="font-weight: 600; color: var(--text-strong);">${name}</td>
        <td style="font-variant-numeric: tabular-nums;">${displayCount(qty, ' pcs')}</td>
        <td style="font-weight: 600; font-variant-numeric: tabular-nums;">${displayCurrency(rev)}</td>
        <td><span class="badge rounded-pill ${badgeClass}">${rank}</span></td>
        <td>${renderStockStatusCell(stockStatus)}</td>
        <td style="text-align: right;">
          <a href="/products" class="btn btn-outline btn-xs" style="padding: 3px 8px; font-size: 0.72rem;">View Item</a>
        </td>
      </tr>
    `;
  }).join('');
}

function renderInventoryHealthAndAlerts() {
  const health = state.stockIntelData?.inventory_health || {};
  const alertSummary = state.stockIntelData?.alert_summary || {};
  const oldList = state.stockIntelData?.old_stock || [];

  const outStockCount = health.out_of_stock ?? 0;
  const lowStockCount = health.low_stock ?? 0;
  const healthyCount = health.healthy_count ?? 0;
  const alertStatus = alertSummary.status || 'no_data';

  setText(document.getElementById('invOutStockCount'), String(outStockCount));
  setText(document.getElementById('invLowStockCount'), String(lowStockCount));
  setText(document.getElementById('invHealthyCount'), String(healthyCount));

  const alertContainer = document.getElementById('priorityAlertCenterList');
  if (!alertContainer) return;

  let alertHtml = '';

  if (alertStatus === 'no_data') {
    alertHtml = `
      <div class="alert-item alert-info">
        <div class="alert-icon">ℹ️</div>
        <div class="alert-content">
          <div class="alert-title">No Alert Data Available</div>
          <div class="alert-desc">Insufficient inventory data to determine health status.</div>
        </div>
      </div>
    `;
  } else {
    if (outStockCount > 0) {
      alertHtml += `
        <div class="alert-item alert-danger">
          <div class="alert-icon">🚫</div>
          <div class="alert-content">
            <div class="alert-title">${outStockCount} Products Out of Stock</div>
            <div class="alert-desc">These products have zero inventory. Consider restocking immediately.</div>
          </div>
        </div>
      `;
    }
    if (lowStockCount > 0) {
      alertHtml += `
        <div class="alert-item alert-warn">
          <div class="alert-icon">⚠️</div>
          <div class="alert-content">
            <div class="alert-title">${lowStockCount} Products at Low Stock Level</div>
            <div class="alert-desc">Items are approaching critical inventory thresholds.</div>
          </div>
        </div>
      `;
    }
    if (oldList.length > 0) {
      alertHtml += `
        <div class="alert-item alert-danger">
          <div class="alert-icon">📦</div>
          <div class="alert-content">
            <div class="alert-title">${oldList.length} Batches Exceeding 30 Days Inventory Age</div>
            <div class="alert-desc">Consider running promotional discounts or supplier returns to free up capital.</div>
          </div>
        </div>
      `;
    }
    if (!alertHtml) {
      alertHtml = `
        <div class="alert-item alert-info">
          <div class="alert-icon">✅</div>
          <div class="alert-content">
            <div class="alert-title">All Operational Health Signals Nominal</div>
            <div class="alert-desc">Zero critical alerts or stock warnings requiring immediate executive action.</div>
          </div>
        </div>
      `;
    }
  }

  alertContainer.innerHTML = alertHtml;
}

function bindEvents() {
  const globalFilterSelect = document.getElementById('dashGlobalFilter');
  if (globalFilterSelect) {
    globalFilterSelect.addEventListener('change', async (e) => {
      state.activePeriod = e.target.value;
      clearErrorBanner();
      state.uiState = 'loading';
      renderSkeletonLoading();
      try {
        const raw = await fetchWithRetry(() => fetchDashboardStatsApi(state.activePeriod));
        validateShape(raw, dashboardStatsSchema);
        state.dashboardStats = mapDashboardStats(raw);
        state.uiState = 'ready';
        clearSkeletonLoading();
        renderExecutiveMetrics(state.dashboardStats);
        drawSalesCanvasChart('salesComparisonChart', state.dashboardStats.chartData);
      } catch (err) {
        logger.error('dashboard-filter-change', err);
        clearSkeletonLoading();
        state.uiState = 'error';
        renderUIState();
        showErrorBanner('Failed to load dashboard stats.', () => {
          globalFilterSelect.dispatchEvent(new Event('change'));
        });
      }
    });
  }

  const tabButtons = document.querySelectorAll('#dashboard .perf-tab');
  tabButtons.forEach(btn => {
    btn.addEventListener('click', (e) => {
      tabButtons.forEach(b => {
        b.classList.remove('active');
        b.setAttribute('aria-selected', 'false');
      });
      btn.classList.add('active');
      btn.setAttribute('aria-selected', 'true');
      state.activeProductTab = btn.dataset.tab || 'all';
      renderTabbedProductPerformance();
    });
  });

  const searchInput = document.getElementById('prodPerfSearch');
  if (searchInput) {
    searchInput.addEventListener('input', (e) => {
      state.productFilterText = e.target.value;
      renderTabbedProductPerformance();
    });
  }

  window.addEventListener('resize', () => {
    drawSalesCanvasChart('salesComparisonChart', state.dashboardStats?.chartData);
  });
}

export async function initDashboardPage() {
  try {
    state.uiState = 'loading';
    renderUIState();
    clearErrorBanner();
    renderSkeletonLoading();
    bindEvents();

    const raw = await fetchWithRetry(() => fetchDashboardStatsApi(state.activePeriod));
    validateShape(raw, dashboardStatsSchema);
    state.dashboardStats = mapDashboardStats(raw);
    state.uiState = 'ready';
    clearSkeletonLoading();

    renderExecutiveMetrics(state.dashboardStats);
    drawSalesCanvasChart('salesComparisonChart', state.dashboardStats.chartData);

    await fetchStockIntelData();
  } catch (err) {
    logger.error('dashboard-init', err);
    clearSkeletonLoading();

    if (err instanceof TypeError && err.message === 'Failed to fetch') {
      state.uiState = 'offline';
    } else if (err instanceof ValidationError) {
      state.uiState = 'error';
    } else {
      state.uiState = 'error';
    }
    renderUIState();
    showErrorBanner('Failed to load dashboard data.', () => {
      state.uiState = 'loading';
      initDashboardPage();
    });
  }
}