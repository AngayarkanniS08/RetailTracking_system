/**
 * system-health.js — System Health page controller module
 */

import { fetchSystemHealthApi } from '../services/dashboard.service.js';
import { logger } from '../core/logger.js';

export async function initSystemHealthPage() {
  try {
    const health = await fetchSystemHealthApi();
    logger.debug('health', 'Health check:', health);
  } catch (err) {
    logger.error('health', err);
  }
}
