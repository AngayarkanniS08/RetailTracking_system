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
import { showToast, notify } from './ui/toast.js';
import { initAuthPage } from './pages/auth.js';
import { initNotifications } from './notifications/NotificationController.js';

// Page-specific modules
import { initDashboardPage } from './pages/dashboard.js';
import { initBillingPage } from './pages/billing.js';
import { initInventoryPage } from './pages/inventory/InventoryController.js';
import { initSystemHealthPage } from './pages/system-health.js';
import { initVendorsPage } from './pages/vendors.js';
import { initCustomerCredit } from './pages/customers.js';
import { initDayToDaySelling } from './pages/daily_sales.js';
import { initDailyRegisterDetail } from './pages/daily_register_detail.js';
import { initVendorHistorySummary } from './pages/vendor_history.js';
import { initVendorHistoryDetail } from './pages/vendor_history_detail.js';

import { API_BASE } from './core/config.js';
import { apiRequest } from './core/api.js';
import { getToken } from './core/storage.js';

// Expose core global helpers for HTML inline attribute handlers (backward compatibility)
window.apiRequest = apiRequest;
window.getToken = getToken;
window.fetchWithAuth = async function (url, options = {}) {
  const token = getToken();
  const fullUrl = url.startsWith('http') ? url : `${API_BASE}${url}`;
  const headers = {
    'Content-Type': 'application/json',
    ...(token ? { Authorization: `Bearer ${token}` } : {}),
    ...(options.headers || {}),
  };
  return fetch(fullUrl, { ...options, headers, credentials: 'include' });
};
window.logoutUser = logoutUser;
window.openModal = openModal;
window.closeModal = closeModal;
window.switchTab = switchTab;
window.showToast = showToast;
window.notify = notify;
window.setTheme = setAppTheme;

// Delegated modal triggers (no inline onclick needed)
document.addEventListener('click', (e) => {
  const modalBtn = e.target.closest('[data-modal]');
  if (modalBtn) { e.preventDefault(); openModal(modalBtn.dataset.modal); return; }
  const closeBtn = e.target.closest('[data-modal-close]');
  if (closeBtn) {
    const overlay = closeBtn.closest('.modal-overlay');
    if (overlay?.id) closeModal(overlay.id);
  }
});

/**
 * User Profile Dropdown Menu Handler
 */
function initUserMenuDropdown() {
  const trigger = document.getElementById('userAvatarTrigger') || document.querySelector('.avatar');
  const dropdown = document.getElementById('userDropdownMenu') || document.querySelector('.user-dropdown-menu');

  if (!trigger || !dropdown) return;

  trigger.addEventListener('click', (e) => {
    e.stopPropagation();
    const isShown = dropdown.classList.contains('show');
    dropdown.classList.toggle('show', !isShown);
    trigger.setAttribute('aria-expanded', isShown ? 'false' : 'true');
  });

  dropdown.addEventListener('click', (e) => {
    if (e.target.closest('.user-dropdown-item')) {
      dropdown.classList.remove('show');
      trigger.setAttribute('aria-expanded', 'false');
    } else {
      e.stopPropagation();
    }
  });

  document.addEventListener('click', () => {
    if (dropdown.classList.contains('show')) {
      dropdown.classList.remove('show');
      trigger.setAttribute('aria-expanded', 'false');
    }
  });
}

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

  // 6. Initialize the Notification Platform (badge + bell + polling)
  initNotifications();

  // 6. Bind user menu profile dropdown
  initUserMenuDropdown();

  // 7. Bind theme toggle buttons
  document.querySelectorAll('.theme-btn[data-theme], .topbar-theme-btn[data-theme]').forEach((btn) => {
    btn.addEventListener('click', () => {
      if (btn.dataset.theme === 'toggle') {
        toggleTheme();
      } else {
        setAppTheme(btn.dataset.theme);
        document.querySelectorAll('.topbar-theme-btn').forEach((b) => {
          b.classList.toggle('active', b.dataset.theme === btn.dataset.theme);
        });
      }
    });
  });

  // 8. Initialize active page controller module
  const activeSection = document.querySelector('.view-section.active');
  if (activeSection) {
    const id = activeSection.id;
    if (id === 'dashboard') initDashboardPage();
    else if (id === 'inventory') initInventoryPage();
    else if (id === 'billing_pos') initBillingPage();
    else if (id === 'system_health') initSystemHealthPage();
    else if (id === 'vendor_list' || id === 'vendor_history' || id === 'vendorhistory') initVendorsPage();
    else if (id === 'credit_kadan' || id === 'customers') initCustomerCredit();
    else if (id === 'day_to_day_selling' || id === 'daily_sales') {
      initDayToDaySelling();
    } else if (id === 'daily_register_detail_section' || id === 'daily_register_detail') {
      initDailyRegisterDetail();
    } else if (id === 'vendor_history_summary') {
      initVendorHistorySummary();
    } else if (id === 'vendor_history_detail') {
      initVendorHistoryDetail();
    }
  }
}

// Guard: only run when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot);
} else {
  boot();
}
