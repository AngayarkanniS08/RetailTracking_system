/**
 * logger.js — Structured logging wrapper
 * Suppresses all logs in production (when hostname is not localhost/dev)
 */

const isDev = ['localhost', '127.0.0.1'].includes(window.location.hostname)
  || window.location.hostname.startsWith('192.168.');

export const logger = {
  /** @param {string} tag  @param {...any} args */
  info(tag, ...args) {
    if (isDev) console.info(`[${tag}]`, ...args);
  },

  /** @param {string} tag  @param {...any} args */
  warn(tag, ...args) {
    if (isDev) console.warn(`[${tag}]`, ...args);
  },

  /** @param {string} tag  @param {...any} args */
  error(tag, ...args) {
    // errors always log — they matter in production too
    console.error(`[${tag}]`, ...args);
  },

  /** @param {string} tag  @param {...any} args */
  debug(tag, ...args) {
    if (isDev) console.debug(`[${tag}]`, ...args);
  },
};
