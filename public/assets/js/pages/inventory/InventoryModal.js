/**
 * InventoryModal.js — modal workflows for the inventory page.
 *
 * Restock, edit, details, add-stock and low-stock-alert modals. All quantities,
 * statuses and recommendations are read from / sent to the backend — this file
 * never performs stock math.
 */

import { openModal, closeModal } from '../../ui/modal.js';
import { showToast } from '../../ui/toast.js';
import { logger } from '../../core/logger.js';
import { inventoryState, setInventoryState } from './InventoryState.js';
import {
  fetchInventoryDetailsApi,
  createInventoryApi,
  updateInventoryApi,
  restockInventoryApi,
  fetchProductsApi,
  saveInventoryAlertApi,
} from './InventoryAPI.js';


export class InventoryModal {
  /**
   * @param {{ onMutated: Function }} handlers
   */
  constructor(handlers) {
    this.handlers = handlers;
    this.cachedProducts = [];
  }

  // ── Restock workflow ─────────────────────────────────────────

  async openRestockModal(batchId) {
    try {
      const { data } = await fetchInventoryDetailsApi(batchId);
      if (!data) throw new Error('Empty details response');

      const unit = data.unit || 'units';
      const rec = data.restock_recommendation || {};
      const maximumCapacity = rec.maximum_capacity ?? data.original_quantity ?? data.initial_qty ?? 0;
      const currentStock = data.quantity ?? 0;
      const deficit = rec.recommended_order_quantity ?? Math.max(0, maximumCapacity - currentStock);

      setInventoryState({ restockBatchId: batchId });

      this.setText('restockCurrentStock', `${currentStock} ${unit}`);
      this.setText('restockMaxStock', `${maximumCapacity} ${unit}`);
      this.setText('restockDeficit', `${deficit} ${unit}`);

      // Build restockQtyLabel safely using DOM — avoids innerHTML interpolation
      const qtyLabelEl = document.getElementById('restockQtyLabel');
      if (qtyLabelEl) {
        qtyLabelEl.textContent = '';
        qtyLabelEl.append(
          `Order quantity (${String(unit)})`,
          Object.assign(document.createElement('span'), { className: 'required', textContent: '*' })
        );
      }

      this.setText('restockUnitSuffix', unit);

      const qtyInput = document.getElementById('orderQty');
      if (qtyInput) {
        qtyInput.value = deficit > 0 ? deficit : '';
        qtyInput.min = 1;
      }

      // Build restockHelperText safely using DOM — avoids innerHTML interpolation
      const helperEl = document.getElementById('restockHelperText');
      if (helperEl) {
        helperEl.textContent = '';
        const icon = document.createElement('i');
        icon.className = 'ti ti-info-circle';
        helperEl.append(
          icon,
          ` Suggested to bring the batch back to its original quantity (${Number(maximumCapacity)} ${String(unit)}). You can adjust before confirming.`
        );
      }

      openModal('modalOverlay');
    } catch (err) {
      logger.error('inventory:restock-open', err);
      showToast(`Failed to load batch: ${err.message || 'Unknown error'}`, 'error');
    }
  }

  async confirmRestockOrder() {
    const batchId = inventoryState.restockBatchId;
    if (!batchId) return;

    const qtyInput = document.getElementById('orderQty');
    const addQty = parseInt(qtyInput?.value || '0', 10);
    if (!addQty || addQty <= 0) {
      showToast('Please enter a valid order quantity', 'warning');
      return;
    }

    try {
      await restockInventoryApi(batchId, { add_quantity: addQty });
      closeModal('modalOverlay');
      showToast('Batch restocked successfully!', 'success');
      this.handlers.onMutated?.();
    } catch (err) {
      logger.error('inventory:restock', err);
      showToast(`Failed to restock: ${err.message || 'Unknown error'}`, 'error');
    }
  }

  // ── Edit batch workflow ──────────────────────────────────────

  async openEditBatch(batchId) {
    try {
      const { data } = await fetchInventoryDetailsApi(batchId);
      if (!data) throw new Error('Empty details response');

      setInventoryState({ editingBatchId: batchId, expectedUpdatedAt: data.updated_at || null });

      const title = document.getElementById('addStockModalTitle');
      if (title) title.textContent = 'Edit Stock Batch';

      const productInput = document.getElementById('stockProductInput');
      const productHidden = document.getElementById('stockProduct');
      if (productInput) {
        productInput.value = data.product_name || '';
        productInput.disabled = true;
      }
      if (productHidden) productHidden.value = data.product_id || '';

      this.setValue('stockVendor', data.vendor_name || '');
      this.setValue('stockBatchId', data.batch_number || '');
      this.setValue('stockQty', data.quantity ?? 0);
      this.setValue('stockPP', data.cost_price ?? 0);
      this.setValue('stockSP', data.selling_price ?? 0);
      this.setValue('retailBasePrice', data.cost_price ?? 0);
      this.setValue('retailSP', data.retail_price ?? 0);

      const dateEl = document.getElementById('stockDate');
      if (dateEl && data.created_at) {
        const d = new Date(data.created_at);
        if (!Number.isNaN(d.getTime())) dateEl.value = d.toISOString().slice(0, 10);
      }

      openModal('addStockModal');
    } catch (err) {
      logger.error('inventory:edit-open', err);
      showToast(`Failed to load batch: ${err.message || 'Unknown error'}`, 'error');
    }
  }

  async saveStock() {
    const editingBatchId = inventoryState.editingBatchId;
    const inputVal = (document.getElementById('stockProductInput')?.value || '').trim();
    let productId = document.getElementById('stockProduct')?.value;

    if (!editingBatchId && !productId && inputVal && this.cachedProducts.length) {
      const match = this.cachedProducts.find(
        (p) => (p.name || '').toLowerCase() === inputVal.toLowerCase() || String(p.id) === inputVal
      );
      productId = match ? match.id : inputVal;
    }

    const isRetail = inventoryState.pricingMode === 'retail';
    const purchasePrice = parseFloat(document.getElementById(isRetail ? 'retailBasePrice' : 'stockPP')?.value || '0');
    const sellingPrice = parseFloat(document.getElementById(isRetail ? 'retailSP' : 'stockSP')?.value || '0');
    const retailPrice = parseFloat(document.getElementById('retailSP')?.value || '0');
    const quantity = parseInt(document.getElementById('stockQty')?.value || '0', 10);
    const batchNumber = document.getElementById('stockBatchId')?.value || '';
    const vendorName = document.getElementById('stockVendor')?.value || '';
    const dateVal = document.getElementById('stockDate')?.value || '';

    if (!productId) return void showToast('Please select or enter a product for the batch', 'warning');
    if (!batchNumber) return void showToast('Please enter a Batch ID / Number', 'warning');
    if (quantity <= 0) return void showToast('Quantity must be greater than 0', 'warning');

    try {
      if (editingBatchId) {
        await updateInventoryApi(editingBatchId, {
          batch_number: batchNumber,
          vendor_name: vendorName,
          quantity,
          cost_price: purchasePrice,
          selling_price: sellingPrice,
          retail_price: retailPrice,
          created_at: dateVal ? `${dateVal} 00:00:00` : undefined,
          expected_updated_at: inventoryState.expectedUpdatedAt || undefined,
        });
        showToast('Stock batch updated successfully!', 'success');
      } else {
        await createInventoryApi({
          product_id: productId,
          batch_number: batchNumber,
          vendor_name: vendorName,
          quantity,
          cost_price: purchasePrice,
          selling_price: sellingPrice,
          retail_price: retailPrice,
          created_at: dateVal ? `${dateVal} 00:00:00` : undefined,
        });
        showToast('Stock batch added successfully!', 'success');
      }

      closeModal('addStockModal');
      this.resetAddStockModal();
      this.handlers.onMutated?.();
    } catch (err) {
      logger.error('inventory:save-stock', err);
      showToast(`Failed to save stock batch: ${err.message || 'Unknown error'}`, 'error');
    }
  }

  resetAddStockModal() {
    setInventoryState({ editingBatchId: null, expectedUpdatedAt: null });

    const title = document.getElementById('addStockModalTitle');
    if (title) title.textContent = 'Add New Stock Batch';

    const productInput = document.getElementById('stockProductInput');
    if (productInput) {
      productInput.value = '';
      productInput.disabled = false;
    }
    const productHidden = document.getElementById('stockProduct');
    if (productHidden) productHidden.value = '';

    ['stockVendor', 'stockBatchId', 'stockQty', 'stockPP', 'stockProfit', 'stockSP',
      'retailBasePrice', 'retailProfit', 'retailSP', 'stockDate'].forEach((id) => {
      const el = document.getElementById(id);
      if (el) el.value = '';
    });
  }

  // ── Details workflow ─────────────────────────────────────────

  async openStockDetailsModal(batchId) {
    try {
      const { data } = await fetchInventoryDetailsApi(batchId);
      if (!data) throw new Error('Empty details response');

      const rec = data.restock_recommendation || {};
      const unit = data.unit || 'units';

      this.setText('sdProduct', data.product_name || '—');
      this.setText('sdBatch', data.batch_number || '—');
      this.setText('sdVendor', data.vendor_name || '—');
      this.setText('sdQty', `${data.quantity ?? 0} ${unit}`);
      this.setText('sdStatus', data.status_text || '—');

      const statusBadge = document.getElementById('sdStatusBadge');
      if (statusBadge) {
        statusBadge.className = `badge rounded-pill ${data.status_badge_class || 'bg-success'}`;
      }

      this.setText('sdValue', `₹${Number(data.inventory_value ?? 0).toFixed(2)}`);
      this.setText('sdRecommendation',
        `Order ${Number(rec.recommended_order_quantity ?? 0)} ${unit} to reach max ${Number(rec.maximum_capacity ?? 0)} ${unit}`
      );

      const details = [
        ['Date', data.created_at ? new Date(data.created_at).toLocaleDateString('en-IN') : '—'],
        ['Cost Price', `₹${Number(data.cost_price ?? 0).toFixed(2)}`],
        ['Selling Price', `₹${Number(data.selling_price ?? 0).toFixed(2)}`],
        ['Retail Price', `₹${Number(data.retail_price ?? 0).toFixed(2)}`],
        ['Reorder Point', String(data.reorder_level ?? 0)],
        ['Emergency Stock', String(data.emergency_stock ?? 0)],
      ];
      // Build detail rows safely via DocumentFragment — eliminates innerHTML interpolation
      const meta = document.getElementById('sdMeta');
      if (meta) {
        const frag = document.createDocumentFragment();
        details.forEach(([label, value]) => {
          const row = document.createElement('div');
          row.style.cssText = 'display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px dashed var(--border,#eef1f5); font-size:0.85rem;';
          const labelSpan = document.createElement('span');
          labelSpan.style.color = 'var(--muted,#64748b)';
          labelSpan.textContent = label;
          const valueSpan = document.createElement('span');
          valueSpan.style.cssText = 'font-weight:600; color:var(--text-strong,#101828)';
          valueSpan.textContent = value;
          row.append(labelSpan, valueSpan);
          frag.appendChild(row);
        });
        meta.textContent = '';
        meta.appendChild(frag);
      }

      setInventoryState({ restockBatchId: batchId, editingBatchId: null });

      const restockBtn = document.getElementById('sdRestockBtn');
      const editBtn = document.getElementById('sdEditBtn');
      if (restockBtn) restockBtn.dataset.batchId = batchId;
      if (editBtn) editBtn.dataset.batchId = batchId;

      openModal('stockDetailsModal');
    } catch (err) {
      logger.error('inventory:details', err);
      showToast(`Failed to load batch details: ${err.message || 'Unknown error'}`, 'error');
    }
  }

  // ── Low stock alert workflow ─────────────────────────────────

  async openAlertModal() {
    openModal('lowStockAlertModal');

    const select = document.getElementById('alertProductSelect');
    if (!select) return;

    select.innerHTML = '<option value="">Loading products...</option>';
    try {
      const res = await fetchProductsApi();
      const products = res?.data || res?.products || (Array.isArray(res) ? res : []);
      if (Array.isArray(products) && products.length > 0) {
        // Build options safely via DOM — eliminates innerHTML interpolation
        const frag = document.createDocumentFragment();
        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = 'Select a Product...';
        frag.appendChild(placeholder);
        products.forEach((p) => {
          const opt = document.createElement('option');
          opt.value = String(p.id ?? '');
          opt.textContent = String(p.name ?? '');
          frag.appendChild(opt);
        });
        select.textContent = '';
        select.appendChild(frag);
      } else {
        select.textContent = '';
        const opt = document.createElement('option');
        opt.value = '';
        opt.textContent = 'No products found';
        select.appendChild(opt);
      }
    } catch (err) {
      logger.error('inventory:alert-open', err);
      select.textContent = '';
      const opt = document.createElement('option');
      opt.value = '';
      opt.textContent = 'Failed to load products';
      select.appendChild(opt);
    }

    ['alertLeadTime', 'alertDailySale', 'alertEmergencyStock', 'alertThreshold'].forEach((id) => {
      const el = document.getElementById(id);
      if (el) el.value = '';
    });
  }

  calculateReorderPoint() {
    const leadTime = parseInt(document.getElementById('alertLeadTime')?.value || '0', 10);
    const dailySales = parseInt(document.getElementById('alertDailySale')?.value || '0', 10);
    const emergencyStock = parseInt(document.getElementById('alertEmergencyStock')?.value || '0', 10);
    const reorderPoint = leadTime * dailySales + emergencyStock;
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
      await saveInventoryAlertApi({ product_id: productId, lead_time: leadTime, daily_sales: dailySales, emergency_stock: emergencyStock });
      closeModal('lowStockAlertModal');
      showToast('Low stock alert configured successfully!', 'success');
      this.handlers.onMutated?.();
    } catch (err) {
      logger.error('inventory:alert-save', err);
      showToast(`Failed to save low stock alert: ${err.message || 'Unknown error'}`, 'error');
    }
  }

  // ── Add-stock modal helpers (pricing UX) ─────────────────────

  setPricingMode(mode) {
    setInventoryState({ pricingMode: mode });

    const pairs = [
      ['segWholesale', 'wholesale'],
      ['segRetail', 'retail'],
    ];
    pairs.forEach(([btnId, m]) => {
      const btn = document.getElementById(btnId);
      if (!btn) return;
      btn.style.background = mode === m ? '#fff' : 'transparent';
      btn.style.boxShadow = mode === m ? '0 1px 3px rgba(0,0,0,0.1)' : 'none';
      btn.style.color = mode === m ? 'var(--text-strong)' : 'var(--muted)';
    });

    const wholesaleSection = document.getElementById('wholesalePricing');
    const retailSection = document.getElementById('retailPricing');
    if (wholesaleSection) wholesaleSection.style.display = mode === 'wholesale' ? '' : 'none';
    if (retailSection) retailSection.style.display = mode === 'retail' ? '' : 'none';
  }

  calculatePrice(trigger, section) {
    const prefix = section === 'retail' ? 'retail' : '';
    const ppKey = prefix ? 'retailBasePrice' : 'stockPP';
    const profitKey = prefix ? 'retailProfit' : 'stockProfit';
    const spKey = prefix ? 'retailSP' : 'stockSP';

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
    const totalTextKey = prefix ? 'retailTotalText' : 'invTotalText';
    const totalEl = document.getElementById(totalTextKey);
    if (totalEl) totalEl.textContent = `Total: ₹${finalSp.toFixed(2)}`;
  }

  // ── Product combobox (add-stock) ─────────────────────────────

  initProductCombobox() {
    const input = document.getElementById('stockProductInput');
    const hidden = document.getElementById('stockProduct');
    const dropdown = document.getElementById('stockProductDropdown');
    if (!input || !hidden || !dropdown) return;

    const fetchProducts = async () => {
      if (this.cachedProducts.length > 0) return this.cachedProducts;
      try {
        const res = await fetchProductsApi();
        this.cachedProducts = res?.data || res?.products || (Array.isArray(res) ? res : []);
        this.cachedProducts = this.cachedProducts;
        return this.cachedProducts;
      } catch (e) {
        logger.error('inventory:products', e);
        return [];
      }
    };

    const render = (items, filterText = '') => {
      const query = filterText.toLowerCase().trim();
      const filtered = items.filter((p) => {
        const name = (p.name || '').toLowerCase();
        const barcode = (p.display_id || p.barcode || '').toLowerCase();
        return name.includes(query) || barcode.includes(query);
      });

      if (filtered.length === 0) {
        // Build empty state safely via DOM
        const emptyDiv = document.createElement('div');
        emptyDiv.style.cssText = 'padding:10px 14px; color:var(--muted); font-size:0.82rem;';
        emptyDiv.textContent = 'No matching products found';
        dropdown.textContent = '';
        dropdown.appendChild(emptyDiv);
        dropdown.style.display = 'block';
        return;
      }

      // Build combobox items safely via DocumentFragment — eliminates innerHTML interpolation
      const frag = document.createDocumentFragment();
      filtered.forEach((p) => {
        const item = document.createElement('div');
        item.className = 'combobox-item';
        item.dataset.id = String(p.id ?? '');
        item.dataset.name = String(p.name ?? '');
        item.style.cssText = 'padding:10px 14px; cursor:pointer; border-bottom:1px solid var(--border); transition:background 0.15s;';
        const nameDiv = document.createElement('div');
        nameDiv.style.cssText = 'font-weight:600; font-size:0.85rem; color:var(--text-strong);';
        nameDiv.textContent = String(p.name ?? '');
        const metaDiv = document.createElement('div');
        metaDiv.style.cssText = 'font-size:0.75rem; color:var(--muted);';
        metaDiv.textContent = `ID: ${p.display_id ?? '—'} | Unit: ${p.unit ?? 'pcs'}`;
        item.append(nameDiv, metaDiv);
        frag.appendChild(item);
      });
      dropdown.textContent = '';
      dropdown.appendChild(frag);
      dropdown.style.display = 'block';
    };

    const showAllOrFiltered = async (filterText = '') => {
      const items = await fetchProducts();
      render(items, filterText);
    };

    input.addEventListener('focus', () => showAllOrFiltered(input.value));
    input.addEventListener('click', () => showAllOrFiltered(input.value));
    input.addEventListener('input', () => {
      hidden.value = '';
      showAllOrFiltered(input.value);
    });

    dropdown.addEventListener('click', (e) => {
      const itemEl = e.target.closest('.combobox-item');
      if (itemEl) {
        input.value = itemEl.dataset.name;
        hidden.value = itemEl.dataset.id;
        dropdown.style.display = 'none';
      }
    });

    document.addEventListener('click', (e) => {
      if (!input.contains(e.target) && !dropdown.contains(e.target)) {
        dropdown.style.display = 'none';
      }
    });
  }

  // ── DOM helpers ──────────────────────────────────────────────

  setText(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = String(value);
  }

  // setHtml() intentionally removed — use DOM construction methods instead.
  // All HTML content should be built via createElement/createDocumentFragment
  // to permanently prevent innerHTML interpolation XSS patterns.

  setValue(id, value) {
    const el = document.getElementById(id);
    if (el) el.value = String(value ?? '');
  }
}
