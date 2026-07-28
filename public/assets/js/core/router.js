/**
 * router.js — View section routing helper for MPA pages
 */

import { logger } from './logger.js';

/** Currently active section ID */
let _activeSectionId = null;

/**
 * Switch the visible view section or navigate in MPA environment.
 * @param {string} sectionId - The `id` of the section or route path
 */
export function switchTab(sectionId) {
  if (!sectionId) return;

  const target = document.getElementById(sectionId);
  if (target) {
    document.querySelectorAll('.view-section').forEach((el) => {
      el.classList.remove('active');
    });
    target.classList.add('active');
    _activeSectionId = sectionId;
    logger.debug('router', `Active section: ${sectionId}`);
  }
}

/** @returns {string|null} The active section ID */
export const getActiveSection = () => _activeSectionId;

/**
 * Initialise router for current MPA view.
 */
export function initRouter() {
  const activeSection = document.querySelector('.view-section.active');
  if (activeSection) {
    _activeSectionId = activeSection.id;
  }
}
