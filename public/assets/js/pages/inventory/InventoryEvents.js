/**
 * InventoryEvents.js — legacy window-handler bridge.
 *
 * Exposes imperative handlers used by inline `onclick` attributes in the
 * served PHP views (restock modal, details modal, search box, etc.). Each
 * handler simply forwards to the corresponding controller/modal method.
 */

export function registerInventoryWindowHandlers({ filters, modal }) {
  window.openRestockForBatch = (batchId) => modal.openRestockModal(batchId);
  window.confirmRestockOrder = () => modal.confirmRestockOrder();
  window.editBatch = (batchId) => modal.openEditBatch(batchId);
  window.openStockIntelligenceModal = (batchId) => modal.openStockDetailsModal(batchId);
  window.handleSearchInput = () => filters.handleSearchInput();
  window.saveStock = () => modal.saveStock();
  window.saveLowStockAlert = () => modal.saveLowStockAlert();
  window.openLowStockAlertModal = () => modal.openAlertModal();
  window.calculateReorderPoint = () => modal.calculateReorderPoint();
  window.setPricingMode = (mode) => modal.setPricingMode(mode);
  window.calculatePrice = (trigger, section) => modal.calculatePrice(trigger, section);
}
