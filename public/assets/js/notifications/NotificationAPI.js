/**
 * NotificationAPI.js — single client contract for the Notification Platform.
 *
 * The frontend never computes counts or read state; it only transports
 * whatever the backend returns. Endpoints:
 *   GET  /api/notifications          → { summary, alerts }
 *   POST /api/notifications/read     → { success, marked }  body: { keys: [...] }
 *   POST /api/notifications/read-all → { success, marked }
 */
import { apiRequest } from '../core/api.js';

export const NotificationAPI = {
  async fetch() {
    const data = await apiRequest('/api/notifications');
    return {
      summary: (data && typeof data === 'object' && data.summary) || {},
      alerts: Array.isArray(data?.alerts) ? data.alerts : [],
    };
  },

  async markRead(keys = []) {
    return apiRequest('/api/notifications/read', {
      method: 'POST',
      body: JSON.stringify({ keys }),
    });
  },

  async markAllRead() {
    return apiRequest('/api/notifications/read-all', { method: 'POST' });
  },
};
