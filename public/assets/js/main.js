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

// Page-specific modules
import { initDashboardPage } from './pages/dashboard.js';
import { initBillingPage } from './pages/billing.js';
import { initInventoryPage } from './pages/inventory.js';
import { initSystemHealthPage } from './pages/system-health.js';
import { initVendorsPage } from './pages/vendors.js';
import { initCustomerCredit } from './pages/customers.js';

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

/**
 * 🔔 Active Alerts Modal Handler (Notification System)
 */
window.openActiveAlertsModal = async function () {
  if (typeof window.openModal === 'function') {
    window.openModal('activeAlertsModal');
  }

  const container = document.getElementById('activeAlertsModalList');
  if (!container) return;

  container.innerHTML = `
    <div style="text-align: center; padding: 24px; color: var(--muted);">
      <div style="font-size: 0.85rem; font-weight: 500;">Checking active stock alerts...</div>
    </div>
  `;

  try {
    const data = await apiRequest('/api/dashboard/stock-intel');

    const lowStock = data.low_selling || [];
    let alertCount = 0;
    let alertsHtml = '';

    if (Array.isArray(lowStock) && lowStock.length > 0) {
      lowStock.forEach((item) => {
        alertCount++;
        const name = item.product_name || item.name || 'Product';
        const qty = item.total_quantity ?? item.stock ?? 0;
        const unit = item.unit || 'pcs';

        alertsHtml += `
          <div class="alert-card" style="padding: 12px 16px; border-radius: 10px; background: var(--surface-container-low); border: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; gap: 12px;">
            <div style="display: flex; align-items: center; gap: 12px;">
              <div style="width: 36px; height: 36px; border-radius: 50%; background: rgba(220, 38, 38, 0.1); color: var(--danger); display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 1.1rem;">⚠️</div>
              <div>
                <div style="font-weight: 600; font-size: 0.9rem; color: var(--text-strong);">${name}</div>
                <div style="font-size: 0.78rem; color: var(--muted);">Current Stock: <strong style="color: var(--danger);">${qty} ${unit}</strong></div>
              </div>
            </div>
            <a href="/products" class="btn btn-outline btn-sm" onclick="closeModal('activeAlertsModal')" style="padding: 4px 10px; font-size: 0.75rem; border-radius: 6px;">Reorder</a>
          </div>
        `;
      });
    }

    if (alertCount === 0) {
      container.innerHTML = `
        <div style="text-align: center; padding: 32px 16px;">
          <div style="font-size: 2rem; margin-bottom: 8px;">✅</div>
          <div style="font-weight: 600; font-size: 0.95rem; color: var(--text-strong);">All Inventory Nominal</div>
          <div style="font-size: 0.8rem; color: var(--muted); margin-top: 4px;">No low stock alerts or reorder warnings.</div>
        </div>
      `;
    } else {
      container.innerHTML = alertsHtml;
    }

    updateTopbarAlertBadge(alertCount);
  } catch (err) {
    console.error('Failed to load active alerts:', err);
    container.innerHTML = `
      <div style="text-align: center; padding: 24px; color: var(--danger); font-size: 0.85rem;">
        Failed to load active alerts. Please try again.
      </div>
    `;
  }
};

function updateTopbarAlertBadge(count) {
  const badge = document.getElementById('topbarAlertBadge');
  if (!badge) return;
  if (count > 0) {
    badge.innerHTML = `${count}<span class="visually-hidden"> unread notifications</span>`;
    badge.style.display = 'flex';
  } else {
    badge.innerHTML = `0<span class="visually-hidden"> unread notifications</span>`;
    badge.style.display = 'none';
  }
}

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
    else if (id === 'vendor_list' || id === 'vendor_history') initVendorsPage();
    else if (id === 'credit_kadan' || id === 'customers') initCustomerCredit();
    else if (id === 'day_to_day_selling' || id === 'daily_sales') {
      if (typeof window.initDayToDaySelling === 'function') window.initDayToDaySelling();
    }
  }
}

// Guard: only run when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot);
} else {
  boot();
}
