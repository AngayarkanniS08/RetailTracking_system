/**
 * theme.js — Dark/light theme management
 * Persists preference to localStorage and syncs HTML attribute
 */

import { getTheme, setTheme } from '../core/storage.js';
import { emit, Events } from '../core/events.js';

/** Apply theme to the HTML root element */
function applyTheme(mode) {
  document.documentElement.setAttribute('data-theme-mode', mode);
}

/**
 * Toggle between dark and light themes.
 * @returns {'dark'|'light'} The new theme
 */
export function toggleTheme() {
  const current = getTheme();
  const next = current === 'dark' ? 'light' : 'dark';
  setTheme(next);
  applyTheme(next);
  syncButtons(next);
  emit(Events.THEME_CHANGED, next);
  return next;
}

/**
 * Explicitly set a theme.
 * @param {'dark'|'light'} mode
 */
export function setAppTheme(mode) {
  setTheme(mode);
  applyTheme(mode);
  syncButtons(mode);
  emit(Events.THEME_CHANGED, mode);
}

/** Sync .theme-btn active states */
function syncButtons(mode) {
  document.querySelectorAll('.theme-btn[data-theme]').forEach((btn) => {
    btn.classList.toggle('active', btn.dataset.theme === mode);
  });
}

/**
 * Initialise theme on page load.
 * Reads persisted preference, falls back to system prefers-color-scheme.
 */
export function initTheme() {
  const stored = getTheme();
  const systemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
  const mode = stored || (systemDark ? 'dark' : 'light');
  setTheme(mode);
  applyTheme(mode);
  syncButtons(mode);
}
