/**
 * main.js — Application entry point
 * Bootstraps: theme, router, sidebar, modals, and page-specific init
 *
 * Usage in footer.php:
 *   <script type="module" src="public/assets/js/main.js"></script>
 */

import { initTheme, toggleTheme, setAppTheme } from './ui/theme.js';
import { initRouter, switchTab } from './core/router.js';
import { initSidebar } from './ui/sidebar.js';
import { initModals, openModal, closeModal } from './ui/modal.js';
import { logoutUser } from './core/auth.js';
import { showToast } from './ui/toast.js';

// Expose core global helpers for HTML inline attribute handlers (backward compatibility)
window.logoutUser = logoutUser;
window.openModal = openModal;
window.closeModal = closeModal;
window.switchTab = switchTab;
window.showToast = showToast;
window.setTheme = setAppTheme;

/**
 * Bootstrap the application shell.
 * Called once DOM is ready.
 */
async function boot() {
  // 1. Apply persisted theme before first paint
  initTheme();

  // 2. Bind sidebar navigation
  initSidebar();

  // 3. Bind modals
  initModals();

  // 4. Route to the active section
  initRouter();

  // 5. Bind theme toggle buttons
  document.querySelectorAll('.theme-btn[data-theme]').forEach((btn) => {
    btn.addEventListener('click', () => {
      if (btn.dataset.theme === 'toggle') {
        toggleTheme();
      } else {
        setAppTheme(btn.dataset.theme);
      }
    });
  });
}

// Guard: only run when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot);
} else {
  boot();
}
