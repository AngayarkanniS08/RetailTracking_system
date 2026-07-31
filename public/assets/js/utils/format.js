/**
 * format.js — Number and date formatting utilities
 */

const INR_FORMATTER = new Intl.NumberFormat('en-IN', {
  style: 'currency',
  currency: 'INR',
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
});

const DATE_FORMATTER = new Intl.DateTimeFormat('en-IN', {
  year: 'numeric',
  month: 'short',
  day: '2-digit',
});

const DATETIME_FORMATTER = new Intl.DateTimeFormat('en-IN', {
  year: 'numeric',
  month: 'short',
  day: '2-digit',
  hour: '2-digit',
  minute: '2-digit',
  hour12: true,
});

/**
 * Format a number as Indian Rupee (₹).
 * @param {number|string} amount
 * @returns {string}
 */
export function formatCurrency(amount) {
  const n = parseFloat(amount);
  return isNaN(n) ? '₹0.00' : INR_FORMATTER.format(n);
}

/**
 * Normalize Postgres-style timestamps ("YYYY-MM-DD HH:MM:SS.micro+00")
 * into a browser-parseable ISO string ("YYYY-MM-DDTHH:MM:SS.micro+00").
 */
function toDate(value) {
  if (value instanceof Date) return value;
  if (typeof value === 'string') {
    const normalized = value.trim().replace(' ', 'T');
    return new Date(normalized);
  }
  return new Date(value);
}

/**
 * Format a date string or Date object to "DD Mon YYYY".
 * @param {string|Date} value
 * @returns {string}
 */
export function formatDate(value) {
  const d = toDate(value);
  return isNaN(d.getTime()) ? 'Invalid date' : DATE_FORMATTER.format(d);
}

/**
 * Format a date to "DD Mon YYYY, HH:MM AM/PM".
 * @param {string|Date} value
 * @returns {string}
 */
export function formatDateTime(value) {
  const d = toDate(value);
  return isNaN(d.getTime()) ? 'Invalid date' : DATETIME_FORMATTER.format(d);
}

/**
 * Truncate a string to a maximum length.
 * @param {string} str
 * @param {number} [max=30]
 * @returns {string}
 */
export function truncate(str, max = 30) {
  if (!str) return '';
  return str.length > max ? `${str.slice(0, max)}…` : str;
}

/**
 * Capitalise the first letter of each word.
 * @param {string} str
 * @returns {string}
 */
export function titleCase(str) {
  return str?.replace(/\w\S*/g, (w) => w[0].toUpperCase() + w.slice(1).toLowerCase()) ?? '';
}

/**
 * Safely escape HTML special characters to prevent XSS vulnerabilities.
 * @param {string|number|null|undefined} str
 * @returns {string}
 */
export function escapeHtml(str) {
  if (str === null || str === undefined) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}
