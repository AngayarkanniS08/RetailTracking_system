/**
 * InventoryState.js — single source of truth for the inventory page UI state.
 *
 * Pure state container: no DOM access, no business calculations. The
 * controller mutates it; renderers read it.
 */

export const inventoryState = {
  page: 1,
  limit: 5,
  search: '',
  categoryId: '',
  subcategoryId: '',
  totalPages: 1,
  totalRecords: 0,
  items: [],
  summary: {},
  categories: [],
  subcategories: [],
  pricingMode: 'wholesale',
  editingBatchId: null,
  restockBatchId: null,
  cachedProducts: [],
};

export function setInventoryState(patch) {
  Object.assign(inventoryState, patch);
}

export function resetInventoryState() {
  Object.assign(inventoryState, {
    page: 1,
    limit: 5,
    search: '',
    categoryId: '',
    subcategoryId: '',
    totalPages: 1,
    totalRecords: 0,
    items: [],
    summary: {},
    categories: [],
    subcategories: [],
    pricingMode: 'wholesale',
    editingBatchId: null,
    restockBatchId: null,
    cachedProducts: [],
  });
}
