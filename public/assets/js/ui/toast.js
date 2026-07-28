/**
 * toast.js — Toast notification UI module
 * Usage:
 *   import { showToast } from '../ui/toast.js';
 *   showToast('Saved!', 'ok');
 */

/** @param {'ok'|'warn'|'danger'|'info'} type */
const ICONS = {
  ok:     '✅',
  warn:   '⚠️',
  danger: '❌',
  info:   'ℹ️',
};

/**
 * @param {string} message
 * @param {'ok'|'warn'|'danger'|'info'} [type='info']
 * @param {number} [durationMs=3500]
 */
export function showToast(message, type = 'info', durationMs = 3500) {
  let container = document.getElementById('toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toast-container';
    container.className = 'toast-container';
    document.body.appendChild(container);
  }

  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  toast.setAttribute('role', 'status');
  toast.setAttribute('aria-live', 'polite');
  toast.innerHTML = `
    <span class="toast-icon" aria-hidden="true">${ICONS[type] ?? 'ℹ️'}</span>
    <span class="toast-msg">${message}</span>
    <button class="toast-close" aria-label="Dismiss notification">×</button>
  `;

  container.appendChild(toast);

  const dismiss = () => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateX(16px)';
    toast.style.transition = 'all 0.2s ease';
    setTimeout(() => toast.remove(), 200);
  };

  toast.querySelector('.toast-close').addEventListener('click', dismiss);
  setTimeout(dismiss, durationMs);
}
