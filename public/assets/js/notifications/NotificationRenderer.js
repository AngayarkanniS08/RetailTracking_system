/**
 * NotificationRenderer.js — owns ALL notification DOM output.
 *
 * Badge, modal list, loading / empty / error states. It contains zero business
 * logic — it renders whatever the backend payload in the store says.
 */
import { escapeHtml } from '../utils/format.js';

const SEVERITY_STYLES = {
  critical: { color: '#ef4444', bg: 'rgba(239, 68, 68, 0.1)' },
  warning:  { color: '#d97706', bg: 'rgba(217, 119, 6, 0.12)' },
  info:     { color: '#2563eb', bg: 'rgba(37, 99, 235, 0.1)' },
};

function severityStyle(severity) {
  return SEVERITY_STYLES[severity] || SEVERITY_STYLES.info;
}

function renderBadge(state) {
  const badge = document.getElementById('topbarAlertBadge');
  if (!badge) return;

  const unread = state.summary.unread || 0;
  if (unread > 0) {
    badge.textContent = String(unread > 99 ? '99+' : unread);
    badge.classList.remove('hidden');
    badge.setAttribute('aria-label', `${unread} unread notification${unread === 1 ? '' : 's'}`);
  } else {
    badge.classList.add('hidden');
    badge.setAttribute('aria-label', 'No unread notifications');
  }
}

function renderModalList(state) {
  const container = document.getElementById('activeAlertsModalList');
  if (!container) return;

  if (state.loading && state.alerts.length === 0) {
    container.innerHTML = `
      <div style="text-align: center; padding: 28px 16px; color: var(--muted);">
        <div class="spinner-border spinner-border-sm" role="status"></div>
        <div style="font-size: 0.85rem; margin-top: 8px;">Loading notifications...</div>
      </div>
    `;
    return;
  }

  if (state.error && state.alerts.length === 0) {
    container.innerHTML = `
      <div style="text-align: center; padding: 28px 16px; color: var(--danger); font-size: 0.85rem;">
        <div style="font-size: 1.6rem; margin-bottom: 6px;">⚠️</div>
        <div>Could not load notifications. Please try again.</div>
      </div>
    `;
    return;
  }

  if (state.alerts.length === 0) {
    container.innerHTML = `
      <div style="text-align: center; padding: 32px 16px;">
        <div style="font-size: 2rem; margin-bottom: 8px;">✅</div>
        <div style="font-weight: 600; font-size: 0.95rem; color: var(--text-strong);">You're all caught up</div>
        <div style="font-size: 0.8rem; color: var(--muted); margin-top: 4px;">No alerts right now. New notifications will appear here.</div>
      </div>
    `;
    return;
  }

  container.innerHTML = state.alerts.map(renderAlertCard).join('');
}

function renderAlertCard(alert) {
  const style = severityStyle(alert.severity);
  const meta = alert.metadata || {};

  const metaParts = [];
  if (meta.stock !== undefined && meta.unit) metaParts.push(`${meta.stock} ${meta.unit} in stock`);
  if (meta.rop !== undefined) metaParts.push(`reorder point ${meta.rop}`);
  if (meta.oldest_batch_days !== undefined) metaParts.push(`oldest batch ${meta.oldest_batch_days}d`);
  const metaLine = metaParts.length > 0
    ? `<div style="font-size: 0.75rem; color: var(--muted); margin-top: 4px;">${escapeHtml(metaParts.join(' · '))}</div>`
    : '';

  const unreadDot = alert.read ? '' : '<span class="notification-unread-dot" title="Unread"></span>';

  return `
    <div class="notification-card" style="padding: 12px 16px; border-radius: 10px; background: var(--surface-container-low); border: 1px solid var(--border); display: flex; align-items: flex-start; gap: 12px;">
      <div style="width: 36px; height: 36px; border-radius: 50%; background: ${style.bg}; color: ${style.color}; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 1.1rem;">${escapeHtml(alert.icon || '🔔')}</div>
      <div style="flex: 1; min-width: 0;">
        <div style="display: flex; align-items: center; gap: 8px;">
          <span class="badge rounded-pill" style="background: ${style.bg}; color: ${style.color}; font-size: 0.66rem; text-transform: uppercase;">${escapeHtml(alert.severity)}</span>
          <span style="font-weight: 600; font-size: 0.9rem; color: var(--text-strong);">${escapeHtml(alert.title)}</span>
          ${unreadDot}
        </div>
        <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 4px; line-height: 1.45;">${escapeHtml(alert.description)}</div>
        ${metaLine}
        <div style="display: flex; align-items: center; gap: 12px; margin-top: 8px;">
          ${alert.action_url ? `<a href="${escapeHtml(alert.action_url)}" class="btn btn-outline btn-sm" style="padding: 3px 10px; font-size: 0.72rem; border-radius: 6px; text-decoration: none;">View</a>` : ''}
          ${alert.read ? '' : `<button type="button" class="btn btn-sm" data-read-key="${escapeHtml(alert.key)}" style="padding: 3px 10px; font-size: 0.72rem; border-radius: 6px; background: transparent; color: var(--muted); border: 1px solid var(--border); cursor: pointer;">Mark read</button>`}
        </div>
      </div>
    </div>
  `;
}

export const NotificationRenderer = {
  renderBadge,
  renderModalList,
};
