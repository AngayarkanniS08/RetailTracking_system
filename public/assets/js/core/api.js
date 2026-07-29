/**
 * api.js — HTTP client with JWT auth and automatic token refresh
 * Extracted and modernised from utils.js
 */

import { API_BASE } from './config.js';
import { getToken, setToken, setUser } from './storage.js';
import { logoutUser, setAuthCookie } from './auth.js';
import { logger } from './logger.js';

/** Singleton promise to prevent parallel refresh races */
let _refreshPromise = null;

/**
 * Attempt to refresh the JWT token.
 * @returns {Promise<string|null>} New token or null on failure
 */
async function refreshToken() {
  if (_refreshPromise) return _refreshPromise;

  const oldToken = getToken();
  if (!oldToken) return null;

  _refreshPromise = (async () => {
    try {
      const res = await fetch(`${API_BASE}/api/auth/refresh`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token: oldToken }),
      });

      if (!res.ok) return null;

      const data = await res.json();
      if (!data.token) return null;

      setToken(data.token);

      if (data.user?.user_id) {
        setAuthCookie(data.user.user_id);
        setUser(data.user);
      }

      return data.token;
    } catch (err) {
      logger.error('api:refresh', err);
      return null;
    }
  })();

  const result = await _refreshPromise;
  _refreshPromise = null;
  return result;
}

/**
 * Make an authenticated API request with automatic token refresh on 401.
 *
 * @param {string} path   - API path (e.g. '/api/dashboard/stats') or full URL
 * @param {RequestInit} [options] - fetch options
 * @returns {Promise<any>} Parsed JSON response body
 * @throws {Error} On network errors, non-OK responses, or invalid JSON
 */
export async function apiRequest(path, options = {}) {
  const url = path.startsWith('http') ? path : `${API_BASE}${path}`;

  /**
   * @param {boolean} retryAllowed
   * @returns {Promise<Response>}
   */
  const doFetch = async (retryAllowed) => {
    const token = getToken();
    const headers = {
      'Content-Type': 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...options.headers,
    };

    let response;
    try {
      response = await fetch(url, { ...options, credentials: 'include', headers });
    } catch (networkErr) {
      logger.error('api:network', url, networkErr);
      throw networkErr;
    }

    if (response.status === 401) {
      if (!retryAllowed) {
        logoutUser();
        throw new Error('Session expired');
      }

      const newToken = await refreshToken();
      if (!newToken) {
        logoutUser();
        throw new Error('Session expired');
      }

      return doFetch(false);
    }

    return response;
  };

  const response = await doFetch(true);
  const text = await response.text();

  if (!text?.trim()) {
    if (!response.ok) throw new Error('API request failed (empty response)');
    return null;
  }

  let data;
  try {
    data = JSON.parse(text);
  } catch {
    logger.error('api:json', path, `(status ${response.status})`, text.slice(0, 300));
    throw new Error('Invalid JSON response from API');
  }

  if (!response.ok) {
    throw new Error(data?.error ?? 'API request failed');
  }

  return data;
}

window.apiRequest = apiRequest;
