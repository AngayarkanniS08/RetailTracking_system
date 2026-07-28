/**
 * system-health.js — System Health page controller module
 */

import { fetchSystemHealthApi } from '../services/dashboard.service.js';
import { logger } from '../core/logger.js';

export async function initSystemHealthPage() {
  try {
    const data = await fetchSystemHealthApi();
    logger.debug('health', 'Health check:', data);

    const overallLabel = document.getElementById('healthOverallLabel');
    const overallIcon  = document.getElementById('healthOverallIcon');
    const overallTime  = document.getElementById('healthOverallTime');

    if (overallLabel && data) {
      const isOk = data.status === 'ok' || data.status === 'healthy';
      overallLabel.textContent = isOk ? 'All Systems Operational' : 'Degraded Performance';
      overallLabel.style.color = isOk ? 'var(--ok)' : 'var(--warn)';
      if (overallIcon) overallIcon.textContent = isOk ? '🟢' : '🟡';
      if (overallTime) overallTime.textContent = `Last checked: ${new Date().toLocaleTimeString()}`;

      const components = data.components || {};

      const updateCard = (name, statusObj) => {
        const card = document.querySelector(`[data-component="${name}"]`);
        if (!card) return;
        const indicator = card.querySelector('.health-indicator');
        const text = card.querySelector('.health-status-text');
        const status = typeof statusObj === 'string' ? statusObj : (statusObj?.status ?? 'ok');
        const compOk = status === 'ok' || status === 'healthy' || status === 'up';

        if (indicator) indicator.textContent = compOk ? '🟢' : '🔴';
        if (text) text.textContent = compOk ? 'Healthy (Connected)' : 'Unavailable';
      };

      updateCard('api', components.api ?? 'ok');
      updateCard('database', components.database ?? 'ok');
      updateCard('valkey', components.valkey ?? 'ok');
      updateCard('backup', components.backup ?? 'ok');
      updateCard('disk', components.disk ?? 'ok');
    }
  } catch (err) {
    logger.error('health', err);
    const overallLabel = document.getElementById('healthOverallLabel');
    if (overallLabel) {
      overallLabel.textContent = 'System Health Check Failed';
      overallLabel.style.color = 'var(--danger)';
    }
  }
}
