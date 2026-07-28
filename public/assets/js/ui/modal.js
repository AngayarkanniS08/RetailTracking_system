/**
 * modal.js — Modal open/close lifecycle management
 */

/**
 * Open a modal overlay.
 * @param {string|HTMLElement} modalIdOrEl
 */
export function openModal(modalIdOrEl) {
  const el = typeof modalIdOrEl === 'string'
    ? document.getElementById(modalIdOrEl)
    : modalIdOrEl;
  if (!el) return;
  el.classList.add('active');
  el.querySelector('[autofocus]')?.focus();
}

/**
 * Close a modal overlay.
 * @param {string|HTMLElement} modalIdOrEl
 */
export function closeModal(modalIdOrEl) {
  const el = typeof modalIdOrEl === 'string'
    ? document.getElementById(modalIdOrEl)
    : modalIdOrEl;
  if (!el) return;
  el.classList.remove('active');
}

/**
 * Close a modal when clicking the backdrop.
 * @param {MouseEvent} event
 * @param {HTMLElement} modalEl
 */
export function handleBackdropClose(event, modalEl) {
  if (event.target === modalEl) closeModal(modalEl);
}

/**
 * Initialise all modals — binds close buttons and Escape key.
 */
export function initModals() {
  // Close buttons
  document.querySelectorAll('[data-close-modal]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const target = btn.dataset.closeModal
        ? document.getElementById(btn.dataset.closeModal)
        : btn.closest('.modal-overlay');
      if (target) closeModal(target);
    });
  });

  // Escape key
  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    const open = document.querySelector('.modal-overlay.active');
    if (open) closeModal(open);
  });

  // Backdrop click
  document.querySelectorAll('.modal-overlay').forEach((overlay) => {
    overlay.addEventListener('click', (e) => handleBackdropClose(e, overlay));
  });
}
