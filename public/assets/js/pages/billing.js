import { Cart } from '../services/cart.js';
import { apiRequest } from '../core/api.js';
import { showToast } from '../ui/toast.js';

let cart;
const _searchCache = {};

export function initBillingPage() {
  cart = new Cart();
  window.__cart = cart;

  cart.onUpdate(() => {
    const discountInput = document.getElementById('cartDiscountInput');
    if (discountInput) discountInput.value = cart.billDiscount > 0 ? String(cart.billDiscount) : '';
  });

  cart.recalculate();
}

/**
 * Called when a product is selected from the POS search dropdown.
 * @param {object} product
 */
export function addItemToCart(product) {
  if (!cart) cart = new Cart();
  cart.addItem(product);
}

/**
 * Update item quantity from grid cell edit.
 */
export function updateItemQty(index, qty) {
  if (!cart) return;
  const val = parseInt(qty, 10);
  if (!isNaN(val) && val >= 0) {
    if (val === 0) {
      cart.removeItem(index);
    } else {
      cart.updateItem(index, 'quantity', val);
    }
  }
}

/**
 * Update item discount from grid cell edit.
 */
export function updateItemDiscount(index, amount) {
  if (!cart) return;
  const val = parseFloat(amount) || 0;
  cart.updateItem(index, 'discount_amount', val);
}

/**
 * Called when the bill-level discount input changes.
 */
export function updateBillDiscount(value) {
  if (!cart) return;
  cart.setBillDiscount(value);
}

/**
 * Called when the GST toggle changes.
 */
export function toggleGst(enabled) {
  if (!cart) return;
  cart.setGstEnabled(enabled);
}

/**
 * Handle POS product search.
 */
export async function searchProducts(query) {
  if (!query || query.length < 2) {
    hideDropdown('posSearchDropdown');
    return [];
  }
  try {
    const data = await apiRequest(`/api/pos/search?q=${encodeURIComponent(query)}&page=1`);
    const results = data?.results || data?.data || data || [];
    results.forEach(p => { _searchCache[p.batch_id] = p; });
    renderSearchDropdown(results, query);
    return results;
  } catch (err) {
    console.error('POS search error:', err);
    return [];
  }
}

function renderSearchDropdown(results, query) {
  const dropdown = document.getElementById('posSearchDropdown');
  if (!dropdown) return;

  if (!results || results.length === 0) {
    dropdown.style.display = 'none';
    return;
  }

  dropdown.innerHTML = results.map(p => `
    <div class="pos-search-item" onclick="selectPOSProduct('${p.batch_id}')">
      <strong>${highlightMatch(p.product_name || '', query)}</strong>
      <span>Batch: ${p.batch_number || ''} | ₹${p.selling_price || 0} | Stock: ${p.quantity || p.remaining_qty || 0}</span>
    </div>
  `).join('');
  dropdown.style.display = 'block';
}

export function selectPOSProduct(batchId) {
  const product = _searchCache[batchId];
  if (!product) return;
  addItemToCart(product);
  hideDropdown('posSearchDropdown');
  document.getElementById('posSearch').value = '';
}

/**
 * Handle customer search.
 */
export async function searchCustomers(query) {
  if (!query || query.length < 1) {
    hideDropdown('customerSearchDropdown');
    return [];
  }
  try {
    const data = await apiRequest(`/api/customers/search?q=${encodeURIComponent(query)}`);
    const results = data?.results || data?.data || data || [];
    renderCustomerDropdown(results);
    return results;
  } catch (err) {
    console.error('Customer search error:', err);
    return [];
  }
}

function renderCustomerDropdown(results) {
  const dropdown = document.getElementById('customerSearchDropdown');
  if (!dropdown) return;
  if (!results || results.length === 0) {
    dropdown.style.display = 'none';
    return;
  }
  dropdown.innerHTML = results.map(c => `
    <div class="customer-search-item" data-id="${c.id || ''}"
         data-name="${c.name || ''}" data-phone="${c.phone || ''}"
         onclick="selectCustomer(this)">
      <strong>${c.name || ''}</strong>
      <span style="font-size:0.8rem;color:var(--muted);">${c.phone || ''}</span>
    </div>
  `).join('');
  dropdown.style.display = 'block';
}

export function selectCustomer(el) {
  const id = el.dataset.id;
  const name = el.dataset.name;
  const phone = el.dataset.phone;
  document.getElementById('billCustomerId').value = id || '';
  document.getElementById('customerSearchInput').value = name || phone || '';
  hideDropdown('customerSearchDropdown');
}

/**
 * Initiate checkout via the backend POST /api/invoices.
 */
export async function processCheckout() {
  if (!cart || cart.items.length === 0) {
    showToast('Cart is empty. Add items before checkout.', 'warning');
    return;
  }
  if (!cart.totals) {
    showToast('Calculating totals... Please wait.', 'info');
    return;
  }

  const customerId = document.getElementById('billCustomerId')?.value || null;
  const customerName = document.getElementById('customerSearchInput')?.value || null;
  const amountPaid = parseFloat(document.getElementById('amountPaidInput')?.value) || 0;
  const paymentMode = document.getElementById('paymentModeSelect')?.value || 'cash';

  const payload = cart.toCheckoutPayload(
    customerId,
    customerName,
    null,
    amountPaid,
    paymentMode,
    null
  );

  if (!payload) {
    showToast('Failed to prepare checkout data.', 'danger');
    return;
  }

  try {
    const result = await apiRequest('/api/invoices', {
      method: 'POST',
      body: JSON.stringify(payload),
    });

    showToast('Invoice created successfully!', 'success');
    cart.clear();
  } catch (err) {
    showToast(err.message || 'Checkout failed', 'danger');
  }
}

function hideDropdown(id) {
  const el = document.getElementById(id);
  if (el) el.style.display = 'none';
}

function highlightMatch(text, query) {
  const idx = text.toLowerCase().indexOf(query.toLowerCase());
  if (idx === -1) return text;
  return text.slice(0, idx) + '<mark>' + text.slice(idx, idx + query.length) + '</mark>' + text.slice(idx + query.length);
}

// Global bindings for inline HTML event handlers
import { debounce } from '../utils/dom.js';

window.addItemToCart = addItemToCart;
window.processCheckout = processCheckout;
window.selectPOSProduct = selectPOSProduct;
window.selectCustomer = selectCustomer;

window.checkout = processCheckout;

let selectedSearchIndex = -1;

window.onPOSSearchKeydown = (e) => {
  const dropdown = document.getElementById('posSearchDropdown');
  if (!dropdown || dropdown.style.display === 'none') return;

  const items = Array.from(dropdown.querySelectorAll('.pos-search-item'));
  if (items.length === 0) return;

  if (e.key === 'ArrowDown') {
    e.preventDefault();
    selectedSearchIndex = (selectedSearchIndex + 1) % items.length;
    items.forEach((el, idx) => {
      el.style.background = idx === selectedSearchIndex ? 'var(--surface-container-low, #f1f5f9)' : '';
    });
  } else if (e.key === 'ArrowUp') {
    e.preventDefault();
    selectedSearchIndex = (selectedSearchIndex - 1 + items.length) % items.length;
    items.forEach((el, idx) => {
      el.style.background = idx === selectedSearchIndex ? 'var(--surface-container-low, #f1f5f9)' : '';
    });
  } else if (e.key === 'Enter') {
    e.preventDefault();
    if (selectedSearchIndex >= 0 && items[selectedSearchIndex]) {
      items[selectedSearchIndex].click();
      selectedSearchIndex = -1;
    }
  } else if (e.key === 'Escape') {
    hideDropdown('posSearchDropdown');
    selectedSearchIndex = -1;
  }
};

window.onPOSSearchKeyup = debounce((e) => {
  if (['ArrowDown', 'ArrowUp', 'Enter', 'Escape'].includes(e.key)) return;
  selectedSearchIndex = -1;
  searchProducts(e.target.value);
}, 300);

window.onCustomerSearchKeyup = debounce((e) => {
  searchCustomers(e.target.value);
}, 300);

window.onGstToggle = (enabled) => {
  toggleGst(enabled);
};

window.onBillDiscount = (value) => {
  updateBillDiscount(value);
};

window.onPaymentMode = (mode) => {
  const input = document.getElementById('amountPaidInput');
  if (!input) return;
  if (mode === 'credit') {
    input.value = '';
    input.placeholder = '₹ 0 (credit sale)';
  } else {
    input.placeholder = '₹ 0';
  }
};

window.onCellQty = (cell) => {
  const tr = cell.closest('tr');
  const index = Array.from(tr.parentNode.children).indexOf(tr);
  if (index >= 0) updateItemQty(index, cell.textContent);
};

window.onCellQtyBlur = (cell) => {
  const val = parseInt(cell.textContent, 10);
  if (isNaN(val) || val < 1) cell.textContent = '';
};

window.onCellDiscount = (cell) => {
  const tr = cell.closest('tr');
  const index = Array.from(tr.parentNode.children).indexOf(tr);
  if (index >= 0) updateItemDiscount(index, cell.textContent);
};

window.onCellDiscountBlur = (cell) => {
  const val = parseFloat(cell.textContent) || 0;
  if (val <= 0) cell.textContent = '';
};

window.closeDeleteConfirm = () => {
  document.getElementById('deleteRowModal').classList.remove('show');
};

window.confirmDeleteRow = () => {
  const name = document.getElementById('deleteRowItemName').textContent;
  if (name && cart) {
    const idx = cart.items.findIndex(i => i.product_name === name);
    if (idx >= 0) cart.removeItem(idx);
  }
  window.closeDeleteConfirm();
};
