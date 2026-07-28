/**
 * validate.js — Form validation utilities
 */

/**
 * Check if a value is non-empty (after trimming whitespace).
 * @param {any} value
 * @returns {boolean}
 */
export const isNotEmpty = (value) =>
  value !== null && value !== undefined && String(value).trim() !== '';

/**
 * Check if a string is a valid email address.
 * @param {string} email
 * @returns {boolean}
 */
export const isValidEmail = (email) =>
  /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);

/**
 * Check if a value is a positive number.
 * @param {any} value
 * @returns {boolean}
 */
export const isPositiveNumber = (value) =>
  !isNaN(value) && Number(value) > 0;

/**
 * Check if a value is a non-negative number (≥0).
 * @param {any} value
 * @returns {boolean}
 */
export const isNonNegative = (value) =>
  !isNaN(value) && Number(value) >= 0;

/**
 * Clamp a number between min and max.
 * @param {number} value
 * @param {number} min
 * @param {number} max
 * @returns {number}
 */
export const clamp = (value, min, max) => Math.min(Math.max(Number(value), min), max);

/**
 * Validate a form element and display inline error.
 * Returns true if valid.
 *
 * @param {HTMLInputElement|HTMLSelectElement} input
 * @param {string} message
 * @returns {boolean}
 */
export function validateField(input, message) {
  const valid = input.checkValidity();
  const errorEl = input.closest('.input-group')?.querySelector('.error-text');
  if (errorEl) errorEl.textContent = valid ? '' : message;
  input.classList.toggle('is-error', !valid);
  return valid;
}
