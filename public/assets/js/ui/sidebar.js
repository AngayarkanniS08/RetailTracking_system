/**
 * sidebar.js — Sidebar navigation module
 */

import { switchTab } from '../core/router.js';

/**
 * Initialise sidebar click delegation.
 */
export function initSidebar() {
  const sidebar = document.querySelector('aside.sidebar, #sidebar, .sidebar');
  if (!sidebar) return;

  sidebar.addEventListener('click', (e) => {
    const navItem = e.target.closest('.nav-item[data-section]');
    if (!navItem) return;
    const section = navItem.getAttribute('data-section') || navItem.dataset.section;
    if (section) {
      if (typeof window.switchTab === 'function') {
        window.switchTab(section);
      } else {
        switchTab(section);
      }
    }
  });
}
