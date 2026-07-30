/**
 * auth.js — Authentication state management
 *
 * Architecture:
 *   - Server issues HttpOnly cookies (auth_token, auth_uid) via Set-Cookie on login/refresh.
 *   - Frontend stores JWT in localStorage for Authorization: Bearer <token> header on API calls.
 *   - On logout: hit /logout to clear server-side HttpOnly cookies, then clear localStorage.
 *   - The frontend NEVER writes auth_token or auth_uid cookies directly.
 */

import { clearToken, clearUser } from './storage.js';

/**
 * Clear all client auth state and redirect to login.
 * Server's /logout route is responsible for clearing HttpOnly cookies.
 * Called on 401 when token refresh fails.
 */
export async function logoutUser() {
  // Clear localStorage
  clearToken();
  clearUser();

  // Hit server logout route to clear HttpOnly cookies via Set-Cookie: max-age=0
  try {
    await fetch('/logout', { credentials: 'include' });
  } catch {
    // Ignore network errors — proceed to redirect regardless
  }

  window.location.href = '/login';
}
