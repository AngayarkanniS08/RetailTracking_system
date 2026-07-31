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
  // 1. Clear localStorage
  clearToken();
  clearUser();

  // 2. Clear client document cookies
  document.cookie = 'auth_token=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT; SameSite=Lax';
  document.cookie = 'auth_uid=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT; SameSite=Lax';

  // 3. Hit server logout route to clear HttpOnly cookies via Set-Cookie: max-age=0
  try {
    await fetch('/logout', { credentials: 'include' });
  } catch {
    // Ignore network errors — proceed to redirect regardless
  }

  // 4. Single deterministic redirect ONLY if not already on a guest route
  const guestRoutes = ['/login', '/register', '/forgot-password', '/reset-password'];
  if (!guestRoutes.includes(window.location.pathname)) {
    window.location.href = '/login';
  }
}
