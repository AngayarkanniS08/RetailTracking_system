/**
 * storage.js — Typed wrappers for localStorage
 * Centralises all storage keys and gracefully handles quota errors
 */

import { STORAGE_KEYS } from './config.js';

/**
 * @param {string} key
 * @returns {string|null}
 */
export function getItem(key) {
  try {
    return localStorage.getItem(key);
  } catch {
    return null;
  }
}

/**
 * @param {string} key
 * @param {string} value
 */
export function setItem(key, value) {
  try {
    localStorage.setItem(key, value);
  } catch {
    // quota exceeded — fail silently
  }
}

/** @param {string} key */
export function removeItem(key) {
  try {
    localStorage.removeItem(key);
  } catch { /* no-op */ }
}

// ── Auth-specific helpers ──────────────────────────────────────

/** @returns {string|null} */
export const getToken = () => getItem(STORAGE_KEYS.AUTH_TOKEN);

/** @param {string} token */
export const setToken = (t) => setItem(STORAGE_KEYS.AUTH_TOKEN, t);

export const clearToken = () => removeItem(STORAGE_KEYS.AUTH_TOKEN);

/** @returns {object|null} */
export function getUser() {
  const raw = getItem(STORAGE_KEYS.AUTH_USER);
  if (!raw) return null;
  try { return JSON.parse(raw); } catch { return null; }
}

/** @param {object} user */
export const setUser = (u) => setItem(STORAGE_KEYS.AUTH_USER, JSON.stringify(u));

export const clearUser = () => removeItem(STORAGE_KEYS.AUTH_USER);

/** @returns {'dark'|'light'} */
export const getTheme = () => getItem(STORAGE_KEYS.THEME) ?? 'dark';

/** @param {'dark'|'light'} mode */
export const setTheme = (mode) => setItem(STORAGE_KEYS.THEME, mode);
