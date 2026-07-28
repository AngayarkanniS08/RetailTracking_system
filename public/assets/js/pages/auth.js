/**
 * auth.js — Auth page (Login / Forgot Password) controller module
 */

import { loginApi, forgotPasswordApi } from '../services/auth.service.js';
import { setToken, setUser } from '../core/storage.js';
import { setAuthCookie } from '../core/auth.js';
import { showToast } from '../ui/toast.js';

export function initAuthPage() {
  const loginForm = document.getElementById('login-form');
  if (loginForm) {
    loginForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const username = e.target.username?.value;
      const password = e.target.password?.value;

      try {
        const res = await loginApi(username, password);
        if (res?.token) {
          setToken(res.token);
          if (res.user) {
            setUser(res.user);
            setAuthCookie(res.user.user_id);
          }
          showToast('Login successful', 'ok');
          window.location.href = '/dashboard';
        }
      } catch (err) {
        showToast(err.message || 'Login failed', 'danger');
      }
    });
  }
}
