/**
 * dashboard.js — Dashboard page controller module
 */

import { fetchDashboardStatsApi, fetchTimePeriodStatsApi } from '../services/dashboard.service.js';
import { formatCurrency } from '../utils/format.js';
import { setText } from '../utils/dom.js';
import { logger } from '../core/logger.js';

export async function initDashboardPage() {
  try {
    const stats = await fetchDashboardStatsApi();
    if (stats) {
      setText(document.getElementById('dash-today-sales'), formatCurrency(stats.todaySales ?? 0));
      setText(document.getElementById('dash-total-products'), stats.totalProducts ?? 0);
      setText(document.getElementById('dash-low-stock-count'), stats.lowStockCount ?? 0);
    }
  } catch (err) {
    logger.error('dashboard-page', err);
  }
}
