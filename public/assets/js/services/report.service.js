/**
 * report.service.js — Daily sales & reports API service
 */

import { apiRequest } from '../core/api.js';

export async function fetchSalesReportApi(startDate, endDate) {
  const query = new URLSearchParams({ start_date: startDate, end_date: endDate }).toString();
  return apiRequest(`/api/reports/sales?${query}`);
}

export async function fetchDailySalesSummaryApi(date) {
  return apiRequest(`/api/reports/daily-summary?date=${encodeURIComponent(date)}`);
}
