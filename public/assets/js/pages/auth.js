/**
 * auth.js — Auth page (Login) controller module
 *
 * Auth flow (permanent architecture):
 *   1. POST /api/auth/login  → Server validates credentials
 *   2. Server sets HttpOnly cookies (auth_token, auth_uid) via Set-Cookie header
 *   3. Server returns JSON { success, token, user }
 *   4. Frontend stores token in localStorage (for Authorization header on API calls)
 *   5. Frontend redirects to /dashboard
 *
 *   The frontend NEVER writes auth cookies. The server owns cookie lifecycle.
 */

import { loginApi } from '../services/auth.service.js';
import { setToken, setUser } from '../core/storage.js';
import { showToast } from '../ui/toast.js';

export function initAuthPage() {
  const loginForm = document.getElementById('loginForm') || document.getElementById('login-form');
  if (loginForm) {
    loginForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const username = e.target.username?.value?.trim();
      const password = e.target.password?.value;
      const messageBox = document.getElementById('messageBox');
      const submitBtn = document.getElementById('submitBtn');

      if (!username || !password) {
        if (messageBox) {
          messageBox.style.display = 'block';
          messageBox.className = 'auth-message error';
          messageBox.textContent = 'Username and password are required.';
        }
        return;
      }

      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Logging in...';
      }

      try {
        const res = await loginApi(username, password);

        if (!res?.token) {
          throw new Error(res?.error || 'Login failed. Please check your credentials.');
        }

        // Store token in localStorage (used as Authorization: Bearer <token> header for all API calls)
        setToken(res.token);
        if (res.user) setUser(res.user);

        // HttpOnly auth_token and auth_uid cookies are already set by the server via Set-Cookie.
        // No frontend cookie manipulation required.

        showToast('Login successful', 'ok');
        window.location.href = '/dashboard';

      } catch (err) {
        if (messageBox) {
          messageBox.style.display = 'block';
          messageBox.className = 'auth-message error';
          messageBox.textContent = err.message || 'Login failed';
        }
        showToast(err.message || 'Login failed', 'danger');
      } finally {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = 'Login to System';
        }
      }
    });
  }
}
