/**
 * InventoryFilters.js — search box + category/subcategory combobox wiring.
 *
 * The *inventory* filtering (product name, batch, vendor, category...) is done
 * server-side via query params. These widgets only collect the selected
 * filter values and push them to the controller.
 */

import { logger } from '../../core/logger.js';
import { inventoryState, setInventoryState } from './InventoryState.js';
import {
  fetchInventoryCategoriesApi,
  fetchInventorySubcategoriesApi,
} from './InventoryAPI.js';
import { escapeHtml } from './InventoryRenderer.js';

export class InventoryFilters {
  /**
   * @param {{ onApply: Function }} handlers
   */
  constructor(handlers) {
    this.handlers = handlers;
    this.searchTimeout = null;
    this.bound = false;
  }

  bind() {
    if (this.bound) return;
    this.bound = true;

    this.initSearchInput();
    this.initCombobox({
      inputId: 'invCatInput',
      hiddenId: 'invCatFilter',
      dropdownId: 'invCatDropdown',
      fetcher: () => fetchInventoryCategoriesApi(),
      clearLabel: 'All Categories',
      labelFor: (item) => item.name,
      onChange: (id) => {
        setInventoryState({ categoryId: id, subcategoryId: '', page: 1 });
        this.refreshSubcategories();
        this.handlers.onApply?.();
      },
    });
    this.initCombobox({
      inputId: 'invSubCatInput',
      hiddenId: 'invSubCatFilter',
      dropdownId: 'invSubCatDropdown',
      fetcher: () => fetchInventorySubcategoriesApi(inventoryState.categoryId),
      clearLabel: 'All Subcategories',
      labelFor: (item) => item.name,
      onChange: (id) => {
        setInventoryState({ subcategoryId: id, page: 1 });
        this.handlers.onApply?.();
      },
      disabledWhenNoCategory: true,
    });
  }

  initSearchInput() {
    const input = document.getElementById('invSearch');
    if (!input) return;
    input.addEventListener('input', () => this.handleSearchInput());
  }

  handleSearchInput() {
    clearTimeout(this.searchTimeout);
    this.searchTimeout = setTimeout(() => {
      const value = document.getElementById('invSearch')?.value || '';
      setInventoryState({ search: value.trim(), page: 1 });
      this.handlers.onApply?.();
    }, 300);
  }

  refreshSubcategories() {
    const dropdown = document.getElementById('invSubCatDropdown');
    const hidden = document.getElementById('invSubCatFilter');
    const input = document.getElementById('invSubCatInput');
    if (dropdown) dropdown.innerHTML = '';
    if (hidden) hidden.value = '';
    if (input) input.value = '';
  }

  /**
   * Reusable combobox widget (mirrors the add-stock product combobox pattern).
   */
  initCombobox(cfg) {
    const input = document.getElementById(cfg.inputId);
    const hidden = document.getElementById(cfg.hiddenId);
    const dropdown = document.getElementById(cfg.dropdownId);
    if (!input || !hidden || !dropdown) return;

    const render = (items, filterText = '') => {
      const query = filterText.toLowerCase().trim();
      const filtered = items.filter((item) =>
        cfg.labelFor(item).toLowerCase().includes(query)
      );

      const clearRow = `
        <div class="combobox-item" data-id="" data-name="${cfg.clearLabel}" style="padding:10px 14px; cursor:pointer; border-bottom:1px solid var(--border);">
          <div style="font-weight:600; font-size:0.85rem; color:var(--text-strong);">${cfg.clearLabel}</div>
        </div>
      `;

      const rows = filtered.map((item) => `
        <div class="combobox-item" data-id="${escapeHtml(item.id)}" data-name="${escapeHtml(cfg.labelFor(item))}" style="padding:10px 14px; cursor:pointer; border-bottom:1px solid var(--border);">
          <div style="font-weight:600; font-size:0.85rem; color:var(--text-strong);">${escapeHtml(cfg.labelFor(item))}</div>
        </div>
      `).join('');

      dropdown.innerHTML = clearRow + rows;
      dropdown.style.display = 'block';
    };

    const show = async (filterText = '') => {
      if (cfg.disabledWhenNoCategory && !inventoryState.categoryId) {
        dropdown.style.display = 'none';
        return;
      }
      try {
        const items = await cfg.fetcher();
        const list = Array.isArray(items) ? items : (items?.data || items?.categories || items?.subcategories || []);
        render(list, filterText);
      } catch (err) {
        logger.error('inventory:filter', err);
        dropdown.innerHTML = '<div style="padding:10px 14px; color:var(--muted); font-size:0.82rem;">Failed to load</div>';
        dropdown.style.display = 'block';
      }
    };

    input.addEventListener('focus', () => show(input.value));
    input.addEventListener('click', () => show(input.value));
    input.addEventListener('input', () => {
      hidden.value = '';
      show(input.value);
    });

    dropdown.addEventListener('click', (e) => {
      const itemEl = e.target.closest('.combobox-item');
      if (!itemEl) return;
      input.value = itemEl.dataset.name;
      hidden.value = itemEl.dataset.id;
      dropdown.style.display = 'none';
      cfg.onChange(itemEl.dataset.id);
    });

    document.addEventListener('click', (e) => {
      if (!input.contains(e.target) && !dropdown.contains(e.target)) {
        dropdown.style.display = 'none';
      }
    });
  }
}
