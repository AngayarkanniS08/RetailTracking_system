/**
 * InventoryPagination.js — pagination control wiring.
 *
 * Delegates clicks on the pagination buttons to the injected navigation
 * handler. The current page is tracked in InventoryState.
 */

export class InventoryPagination {
  /**
   * @param {{ onPrev: Function, onNext: Function, onPage: Function }} handlers
   */
  constructor(handlers) {
    this.handlers = handlers;
    this.bound = false;
  }

  bind() {
    if (this.bound) return;
    this.bound = true;

    const container = document.getElementById('inventoryPaginationControls');
    if (!container) return;

    container.addEventListener('click', (e) => {
      const btn = e.target.closest('button');
      if (!btn) return;
      if (btn.id === 'prevInvPageBtn') {
        this.handlers.onPrev?.();
      } else if (btn.id === 'nextInvPageBtn') {
        this.handlers.onNext?.();
      } else {
        const pageBtn = btn.closest('[data-page]');
        if (pageBtn) this.handlers.onPage?.(Number(pageBtn.dataset.page));
      }
    });
  }
}
