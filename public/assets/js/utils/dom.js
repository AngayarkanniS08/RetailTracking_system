/**
 * dom.js — DOM query and manipulation helpers
 */

/**
 * Shorthand for document.getElementById.
 * @template {HTMLElement} T
 * @param {string} id
 * @returns {T|null}
 */
export const byId = (id) => document.getElementById(id);

/**
 * Shorthand for querySelector on an optional root.
 * @template {Element} T
 * @param {string} selector
 * @param {Element|Document} [root=document]
 * @returns {T|null}
 */
export const qs = (selector, root = document) =>
  root.querySelector(selector);

/**
 * Shorthand for querySelectorAll, returns a real array.
 * @template {Element} T
 * @param {string} selector
 * @param {Element|Document} [root=document]
 * @returns {T[]}
 */
export const qsa = (selector, root = document) =>
  Array.from(root.querySelectorAll(selector));

/**
 * Set innerHTML of an element safely.
 * @param {HTMLElement} el
 * @param {string} html
 */
export const setHTML = (el, html) => {
  if (el) el.innerHTML = html;
};

/**
 * Set text content of an element.
 * @param {HTMLElement} el
 * @param {string} text
 */
export const setText = (el, text) => {
  if (el) el.textContent = text;
};

/**
 * Show an element (removes 'd-none' class, sets display if needed).
 * @param {HTMLElement} el
 */
export const show = (el) => {
  if (!el) return;
  el.classList.remove('d-none');
  if (el.style.display === 'none') el.style.display = '';
};

/**
 * Hide an element.
 * @param {HTMLElement} el
 */
export const hide = (el) => {
  if (!el) return;
  el.classList.add('d-none');
};

/**
 * Debounce a function.
 * @param {Function} fn
 * @param {number} [ms=300]
 * @returns {Function}
 */
export function debounce(fn, ms = 300) {
  let timer;
  return (...args) => {
    clearTimeout(timer);
    timer = setTimeout(() => fn(...args), ms);
  };
}

/**
 * Scroll an element into view smoothly.
 * @param {HTMLElement} el
 */
export const scrollIntoView = (el) =>
  el?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
