/**
 * toast.js — Enterprise Notification Engine (Bootstrap 5 Toast & Alert UI)
 *
 * Features:
 *  - Fluent API: notify.success(), notify.error(), notify.warning(), notify.info(), notify.loading()
 *  - Queue management: Max 3 visible notifications simultaneously
 *  - Deduplication: Suppresses duplicate messages within a 2000ms window
 *  - Priority sorting: Critical errors take visual precedence
 *  - Persistent errors: Error/danger notifications require manual dismissal (sticky)
 *  - Accessibility: Full ARIA live region and role attributes
 *
 * Usage:
 *   import { notify, showToast } from '../ui/toast.js';
 *   notify.success('Customer saved successfully');
 *   notify.error('Failed to process payment');
 */

const TYPE_CONFIG = {
  success:   { bg: 'success',   icon: '✓', role: 'status',    live: 'polite',    autohide: true,  priority: 1 },
  ok:        { bg: 'success',   icon: '✓', role: 'status',    live: 'polite',    autohide: true,  priority: 1 },
  danger:    { bg: 'danger',    icon: '✕', role: 'alert',     live: 'assertive', autohide: false, priority: 3 },
  error:     { bg: 'danger',    icon: '✕', role: 'alert',     live: 'assertive', autohide: false, priority: 3 },
  warning:   { bg: 'warning',   icon: '⚠', role: 'alert',     live: 'assertive', autohide: true,  priority: 2 },
  warn:      { bg: 'warning',   icon: '⚠', role: 'alert',     live: 'assertive', autohide: true,  priority: 2 },
  info:      { bg: 'info',      icon: 'ℹ', role: 'status',    live: 'polite',    autohide: true,  priority: 1 },
  primary:   { bg: 'primary',   icon: 'ℹ', role: 'status',    live: 'polite',    autohide: true,  priority: 1 },
  secondary: { bg: 'secondary', icon: '⚙', role: 'status',    live: 'polite',    autohide: true,  priority: 1 },
  dark:      { bg: 'dark',      icon: '📢', role: 'status',   live: 'polite',    autohide: true,  priority: 1 },
  light:     { bg: 'light',     icon: '💬', role: 'status',   live: 'polite',    autohide: true,  priority: 1 },
  loading:   { bg: 'primary',   icon: 'spinner', role: 'status', live: 'polite', autohide: false, priority: 2 }
};

class NotificationManager {
  constructor(options = {}) {
    this.maxVisible = options.maxVisible || 3;
    this.dedupeWindow = options.dedupeWindow || 2000;
    this.queue = [];
    this.activeToasts = new Map(); // id -> { element, timer }
    this.recentHistory = new Map(); // message -> timestamp
    this.container = null;
    this.idCounter = 0;
    this.position = options.position || 'bottom-0 end-0'; // Default bottom-right offset
  }

  _getContainer() {
    if (!this.container || !document.body.contains(this.container)) {
      this.container = document.getElementById('toast-container');
      if (!this.container) {
        this.container = document.createElement('div');
        this.container.id = 'toast-container';
        this.container.className = `toast-container position-fixed ${this.position} p-3`;
        this.container.style.zIndex = '1090';
        document.body.appendChild(this.container);
      }
    }
    return this.container;
  }

  _isDuplicate(message) {
    const now = Date.now();
    const lastTime = this.recentHistory.get(message);
    if (lastTime && now - lastTime < this.dedupeWindow) {
      return true;
    }
    this.recentHistory.set(message, now);
    // Cleanup old history entries
    if (this.recentHistory.size > 50) {
      for (const [msg, time] of this.recentHistory.entries()) {
        if (now - time > this.dedupeWindow * 2) {
          this.recentHistory.delete(msg);
        }
      }
    }
    return false;
  }

  show(message, type = 'info', options = {}) {
    if (typeof message !== 'string') {
      message = String(message || '');
    }

    if (!options.force && this._isDuplicate(message)) {
      return null;
    }

    const id = options.id || `toast-${++this.idCounter}-${Date.now()}`;
    const cfg = TYPE_CONFIG[type] || TYPE_CONFIG.info;
    const duration = options.durationMs ?? (cfg.autohide ? 3500 : 0);

    const notification = {
      id,
      message,
      type,
      config: cfg,
      duration,
      title: options.title || '',
      autohide: options.autohide ?? cfg.autohide,
      priority: cfg.priority
    };

    if (this.activeToasts.size < this.maxVisible) {
      this._render(notification);
    } else {
      // Add to queue and sort by priority (higher priority first)
      this.queue.push(notification);
      this.queue.sort((a, b) => b.priority - a.priority);
    }

    return id;
  }

  _render(item) {
    const container = this._getContainer();
    const toastEl = document.createElement('div');
    const isLight = item.config.bg === 'light' || item.config.bg === 'warning';
    const textColor = isLight ? 'text-dark' : 'text-white';
    const closeBtnColor = isLight ? '' : 'btn-close-white';

    toastEl.id = item.id;
    toastEl.className = `toast align-items-center text-bg-${item.config.bg} border-0 show shadow-sm mb-2`;
    toastEl.setAttribute('role', item.config.role);
    toastEl.setAttribute('aria-live', item.config.live);
    toastEl.setAttribute('aria-atomic', 'true');
    toastEl.style.transition = 'all 0.25s ease-in-out';

    let iconHtml = `<strong class="me-2">${item.config.icon}</strong>`;
    if (item.config.icon === 'spinner') {
      iconHtml = `<div class="spinner-border spinner-border-sm me-2" role="status"><span class="visually-hidden">Loading...</span></div>`;
    }

    toastEl.innerHTML = `
      <div class="d-flex ${textColor}">
        <div class="toast-body d-flex align-items-center">
          ${iconHtml}
          <div>
            ${item.title ? `<div class="fw-bold mb-1">${item.title}</div>` : ''}
            <span>${item.message}</span>
          </div>
        </div>
        <button type="button" class="btn-close ${closeBtnColor} me-2 m-auto" aria-label="Close"></button>
      </div>
    `;

    container.appendChild(toastEl);

    let timer = null;
    const dismiss = () => this.dismiss(item.id);

    toastEl.querySelector('.btn-close').addEventListener('click', dismiss);

    if (item.autohide && item.duration > 0) {
      timer = setTimeout(dismiss, item.duration);
    }

    this.activeToasts.set(item.id, { element: toastEl, timer });
  }

  dismiss(id) {
    const active = this.activeToasts.get(id);
    if (active) {
      if (active.timer) clearTimeout(active.timer);
      const el = active.element;
      el.style.opacity = '0';
      el.style.transform = 'translateY(-8px)';
      setTimeout(() => {
        if (el.parentNode) el.parentNode.removeChild(el);
        this.activeToasts.delete(id);
        this._processQueue();
      }, 250);
    } else {
      // Remove from queue if pending
      this.queue = this.queue.filter(item => item.id !== id);
    }
  }

  update(id, options = {}) {
    const active = this.activeToasts.get(id);
    if (active) {
      const el = active.element;
      if (options.message) {
        const msgSpan = el.querySelector('.toast-body span:last-child');
        if (msgSpan) msgSpan.textContent = options.message;
      }
      if (options.type && TYPE_CONFIG[options.type]) {
        const newCfg = TYPE_CONFIG[options.type];
        el.className = `toast align-items-center text-bg-${newCfg.bg} border-0 show shadow-sm mb-2`;
      }
      if (options.autohide !== undefined && options.durationMs) {
        if (active.timer) clearTimeout(active.timer);
        if (options.autohide) {
          active.timer = setTimeout(() => this.dismiss(id), options.durationMs);
        }
      }
    }
  }

  clear() {
    this.queue = [];
    for (const id of Array.from(this.activeToasts.keys())) {
      this.dismiss(id);
    }
  }

  _processQueue() {
    if (this.queue.length > 0 && this.activeToasts.size < this.maxVisible) {
      const nextItem = this.queue.shift();
      this._render(nextItem);
    }
  }
}

// Global Singleton Instance
const manager = new NotificationManager();

export const notify = {
  success: (msg, opts) => manager.show(msg, 'success', opts),
  error:   (msg, opts) => manager.show(msg, 'danger', opts),
  warning: (msg, opts) => manager.show(msg, 'warning', opts),
  info:    (msg, opts) => manager.show(msg, 'info', opts),
  loading: (msg, opts) => manager.show(msg, 'loading', opts),
  dismiss: (id) => manager.dismiss(id),
  update:  (id, opts) => manager.update(id, opts),
  clear:   () => manager.clear()
};

/**
 * Backward-compatible showToast helper
 */
export function showToast(message, type = 'info', durationMs = 3500) {
  const normalizedType = type === 'ok' ? 'success' : (type === 'warn' ? 'warning' : type);
  return manager.show(message, normalizedType, { durationMs });
}

export { NotificationManager };
