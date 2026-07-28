/**
 * config.js — Application-wide constants and environment config
 * Pure: no side effects, no DOM access
 */

/** Derive API base URL from the current browser location */
export const API_BASE = `${window.location.protocol}//${window.location.hostname}:8081`;

/** Application name */
export const APP_NAME = 'Pudheera Retail Tracking System';

/** API request timeout in ms */
export const REQUEST_TIMEOUT_MS = 30_000;

/** Default pagination page size */
export const DEFAULT_PAGE_SIZE = 50;

/** Local storage keys */
export const STORAGE_KEYS = {
  AUTH_TOKEN: 'auth_token',
  AUTH_USER:  'auth_user',
  THEME:      'theme',
};

/** Cookie names */
export const COOKIE_NAMES = {
  AUTH_UID: 'auth_uid',
};
