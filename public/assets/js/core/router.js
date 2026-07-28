/**
 * router.js — View section routing (tab / section switching)
 * Extracted from Sidebar.js global functions
 */

import { logger } from './logger.js';

/** Currently active section ID */
let _activeSectionId = null;

/**
 * Switch the visible view section.
 * Hides all `.view-section` elements, shows the one matching `sectionId`.
 *
 * @param {string} sectionId - The `id` of the section to show
 */
export function switchTab(sectionId) {
  if (_activeSectionId === sectionId) return;

  document.querySelectorAll('.view-section').forEach((el) => {
    el.classList.remove('active');
  });

  const target = document.getElementById(sectionId);
  if (!target) {
    logger.warn('router', `Section not found: ${sectionId}`);
    return;
  }

  target.classList.add('active');
  _activeSectionId = sectionId;

  // Sync nav items
  document.querySelectorAll('.nav-item[data-section]').forEach((nav) => {
    nav.classList.toggle('active', nav.dataset.section === sectionId);
  });

  logger.debug('router', `Navigated to: ${sectionId}`);
}

/** @returns {string|null} The active section ID */
export const getActiveSection = () => _activeSectionId;

/**
 * Navigate to the default (first) section on page load.
 * @param {string} [defaultSection='dashboard'] - Section to show on load
 */
export function initRouter(defaultSection = 'dashboard') {
  // Honour hash navigation
  const hash = window.location.hash.slice(1);
  switchTab(hash || defaultSection);
}
