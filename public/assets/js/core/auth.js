/**
 * auth.js — Authentication state management
 * Handles logout, cookie clearing, and session expiry redirect
 */

import { COOKIE_NAMES } from './config.js';
import { clearToken, clearUser } from './storage.js';

/**
 * Clear all auth state and redirect to login.
 * Called on 401 when token refresh fails.
 */
export function logoutUser() {
  // Clear cookies
  document.cookie = `${COOKIE_NAMES.AUTH_UID}=; path=/; max-age=0; SameSite=Lax`;
  document.cookie = `auth_token=; path=/; max-age=0; SameSite=Lax`;

  // Clear storage
  clearToken();
  clearUser();

  // Redirect
  window.location.href = '/login';
}

/**
 * Set auth cookie after login / token refresh
 * @param {string|number} userId
 * @param {string} [token]
 */
export function setAuthCookie(userId, token = null) {
  document.cookie = `${COOKIE_NAMES.AUTH_UID}=${userId}; path=/; max-age=86400; SameSite=Lax`;
  if (token) {
    document.cookie = `auth_token=${token}; path=/; max-age=86400; SameSite=Lax`;
  }
}
