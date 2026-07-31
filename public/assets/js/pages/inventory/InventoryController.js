/**
 * InventoryController.js — orchestrates the inventory page.
 *
 * Responsibilities:
 *   - DOM wiring (attribute-based listeners from legacy markup)
 *   - data loading via InventoryAPI → InventoryState → renderers
 *   - navigation (filters, pagination)
 *   - forwarding mutations to InventoryModal, then refreshing data + badges
 *
 * No business calculations live here — the backend owns every rule.
 */

import { logger } from '../../core/logger.js';
import { inventoryState, setInventoryState } from './InventoryState.js';
import { fetchInventoryApi } from './InventoryAPI.js';
import { renderStats, renderTable, renderPagination } from './InventoryRenderer.js';
import { InventoryTable } from './InventoryTable.js';
import { InventoryFilters } from './InventoryFilters.js';
import { InventoryPagination } from './InventoryPagination.js';
import { InventoryModal } from './InventoryModal.js';
import { registerInventoryWindowHandlers } from './InventoryEvents.js';

export class InventoryController {
  constructor() {
    this.modal = new InventoryModal({ onMutated: () => this.afterMutation() });
    this.table = new InventoryTable({
      onDetails: (id) => this.modal.openStockDetailsModal(id),
      onRestock: (id) => this.modal.openRestockModal(id),
      onEdit: (id) => this.modal.openEditBatch(id),
    });
    this.filters = new InventoryFilters({ onApply: () => this.reload() });
    this.pagination = new InventoryPagination({
      onPrev: () => this.gotoPage(inventoryState.page - 1),
      onNext: () => this.gotoPage(inventoryState.page + 1),
      onPage: (page) => this.gotoPage(page),
    });
  }

  async init() {
    this.bindDom();
    this.filters.bind();
    this.table.bind();
    this.pagination.bind();
    this.modal.initProductCombobox();
    registerInventoryWindowHandlers({ filters: this.filters, modal: this.modal });
    await this.reload();
  }

  bindDom() {
    const saveBtn = document.querySelector('[data-save-stock]');
    if (saveBtn) saveBtn.addEventListener('click', () => this.modal.saveStock());

    document.querySelectorAll('[data-segment]').forEach((el) => {
      el.addEventListener('click', () => this.modal.setPricingMode(el.dataset.segment));
    });

    document.querySelectorAll('[data-calc-trigger]').forEach((el) => {
      el.addEventListener('input', () =>
        this.modal.calculatePrice(el.dataset.calcTrigger, el.dataset.calcSection)
      );
    });

    const saveAlertBtn = document.querySelector('[data-save-alert]');
    if (saveAlertBtn) saveAlertBtn.addEventListener('click', () => this.modal.saveLowStockAlert());

    document.querySelectorAll('[data-recalc]').forEach((el) => {
      el.addEventListener('input', () => this.modal.calculateReorderPoint());
    });

    const alertBtn = document.querySelector('[data-modal-alert]');
    if (alertBtn) alertBtn.addEventListener('click', () => this.modal.openAlertModal());

    // "Add Stock" buttons open a fresh create form (never in edit mode).
    document.querySelectorAll('[data-modal="addStockModal"]').forEach((el) => {
      el.addEventListener('click', () => this.modal.resetAddStockModal());
    });
  }

  async reload() {
    try {
      const res = await fetchInventoryApi({
        page: inventoryState.page,
        limit: inventoryState.limit,
        search: inventoryState.search,
        category_id: inventoryState.categoryId,
        subcategory_id: inventoryState.subcategoryId,
      });

      setInventoryState({
        items: res.data || [],
        summary: res.summary || {},
        totalPages: res.pagination?.total_pages ?? 1,
        totalRecords: res.pagination?.total_records ?? 0,
      });

      renderStats(inventoryState.summary);
      renderTable(inventoryState.items);
      renderPagination(res.pagination || {});
    } catch (err) {
      logger.error('inventory:list', err);
      renderTable([]);
      renderStats({});
    }
  }

  gotoPage(page) {
    const target = Math.max(1, Math.min(page, inventoryState.totalPages));
    setInventoryState({ page: target });
    this.reload();
  }

  afterMutation() {
    this.reload();
    if (typeof window.refreshNotifications === 'function') {
      window.refreshNotifications();
    }
  }
}

let controller = null;

export function initInventoryPage() {
  if (!controller) controller = new InventoryController();
  controller.init();
  return controller;
}
