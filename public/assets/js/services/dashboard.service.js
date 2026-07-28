/**
 * dashboard.service.js — Dashboard stats & analytics API service
 */

import { apiRequest } from '../core/api.js';

export async function fetchDashboardStatsApi() {
  return apiRequest('/api/dashboard/stats');
}

export async function fetchTimePeriodStatsApi() {
  return apiRequest('/api/dashboard/time-period-stats');
}

export async function fetchLowStockItemsApi() {
  return apiRequest('/api/dashboard/low-stock');
}

export async function fetchSystemHealthApi() {
  return apiRequest('/api/health');
}
