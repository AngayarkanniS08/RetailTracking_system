import { Cart } from '../services/cart.js';
import { apiRequest } from '../core/api.js';
import { showToast } from '../ui/toast.js';
import { escapeHtml } from '../utils/format.js';

let cart;
const _searchCache = new Map();

export function initBillingPage() {
  cart = new Cart();
  window.__cart = cart;

  cart.onUpdate(() => {
    const discountInput = document.getElementById('cartDiscountInput');
    if (discountInput) discountInput.value = cart.billDiscount > 0 ? String(cart.billDiscount) : '';
  });

  const hasSaved = cart._restore();
  if (!hasSaved) {
    cart.recalculate();
  }
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
    results.forEach(p => {
      if (p && p.batch_id) _searchCache.set(String(p.batch_id), p);
    });
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

  dropdown.innerHTML = results.map(p => {
    const batchId = escapeHtml(p.batch_id || '');
    const productName = highlightMatch(p.product_name || '', query);
    const batchNumber = escapeHtml(p.batch_number || '');
    const price = escapeHtml(p.selling_price || 0);
    const stock = escapeHtml(p.quantity || p.remaining_qty || 0);

    return `
      <div class="pos-search-item" onclick="selectPOSProduct('${batchId}')">
        <strong>${productName}</strong>
        <span>Batch: ${batchNumber} | ₹${price} | Stock: ${stock}</span>
      </div>
    `;
  }).join('');
  dropdown.style.display = 'block';
}

export function selectPOSProduct(batchId) {
  if (!batchId || typeof batchId !== 'string') return;
  const product = _searchCache.get(batchId);
  if (!product) return;
  addItemToCart(product);
  hideDropdown('posSearchDropdown');
  document.getElementById('posSearch').value = '';
}

/**
 * Load all customers (no search query) for the dropdown on focus/tap.
 */
export async function loadAllCustomers() {
  const dropdown = document.getElementById('customerSearchDropdown');
  if (dropdown) {
    dropdown.innerHTML = '<div class="cs-item" style="cursor:default;opacity:0.6;">Loading...</div>';
    dropdown.classList.add('is-open');
  }
  try {
    const data = await apiRequest('/api/customers?limit=100');
    const results = data?.data || [];
    renderCustomerDropdown(results, '');
    return results;
  } catch (err) {
    console.error('Failed to load customers:', err);
    if (dropdown) dropdown.classList.remove('is-open');
    return [];
  }
}

/**
 * Handle customer search.
 */
export async function searchCustomers(query) {
  if (!query || query.length < 1) {
    return loadAllCustomers();
  }
  try {
    const data = await apiRequest(`/api/customers?search=${encodeURIComponent(query)}&limit=100`);
    const results = data?.data || [];
    renderCustomerDropdown(results, query);
    return results;
  } catch (err) {
    console.error('Customer search error:', err);
    return [];
  }
}

function renderCustomerDropdown(results, query = '') {
  const dropdown = document.getElementById('customerSearchDropdown');
  if (!dropdown) return;

  if (!results || results.length === 0) {
    dropdown.innerHTML = `
      <div class="cs-item" style="cursor:default;opacity:0.55;text-align:center;padding:14px 0;">
        <span>No customers found</span>
      </div>
    `;
    dropdown.classList.add('is-open');
    return;
  }

  const safeQueryHeader = escapeHtml(query);
  const header = `<div style="padding:6px 14px 4px;font-size:0.7rem;font-weight:700;letter-spacing:0.08em;opacity:0.45;text-transform:uppercase;">${
    query ? `Results for "${safeQueryHeader}"` : `All Customers (${results.length})`
  }</div>`;

  const items = results.map((c) => {
    const id    = escapeHtml(c.id || '');
    const name  = escapeHtml(c.name || '');
    const phone = escapeHtml(c.phone || '');
    const balance = parseFloat(c.current_balance || 0);
    const balanceBadge = balance !== 0
      ? `<span style="font-size:0.72rem;padding:1px 7px;border-radius:20px;background:${
          balance > 0 ? 'var(--success-muted,#16a34a22)' : 'var(--danger-muted,#dc262622)'
        };color:${
          balance > 0 ? 'var(--success,#16a34a)' : 'var(--danger,#dc2626)'
        };">₹${Math.abs(balance).toFixed(2)}</span>`
      : '';
    return `
      <div class="cs-item" role="option" aria-selected="false" tabindex="-1"
           data-id="${id}" data-name="${name}" data-phone="${phone}"
           onclick="selectCustomer(this)">
        <div class="cs-item-main">${highlightMatch(c.name || '', query)} ${balanceBadge}</div>
        <div class="cs-item-sub">${phone || '—'}</div>
      </div>`;
  }).join('');

  dropdown.innerHTML = header + items;
  dropdown.classList.add('is-open');
}

export function selectCustomer(el) {
  const id    = el.dataset.id;
  const name  = el.dataset.name;
  const phone = el.dataset.phone;
  const input = document.getElementById('customerSearchInput');
  const hiddenId = document.getElementById('billCustomerId');
  if (hiddenId)  hiddenId.value = id || '';
  if (input) {
    input.value = name || phone || '';
    input.dataset.selectedId = id || '';
  }
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
  if (!el) return;
  el.classList.remove('is-open');
}

function highlightMatch(text, query) {
  if (!query || typeof query !== 'string') return text;
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
window.loadAllCustomers = loadAllCustomers;

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
  if (['ArrowDown', 'ArrowUp', 'Enter', 'Escape', 'Tab'].includes(e.key)) return;
  const val = e?.target?.value?.trim();
  if (val && val.length >= 1) {
    searchCustomers(val);
  } else {
    loadAllCustomers();
  }
}, 250);

window.onCustomerSearchFocus = () => {
  const input = document.getElementById('customerSearchInput');
  const val = input?.value?.trim();
  if (val && val.length >= 1) {
    searchCustomers(val);
  } else {
    loadAllCustomers();
  }
};

// Close customer dropdown when clicking outside
document.addEventListener('click', (e) => {
  const wrap = document.querySelector('.customer-search-combobox');
  if (wrap && !wrap.contains(e.target)) {
    hideDropdown('customerSearchDropdown');
  }
}, true);

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
