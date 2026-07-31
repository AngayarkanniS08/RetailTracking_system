/**
 * NotificationController.js — orchestrates the notification pipeline.
 *
 *   init()  → subscribe store→renderers, first fetch, start 60s polling
 *   load()  → fetch backend → commit to store → render (with retry backoff)
 *   openActiveAlertsModal() → open modal, render, background refresh
 *   markRead() / markAllRead() → backend mutation → invalidate → refresh
 *
 * Renderers are pure DOM; the store is the single source of truth; business
 * logic (counts, read state, severity) lives exclusively on the backend.
 */
import { notificationStore } from './NotificationStore.js';
import { NotificationAPI } from './NotificationAPI.js';
import { NotificationRenderer } from './NotificationRenderer.js';
import { logger } from '../core/logger.js';

const POLL_INTERVAL_MS = 60_000;
const RETRY_DELAYS_MS = [1_000, 2_000, 5_000];

class NotificationController {
  constructor() {
    this._timer = null;
    this._inFlight = false;
    this._retryIndex = 0;
    this._modalOpen = false;
    this._unsubscribe = null;
  }

  init() {
    if (this._unsubscribe) return;

    this._unsubscribe = notificationStore.subscribe((state) => {
      NotificationRenderer.renderBadge(state);
      if (this._modalOpen) {
        NotificationRenderer.renderModalList(state);
      }
    });

    this._bindModalEvents();
    this._startPolling();

    // First fetch immediately (also renders the badge).
    this.load();

    // Public API used by inline handlers in legacy markup.
    window.openActiveAlertsModal = () => this.openActiveAlertsModal();
    window.markNotificationRead = (key) => this.markRead([key]);
    window.markAllNotificationsRead = () => this.markAllRead();
    window.closeGlobalLowStockBanner = () => this.closeGlobalLowStockBanner();
    window.refreshNotifications = () => this.load();
  }

  async load() {
    if (this._inFlight) return this._inFlight;
    this._inFlight = (async () => {
      notificationStore.setState({ loading: true, error: null });
      try {
        const data = await NotificationAPI.fetch();
        notificationStore.setState({
          alerts: data.alerts,
          summary: data.summary,
          loading: false,
          lastRefresh: Date.now(),
        });
        this._retryIndex = 0;
      } catch (err) {
        logger.error('notifications:load', err);
        notificationStore.setState({ loading: false, error: err });
        this._scheduleRetry();
      } finally {
        this._inFlight = false;
      }
    })();
    return this._inFlight;
  }

  openActiveAlertsModal() {
    if (typeof window.openModal === 'function') {
      window.openModal('activeAlertsModal');
    }
    this._modalOpen = true;
    NotificationRenderer.renderModalList(notificationStore.getState());
    this.load();
  }

  async markRead(keys = []) {
    if (!Array.isArray(keys) || keys.length === 0) return;
    try {
      await NotificationAPI.markRead(keys);
      await this.load();
    } catch (err) {
      logger.error('notifications:markRead', err);
    }
  }

  async markAllRead() {
    try {
      await NotificationAPI.markAllRead();
      await this.load();
    } catch (err) {
      logger.error('notifications:markAllRead', err);
    }
  }

  closeGlobalLowStockBanner() {
    const banner = document.getElementById('globalLowStockBanner');
    if (banner) banner.style.display = 'none';
  }

  _startPolling() {
    if (this._timer) clearInterval(this._timer);
    this._timer = setInterval(() => this.load(), POLL_INTERVAL_MS);
  }

  _scheduleRetry() {
    const delay = RETRY_DELAYS_MS[this._retryIndex] ?? RETRY_DELAYS_MS[RETRY_DELAYS_MS.length - 1];
    this._retryIndex = Math.min(this._retryIndex + 1, RETRY_DELAYS_MS.length - 1);
    setTimeout(() => this.load(), delay);
  }

  _bindModalEvents() {
    const container = document.getElementById('activeAlertsModalList');
    if (container) {
      container.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-read-key]');
        if (btn?.dataset.readKey) {
          this.markRead([btn.dataset.readKey]);
        }
      });
    }

    const markAllBtn = document.getElementById('notificationsMarkAllBtn');
    if (markAllBtn) {
      markAllBtn.addEventListener('click', () => this.markAllRead());
    }

    // Track modal open state so the list re-renders while visible.
    const overlay = document.getElementById('activeAlertsModal');
    if (overlay) {
      const observer = new MutationObserver(() => {
        const isOpen = overlay.classList.contains('active');
        this._modalOpen = isOpen;
        if (isOpen) NotificationRenderer.renderModalList(notificationStore.getState());
      });
      observer.observe(overlay, { attributes: true, attributeFilter: ['class', 'style'] });
    }
  }
}

let controller = null;

export function initNotifications() {
  if (!controller) controller = new NotificationController();
  controller.init();
  return controller;
}

export function getNotificationController() {
  return controller;
}
