/**
 * NotificationStore.js — single source of truth for notification UI state.
 *
 * Holds the last-good backend payload so a failed refresh can fall back to
 * cached data instead of flashing an error. Renderers subscribe and re-render
 * on every committed change.
 */
export class NotificationStore {
  constructor() {
    this.state = {
      alerts: [],
      summary: { total: 0, unread: 0, critical: 0, warning: 0, info: 0 },
      loading: false,
      error: null,
      lastRefresh: null,
    };
    this.listeners = new Set();
  }

  getState() {
    return this.state;
  }

  setState(patch) {
    this.state = { ...this.state, ...patch };
    this._emit();
  }

  subscribe(listener) {
    this.listeners.add(listener);
    return () => this.listeners.delete(listener);
  }

  _emit() {
    this.listeners.forEach((listener) => {
      try {
        listener(this.state);
      } catch (err) {
        // A broken renderer must never break the notification pipeline.
        console.error('NotificationStore listener error:', err);
      }
    });
  }
}

export const notificationStore = new NotificationStore();
