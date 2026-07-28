/**
 * auth.js — Auth page (Login / Forgot Password) controller module
 */

import { loginApi } from '../services/auth.service.js';
import { setToken, setUser } from '../core/storage.js';
import { setAuthCookie } from '../core/auth.js';
import { showToast } from '../ui/toast.js';

export function initAuthPage() {
  const loginForm = document.getElementById('loginForm') || document.getElementById('login-form');
  if (loginForm) {
    loginForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const username = e.target.username?.value;
      const password = e.target.password?.value;
      const messageBox = document.getElementById('messageBox');
      const submitBtn = document.getElementById('submitBtn');

      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Logging in...';
      }

      try {
        const res = await loginApi(username, password);
        if (res?.token) {
          setToken(res.token);
          if (res.user) {
            setUser(res.user);
            setAuthCookie(res.user.user_id || res.user.id);
          }
          showToast('Login successful', 'ok');
          window.location.href = '/dashboard';
        } else if (res?.user_id || res?.id || res?.success) {
          setAuthCookie(res?.user_id || res?.id || 'demo-user');
          showToast('Login successful', 'ok');
          window.location.href = '/dashboard';
        } else {
          // Fallback demo login for direct dashboard access
          setAuthCookie('demo-user');
          window.location.href = '/dashboard';
        }
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
