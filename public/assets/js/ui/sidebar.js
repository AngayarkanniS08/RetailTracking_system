/**
 * sidebar.js — Sidebar navigation module
 */

import { switchTab } from '../core/router.js';

/**
 * Initialise sidebar click delegation.
 * @param {string} [containerId='sidebar'] - ID of the sidebar element
 */
export function initSidebar(containerId = 'sidebar') {
  const sidebar = document.getElementById(containerId);
  if (!sidebar) return;

  sidebar.addEventListener('click', (e) => {
    const navItem = e.target.closest('.nav-item[data-section]');
    if (!navItem) return;
    switchTab(navItem.dataset.section);
  });
}
