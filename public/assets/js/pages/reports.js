/**
 * reports.js — Sales reports page controller module
 */

import { fetchSalesReportApi } from '../services/report.service.js';
import { logger } from '../core/logger.js';

export async function initReportsPage() {
  try {
    const today = new Date().toISOString().slice(0, 10);
    const sales = await fetchSalesReportApi(today, today);
    logger.debug('reports', 'Sales report loaded:', sales);
  } catch (err) {
    logger.error('reports', err);
  }
}
