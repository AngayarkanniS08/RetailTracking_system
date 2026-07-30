import { apiRequest } from '../core/api.js';
import { formatCurrency } from '../utils/format.js';

export class Cart {
  constructor() {
    this.items = [];
    this.billDiscount = 0;
    this.enableGst = true;
    this.totals = null;
    this._onUpdate = null;
  }

  onUpdate(fn) {
    this._onUpdate = fn;
  }

  addItem(productData) {
    const maxStock = parseInt(productData.quantity ?? productData.available_quantity ?? 99999, 10);
    const existing = this.items.find(i => i.batch_id === productData.batch_id);

    if (existing) {
      if (existing.quantity >= maxStock) {
        if (typeof window.showToast === 'function') {
          window.showToast(`Cannot add more. Available stock cap reached (${maxStock} max).`, 'warning');
        }
        return;
      }
      existing.quantity += 1;
    } else {
      if (maxStock <= 0) {
        if (typeof window.showToast === 'function') {
          window.showToast('Item is out of stock.', 'warning');
        }
        return;
      }
      this.items.push({
        product_id: productData.product_id,
        product_name: productData.product_name,
        batch_id: productData.batch_id,
        batch_number: productData.batch_number || '',
        unit: productData.unit || '',
        hsn_code: productData.hsn_code || '',
        unit_price: productData.selling_price || productData.unit_price || 0,
        available_quantity: maxStock,
        quantity: 1,
        discount_amount: 0,
      });
    }
    this.syncToGrid();
    this.recalculate();
    this._save();
  }

  updateItem(index, field, value) {
    if (this.items[index]) {
      if (field === 'quantity') {
        const val = parseInt(value, 10);
        const maxStock = this.items[index].available_quantity || 99999;
        if (val > maxStock) {
          if (typeof window.showToast === 'function') {
            window.showToast(`Quantity capped at available stock limit (${maxStock} max).`, 'warning');
          }
          this.items[index].quantity = maxStock;
      this.syncToGrid();
      this.recalculate();
      this._save();
      return;
        }
      }
      this.items[index][field] = value;
      this.syncToGrid();
      this.recalculate();
      this._save();
    }
  }

  removeItem(index) {
    this.items.splice(index, 1);
    this.syncToGrid();
    this.recalculate();
    this._save();
  }

  setBillDiscount(value) {
    this.billDiscount = Math.max(0, parseFloat(value) || 0);
    this.recalculate();
    this._save();
  }

  setGstEnabled(enabled) {
    this.enableGst = !!enabled;
    this.recalculate();
    this._save();
  }

  async recalculate() {
    if (this.items.length === 0) {
      this.totals = null;
      this.renderTotals();
      return;
    }

    const payload = {
      items: this.items.map(i => ({
        product_id: i.product_id,
        quantity: i.quantity,
        batch_id: i.batch_id,
        discount_amount: i.discount_amount || 0,
      })),
      bill_discount: this.billDiscount,
      apply_gst: this.enableGst,
    };

    try {
      const result = await apiRequest('/api/billing/calculate', {
        method: 'POST',
        body: JSON.stringify(payload),
      });
      this.totals = result;
      this.renderTotals();
      this.syncFromBackend(result);
    } catch (err) {
      console.error('Cart calculation error:', err);
    }
  }

  syncFromBackend(result) {
    if (!result || !result.items) return;
    result.items.forEach((backendItem, index) => {
      if (this.items[index]) {
        this.items[index].unit_price = backendItem.unit_price;
        this.items[index].product_name = backendItem.product_name;
        this.items[index].batch_number = backendItem.batch_number || this.items[index].batch_number;
        this.items[index].hsn_code = backendItem.hsn_code || this.items[index].hsn_code;
        this.items[index].unit = backendItem.unit || this.items[index].unit;
        this.items[index].gst_rate = backendItem.gst_rate || 0;
        this.items[index].gst_amount = backendItem.gst_amount || 0;
        this.items[index].line_total = backendItem.line_total || (backendItem.unit_price * backendItem.quantity);
      }
    });
    this.syncToGrid();
  }

  syncToGrid() {
    const tbody = document.querySelector('#billingGrid tbody');
    if (!tbody) return;

    const rows = tbody.querySelectorAll('tr');

    for (let i = 0; i < rows.length; i++) {
      const cells = rows[i].querySelectorAll('td');
      if (i < this.items.length) {
        const item = this.items[i];
        rows[i].dataset.batchId = item.batch_id;
        rows[i].dataset.productId = item.product_id;
        rows[i].style.display = '';
        if (cells[0]) cells[0].textContent = item.batch_number;
        if (cells[1]) cells[1].textContent = item.product_name;
        if (cells[2]) { cells[2].dataset.value = item.unit_price; cells[2].textContent = formatCurrency(item.unit_price); }
        if (cells[3]) cells[3].textContent = item.discount_amount > 0 ? String(item.discount_amount) : '';
        if (cells[4]) cells[4].textContent = item.unit;
        if (cells[5]) cells[5].textContent = String(item.quantity);
        if (cells[6]) { const gstRate = item.gst_rate || 0; cells[6].dataset.value = gstRate; cells[6].textContent = gstRate > 0 ? String(gstRate) : ''; }
        if (cells[7]) { const lineTotal = item.line_total || (item.unit_price * item.quantity); cells[7].dataset.value = lineTotal; cells[7].textContent = lineTotal > 0 ? formatCurrency(lineTotal) : ''; }
      } else {
        for (let c = 0; c < cells.length; c++) {
          cells[c].textContent = '';
          cells[c].dataset.value = '';
        }
        rows[i].dataset.batchId = '';
        rows[i].dataset.productId = '';
        rows[i].dataset.index = '';
      }
    }

    if (this._onUpdate) this._onUpdate();
  }

  renderTotals() {
    const subtotalEl = document.getElementById('cartSubtotal');
    const gstEl = document.getElementById('cartGst');
    const totalEl = document.getElementById('cartTotal');

    if (!this.totals) {
      if (subtotalEl) subtotalEl.textContent = '₹0.00';
      if (gstEl) gstEl.textContent = '₹0.00';
      if (totalEl) totalEl.textContent = '₹0.00';
      return;
    }

    if (subtotalEl) subtotalEl.textContent = formatCurrency(this.totals.subtotal);
    if (gstEl) gstEl.textContent = this.totals.gst_total > 0 ? formatCurrency(this.totals.gst_total) : '₹0.00';
    if (totalEl) totalEl.textContent = formatCurrency(this.totals.grand_total);
  }

  toCheckoutPayload(customerId, customerName, customerPhone, amountPaid, paymentMode, notes) {
    if (!this.totals) return null;

    return {
      customer_id: customerId || null,
      customer_name: customerName || null,
      customer_phone: customerPhone || null,
      apply_gst: this.enableGst,
      discount_amount: this.billDiscount,
      amount_paid: amountPaid,
      expected_grand_total: this.totals.grand_total,
      payment_mode: paymentMode || 'cash',
      items: this.items.map((item, idx) => ({
        product_id: item.product_id,
        quantity: item.quantity,
        unit_price: item.unit_price,
        batch_id: item.batch_id,
        discount_amount: item.discount_amount || 0,
      })),
      notes: notes || null,
    };
  }

  clear() {
    this.items = [];
    this.billDiscount = 0;
    this.totals = null;
    this.syncToGrid();
    this.renderTotals();
    this._clearStorage();
  }

  _save() {
    if (typeof sessionStorage === 'undefined') return;
    try {
      sessionStorage.setItem('cart_items', JSON.stringify(this.items));
      sessionStorage.setItem('cart_billDiscount', String(this.billDiscount));
      sessionStorage.setItem('cart_enableGst', String(this.enableGst));
    } catch (e) {}
  }

  _restore() {
    if (typeof sessionStorage === 'undefined') return false;
    try {
      const savedItems = sessionStorage.getItem('cart_items');
      const savedDiscount = sessionStorage.getItem('cart_billDiscount');
      const savedGst = sessionStorage.getItem('cart_enableGst');
      if (savedItems) {
        this.items = JSON.parse(savedItems);
        this.billDiscount = savedDiscount ? parseFloat(savedDiscount) : 0;
        this.enableGst = savedGst !== 'false';
        this.syncToGrid();
        this.recalculate();
        return true;
      }
    } catch (e) {}
    return false;
  }

  _clearStorage() {
    if (typeof sessionStorage === 'undefined') return;
    try {
      sessionStorage.removeItem('cart_items');
      sessionStorage.removeItem('cart_billDiscount');
      sessionStorage.removeItem('cart_enableGst');
    } catch (e) {}
  }

  get itemCount() {
    return this.items.length;
  }
}
