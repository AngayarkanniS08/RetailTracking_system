/**
 * main.js — Application entry point
 * Bootstraps: theme, router, sidebar, modals, auth handlers, and page-specific init
 *
 * Usage in footer.php:
 *   <script type="module" src="/public/assets/js/main.js"></script>
 */

import { initTheme, toggleTheme, setAppTheme } from './ui/theme.js';
import { initRouter, switchTab } from './core/router.js';
import { initSidebar } from './ui/sidebar.js';
import { initModals, openModal, closeModal } from './ui/modal.js';
import { logoutUser } from './core/auth.js';
import { showToast } from './ui/toast.js';
import { initAuthPage } from './pages/auth.js';

// Page-specific modules
import { initDashboardPage } from './pages/dashboard.js';
import { initBillingPage } from './pages/billing.js';
import { initSystemHealthPage } from './pages/system-health.js';
import { initVendorsPage } from './pages/vendors.js';

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

  // 5. Initialize auth page listeners (Login/Register)
  initAuthPage();

  // 6. Bind theme toggle buttons
  document.querySelectorAll('.theme-btn[data-theme]').forEach((btn) => {
    btn.addEventListener('click', () => {
      if (btn.dataset.theme === 'toggle') {
        toggleTheme();
      } else {
        setAppTheme(btn.dataset.theme);
      }
    });
  });

  // 7. Initialize active page controller module
  const activeSection = document.querySelector('.view-section.active');
  if (activeSection) {
    const id = activeSection.id;
    if (id === 'dashboard') initDashboardPage();
    else if (id === 'billing_pos') initBillingPage();
    else if (id === 'system_health') initSystemHealthPage();
    else if (id === 'vendor_list' || id === 'vendor_history') initVendorsPage();
  }
}

// Guard: only run when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot);
} else {
  boot();
}
