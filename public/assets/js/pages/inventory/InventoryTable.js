/**
 * InventoryTable.js — table DOM wiring + row-action delegation.
 *
 * Delegates clicks on batch rows to the injected handlers. It knows nothing
 * about business rules — it only translates DOM actions into controller calls.
 */

export class InventoryTable {
  /**
   * @param {{ onDetails: Function, onRestock: Function, onEdit: Function }} handlers
   */
  constructor(handlers) {
    this.handlers = handlers;
    this.bound = false;
  }

  bind() {
    if (this.bound) return;
    this.bound = true;

    const table = document.getElementById('inventoryTable');
    if (!table) return;

    table.addEventListener('click', (e) => {
      const detailsBtn = e.target.closest('[data-stock-details]');
      if (detailsBtn) {
        this.handlers.onDetails?.(detailsBtn.dataset.stockDetails);
        return;
      }
      const restockBtn = e.target.closest('[data-restock]');
      if (restockBtn) {
        this.handlers.onRestock?.(restockBtn.dataset.restock);
        return;
      }
      const editBtn = e.target.closest('[data-edit-batch]');
      if (editBtn) {
        this.handlers.onEdit?.(editBtn.dataset.editBatch);
      }
    });
  }
}
