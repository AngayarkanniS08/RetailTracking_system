import { fetchInventoryItemsApi } from '../services/inventory.service.js';
import { formatCurrency, formatDate } from '../utils/format.js';
import { logger } from '../core/logger.js';
import { apiRequest } from '../core/api.js';
import { openModal, closeModal } from '../ui/modal.js';
import { showToast } from '../ui/toast.js';

class InventoryPage {
  constructor() {
    this.state = { pricingMode: 'wholesale' };
  }

  cacheDom() {
    this.dom = {
      tableContainer: document.querySelector('#inventoryTable')?.closest('.table-container'),
      tbody: document.querySelector('#inventoryTable tbody'),
      emptyState: document.getElementById('inventoryEmptyState'),

      addStockBtn: document.querySelector('[data-modal="addStockModal"]'),
      alertBtn: document.querySelector('[data-modal-alert]'),
      inventoryTable: document.getElementById('inventoryTable'),
    };
  }

  async init() {
    this.cacheDom();
    this.bindEvents();

    try {
      const items = await fetchInventoryItemsApi();
      this.renderTable(items);
    } catch (err) {
      logger.error('inventory', err);
      this.renderTable([]);
    }
  }

  bindEvents() {
    if (this.isEventsBound) return;
    this.isEventsBound = true;

    const addStockModal = document.getElementById('addStockModal');
    const lowStockModal = document.getElementById('lowStockAlertModal');

    if (addStockModal) {
      this.initProductCombobox();

      const saveBtn = addStockModal.querySelector('[data-save-stock]');
      if (saveBtn) saveBtn.addEventListener('click', () => this.saveStock());

      const segments = addStockModal.querySelectorAll('[data-segment]');
      segments.forEach(el =>
        el.addEventListener('click', () => this.setPricingMode(el.dataset.segment))
      );

      addStockModal.querySelectorAll('[data-calc-trigger]').forEach(el =>
        el.addEventListener('input', () =>
          this.calculatePrice(el.dataset.calcTrigger, el.dataset.calcSection)
        )
      );
    }

    if (lowStockModal) {
      const saveAlertBtn = lowStockModal.querySelector('[data-save-alert]');
      if (saveAlertBtn) saveAlertBtn.addEventListener('click', () => this.saveLowStockAlert());

      lowStockModal.querySelectorAll('[data-recalc]').forEach(el =>
        el.addEventListener('input', () => this.calculateReorderPoint())
      );
    }

    if (this.dom.alertBtn) {
      this.dom.alertBtn.addEventListener('click', () => this.openAlertModal());
    }

    if (this.dom.inventoryTable) {
      this.dom.inventoryTable.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-stock-details]');
        if (btn) {
          const id = btn.dataset.stockDetails;
          if (typeof window.openStockIntelligenceModal === 'function') {
            window.openStockIntelligenceModal(id);
          }
        }
      });
    }
  }

  renderTable(items = []) {
    const { tableContainer, tbody, emptyState } = this.dom;
    if (!items || items.length === 0) {
      if (tableContainer) tableContainer.style.display = 'none';
      if (emptyState) emptyState.style.display = 'flex';
      return;
    }
    if (tableContainer) tableContainer.style.display = 'block';
    if (emptyState) emptyState.style.display = 'none';
    if (!tbody) return;

    tbody.innerHTML = items.map(item => {
      const batchLabel = item.batch_number || item.batch_id || (item.id ? 'BAT-' + item.id.substring(0, 6) : '—');
      const rawDate = item.created_at || item.date || '';
      const formattedDate = rawDate ? formatDate(rawDate) : '—';
      const productName = item.product_name || item.name || '—';
      const qty = item.stock_qty ?? item.quantity ?? 0;

      return `
        <tr>
          <td style="font-weight: 600; color: var(--text-strong);">${batchLabel}</td>
          <td>${formattedDate}</td>
          <td>${item.vendor_name || '—'}</td>
          <td style="font-weight: 500;">${productName}</td>
          <td class="tabular-nums">${formatCurrency(item.cost_price ?? item.purchase_price ?? 0)}</td>
          <td class="tabular-nums">${formatCurrency(item.selling_price ?? 0)}</td>
          <td style="font-weight: 600;">${qty} ${item.unit || ''}</td>
          <td>
            <span class="badge ${this._statusBadge(qty, item.min_threshold ?? 10)}">
              ${this._statusText(qty, item.min_threshold ?? 10)}
            </span>
          </td>
          <td style="text-align: right;">
            <button class="btn btn-xs btn-outline" data-stock-details="${item.id}">Details</button>
          </td>
        </tr>
      `;
    }).join('');
  }

  _statusBadge(qty, threshold) {
    if (qty <= 0) return 'badge-danger';
    if (qty <= threshold) return 'badge-warning';
    return 'badge-success';
  }

  _statusText(qty, threshold) {
    if (qty <= 0) return 'Out of Stock';
    if (qty <= threshold) return 'Low Stock';
    return 'In Stock';
  }

  setPricingMode(mode) {
    this.state.pricingMode = mode;

    const wholesaleBtn = document.getElementById('segWholesale');
    const retailBtn = document.getElementById('segRetail');
    const wholesaleSection = document.getElementById('wholesalePricing');
    const retailSection = document.getElementById('retailPricing');

    if (wholesaleBtn) {
      wholesaleBtn.style.background = mode === 'wholesale' ? '#fff' : 'transparent';
      wholesaleBtn.style.boxShadow = mode === 'wholesale' ? '0 1px 3px rgba(0,0,0,0.1)' : 'none';
      wholesaleBtn.style.color = mode === 'wholesale' ? 'var(--text-strong)' : 'var(--muted)';
    }
    if (retailBtn) {
      retailBtn.style.background = mode === 'retail' ? '#fff' : 'transparent';
      retailBtn.style.boxShadow = mode === 'retail' ? '0 1px 3px rgba(0,0,0,0.1)' : 'none';
      retailBtn.style.color = mode === 'retail' ? 'var(--text-strong)' : 'var(--muted)';
    }
    if (wholesaleSection) wholesaleSection.style.display = mode === 'wholesale' ? '' : 'none';
    if (retailSection) retailSection.style.display = mode === 'retail' ? '' : 'none';
  }

  calculatePrice(trigger, section) {
    const prefix = section === 'retail' ? 'retail' : '';
    const ppKey = prefix ? 'retailBasePrice' : 'stockPP';
    const profitKey = prefix ? 'retailProfit' : 'stockProfit';
    const spKey = prefix ? 'retailSP' : 'stockSP';
    const gstTextKey = prefix ? 'retailGstRateText' : 'invGstRateText';
    const totalTextKey = prefix ? 'retailTotalText' : 'invTotalText';

    const pp = parseFloat(document.getElementById(ppKey)?.value) || 0;
    const profit = parseFloat(document.getElementById(profitKey)?.value) || 0;
    const sp = parseFloat(document.getElementById(spKey)?.value) || 0;

    if (trigger === 'profit') {
      const spField = document.getElementById(spKey);
      if (spField) spField.value = (pp + profit).toFixed(2);
    } else if (trigger === 'sp') {
      const profitField = document.getElementById(profitKey);
      if (profitField) profitField.value = Math.max(0, sp - pp).toFixed(2);
    }

    const finalSp = parseFloat(document.getElementById(spKey)?.value) || 0;
    const total = finalSp;

    const gstEl = document.getElementById(gstTextKey);
    const totalEl = document.getElementById(totalTextKey);
    if (gstEl) gstEl.textContent = `GST: ₹0.00`;
    if (totalEl) totalEl.textContent = `Total: ₹${total.toFixed(2)}`;
  }

  initProductCombobox() {
    const input = document.getElementById('stockProductInput');
    const hidden = document.getElementById('stockProduct');
    const dropdown = document.getElementById('stockProductDropdown');
    if (!input || !hidden || !dropdown) return;

    let productsList = [];

    const fetchProducts = async () => {
      if (productsList.length > 0) return productsList;
      try {
        const res = await apiRequest('/api/products');
        productsList = Array.isArray(res) ? res : (res?.data || res?.products || []);
        this.cachedProducts = productsList;
        return productsList;
      } catch (e) {
        logger.error('Failed to load products for stock combobox:', e);
        return [];
      }
    };

    const renderDropdown = (items, filterText = '') => {
      const query = filterText.toLowerCase().trim();
      const filtered = items.filter(p => {
        const name = (p.name || p.product_name || '').toLowerCase();
        const barcode = (p.barcode || '').toLowerCase();
        return name.includes(query) || barcode.includes(query);
      });

      if (filtered.length === 0) {
        dropdown.innerHTML = '<div style="padding: 10px 14px; color: var(--muted); font-size: 0.82rem;">No matching products found</div>';
        dropdown.style.display = 'block';
        return;
      }

      dropdown.innerHTML = filtered.map(p => `
        <div class="combobox-item" data-id="${p.id}" data-name="${p.name}" style="padding: 10px 14px; cursor: pointer; border-bottom: 1px solid var(--border); transition: background 0.15s;">
          <div style="font-weight: 600; font-size: 0.85rem; color: var(--text-strong);">${p.name}</div>
          <div style="font-size: 0.75rem; color: var(--muted);">Stock: ${p.stock ?? p.quantity ?? 0} ${p.unit || 'pcs'} | Cost: ₹${p.cost_price ?? p.purchase_price ?? 0}</div>
        </div>
      `).join('');

      dropdown.style.display = 'block';
    };

    const showAllOrFiltered = async (filterText = '') => {
      const items = await fetchProducts();
      renderDropdown(items, filterText);
    };

    input.addEventListener('focus', () => showAllOrFiltered(input.value));
    input.addEventListener('click', () => showAllOrFiltered(input.value));

    input.addEventListener('input', async () => {
      hidden.value = '';
      showAllOrFiltered(input.value);
    });

    dropdown.addEventListener('click', (e) => {
      const itemEl = e.target.closest('.combobox-item');
      if (itemEl) {
        const id = itemEl.dataset.id;
        const name = itemEl.dataset.name;
        input.value = name;
        hidden.value = id;
        dropdown.style.display = 'none';
      }
    });

    document.addEventListener('click', (e) => {
      if (!input.contains(e.target) && !dropdown.contains(e.target)) {
        dropdown.style.display = 'none';
      }
    });
  }

  async saveStock() {
    let productId = document.getElementById('stockProduct')?.value;
    const inputVal = (document.getElementById('stockProductInput')?.value || '').trim();

    if (!productId && inputVal && this.cachedProducts) {
      const match = this.cachedProducts.find(p =>
        (p.name || '').toLowerCase() === inputVal.toLowerCase() || String(p.id) === inputVal
      );
      if (match) {
        productId = match.id;
      } else {
        productId = inputVal;
      }
    } else if (!productId) {
      productId = inputVal;
    }

    const vendorName = document.getElementById('stockVendor')?.value || '';
    const batchNumber = document.getElementById('stockBatchId')?.value || '';
    const isRetail = this.state.pricingMode === 'retail';

    const purchasePrice = parseFloat(
      document.getElementById(isRetail ? 'retailBasePrice' : 'stockPP')?.value || '0'
    );
    const sellingPrice = parseFloat(
      document.getElementById(isRetail ? 'retailSP' : 'stockSP')?.value || '0'
    );
    const retailPrice = parseFloat(
      document.getElementById('retailSP')?.value || '0'
    );
    const quantity = parseInt(document.getElementById('stockQty')?.value || '0', 10);
    const dateVal = document.getElementById('stockDate')?.value || '';

    if (!productId) return void showToast('Please select or enter a product for the batch', 'warning');
    if (!batchNumber) return void showToast('Please enter a Batch ID / Number', 'warning');
    if (quantity <= 0) return void showToast('Quantity must be greater than 0', 'warning');

    try {
      await apiRequest('/api/inventory/batches', {
        method: 'POST',
        body: JSON.stringify({
          product_id: productId,
          batch_number: batchNumber,
          vendor_name: vendorName,
          initial_qty: quantity,
          quantity,
          cost_price: purchasePrice,
          purchase_price: purchasePrice,
          selling_price: sellingPrice,
          retail_price: retailPrice,
          created_at: dateVal ? `${dateVal} 00:00:00` : undefined,
        }),
      });
      closeModal('addStockModal');
      showToast('Stock batch added successfully!', 'success');
      this.init();
    } catch (err) {
      logger.error('Failed to save stock batch:', err);
      showToast(`Failed to save stock batch: ${err.message || 'Unknown error'}`, 'error');
    }
  }

  async openAlertModal() {
    openModal('lowStockAlertModal');

    const select = document.getElementById('alertProductSelect');
    if (!select) return;

    select.innerHTML = '<option value="">Loading products...</option>';
    try {
      const res = await apiRequest('/api/products');
      const products = res.data || res.products || (Array.isArray(res) ? res : []);
      if (Array.isArray(products) && products.length > 0) {
        select.innerHTML = '<option value="">Select a Product...</option>' +
          products.map(p => `<option value="${p.id}">${p.name}</option>`).join('');
      } else {
        select.innerHTML = '<option value="">No products found</option>';
      }
    } catch (err) {
      logger.error('Failed to load products for alert modal:', err);
      select.innerHTML = '<option value="">Failed to load products</option>';
    }

    ['alertLeadTime', 'alertDailySale', 'alertEmergencyStock', 'alertThreshold']
      .map(id => document.getElementById(id))
      .forEach(el => { if (el) el.value = ''; });
  }

  calculateReorderPoint() {
    const leadTime = parseInt(document.getElementById('alertLeadTime')?.value || '0', 10);
    const dailySales = parseInt(document.getElementById('alertDailySale')?.value || '0', 10);
    const emergencyStock = parseInt(document.getElementById('alertEmergencyStock')?.value || '0', 10);
    const reorderPoint = (leadTime * dailySales) + emergencyStock;
    const thresholdEl = document.getElementById('alertThreshold');
    if (thresholdEl) thresholdEl.value = reorderPoint > 0 ? reorderPoint : '';
  }

  async saveLowStockAlert() {
    const productId = document.getElementById('alertProductSelect')?.value;
    const leadTime = parseInt(document.getElementById('alertLeadTime')?.value || '0', 10);
    const dailySales = parseInt(document.getElementById('alertDailySale')?.value || '0', 10);
    const emergencyStock = parseInt(document.getElementById('alertEmergencyStock')?.value || '0', 10);

    if (!productId) return void showToast('Please select a product for the alert', 'warning');

    try {
      await apiRequest('/api/inventory/alerts', {
        method: 'POST',
        body: JSON.stringify({ product_id: productId, lead_time: leadTime, daily_sales: dailySales, emergency_stock: emergencyStock }),
      });
      closeModal('lowStockAlertModal');
      showToast('Low stock alert configured successfully!', 'success');
      if (typeof window.openActiveAlertsModal === 'function') window.openActiveAlertsModal();
    } catch (err) {
      logger.error('Failed to save low stock alert:', err);
      showToast(`Failed to save low stock alert: ${err.message || 'Unknown error'}`, 'error');
    }
  }
}

let pageInstance;

export async function initInventoryPage() {
  pageInstance = new InventoryPage();
  await pageInstance.init();
}
