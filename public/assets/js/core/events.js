/**
 * events.js — Lightweight typed event bus
 * Used to decouple modules (e.g. billing notifies cart to refresh)
 */

/** @type {Map<string, Set<Function>>} */
const _listeners = new Map();

/**
 * Subscribe to an event.
 * @param {string} event
 * @param {Function} handler
 * @returns {() => void} Unsubscribe function
 */
export function on(event, handler) {
  if (!_listeners.has(event)) _listeners.set(event, new Set());
  _listeners.get(event).add(handler);
  return () => off(event, handler);
}

/**
 * Unsubscribe from an event.
 * @param {string} event
 * @param {Function} handler
 */
export function off(event, handler) {
  _listeners.get(event)?.delete(handler);
}

/**
 * Emit an event to all subscribers.
 * @param {string} event
 * @param {any} [payload]
 */
export function emit(event, payload) {
  _listeners.get(event)?.forEach((handler) => {
    try { handler(payload); } catch (err) {
      console.error(`[events] Error in handler for "${event}"`, err);
    }
  });
}

/** Named application events — use these constants for autocomplete safety */
export const Events = {
  CART_UPDATED:       'cart:updated',
  STOCK_ALERT:        'stock:alert',
  THEME_CHANGED:      'theme:changed',
  SECTION_CHANGED:    'router:section-changed',
  TOAST:              'ui:toast',
};
