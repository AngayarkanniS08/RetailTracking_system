// inventory.js
let currentInventoryPage = 1;
let totalInventoryPages = 1;

// Initialize global state arrays if they are not already defined
window.batches = window.batches || [];
window.sale_items = window.sale_items || [];
window.stock_list = window.stock_list || [];
window.sales = window.sales || [];
window.cart = window.cart || [];
window.inventoryProductsCache = window.inventoryProductsCache || {};

// Helper: escape HTML
function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>]/g, function (m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}

// Helper: generate unique IDs (e.g. B12345)
function generateId(prefix) {
    return prefix + Math.floor(Math.random() * 100000);
}

// Helper: get product from global cache or ProductMaster list
function getProduct(pid) {
    if (window.inventoryProductsCache && window.inventoryProductsCache[pid]) {
        return window.inventoryProductsCache[pid];
    }
    if (typeof products !== 'undefined' && Array.isArray(products)) {
        const found = products.find(p => p.id === pid);
        if (found) return found;
    }
    return null;
}

// ────────────────────────────────────────────────────────────
// 1. Load categories for filter dropdown and modal
// ────────────────────────────────────────────────────────────
async function loadCategoriesForInventory() {
    try {
        const data = await window.apiRequest('/api/categories');
        if (data && !data.error) {
            // Category filter in inventory table
            const filterSelect = document.getElementById('invCatFilter');
            if (filterSelect) {
                filterSelect.innerHTML = '<option value="">All Categories</option>';
                data.forEach(cat => {
                    filterSelect.innerHTML += `<option value="${cat.id}">${escapeHtml(cat.name)}</option>`;
                });
            }

            // Category dropdown inside "Add New Stock" modal
            const stockCatSelect = document.getElementById('stockCategory');
            if (stockCatSelect) {
                stockCatSelect.innerHTML = '<option value="">-- Select Category --</option>';
                data.forEach(cat => {
                    stockCatSelect.innerHTML += `<option value="${cat.id}">${escapeHtml(cat.name)}</option>`;
                });
            }
        }
    } catch (err) {
        console.error('Failed to load categories for inventory', err);
    }
}

// ────────────────────────────────────────────────────────────
// 2. Load products based on selected category (for Add Stock modal)
// ────────────────────────────────────────────────────────────
async function loadProductsByCategory(categoryId) {
    const productSelect = document.getElementById('stockProduct');
    if (!productSelect) return;
    try {
        let url = '/api/products?limit=100';
        if (categoryId && categoryId !== 'all') {
            url += `&category_id=${categoryId}`;
        }
        const data = await window.apiRequest(url);
        const productList = Array.isArray(data) ? data : (data && Array.isArray(data.data) ? data.data : []);
        productSelect.innerHTML = '<option value="">-- Select Product --</option>';
        if (productList) {
            productList.forEach(prod => {
                // Cache this product
                window.inventoryProductsCache[prod.id] = prod;
                productSelect.innerHTML += `<option value="${prod.id}">${escapeHtml(prod.name)}</option>`;
            });
        }
    } catch (err) {
        console.error('Failed to load products', err);
    }
}
// Load products for the Add Stock modal based on currently selected category/subcategory
async function loadProductsForAddStockModal() {
    const categoryId = document.getElementById('invCatFilter')?.value || '';
    const subcategoryId = document.getElementById('invSubCatFilter')?.value || '';
    const productSelect = document.getElementById('stockProduct');
    if (!productSelect) return;

    productSelect.innerHTML = '<option value="">-- Select Product --</option>';

    let url = '/api/products?limit=100';
    if (categoryId) url += `&category_id=${categoryId}`;
    if (subcategoryId) url += `&subcategory_id=${subcategoryId}`;

    try {
        const data = await window.apiRequest(url);
        const productList = Array.isArray(data) ? data : (data && Array.isArray(data.data) ? data.data : []);
        if (productList) {
            productList.forEach(prod => {
                // Cache this product so getProduct(pid) retrieves it correctly for GST/calculations
                window.inventoryProductsCache[prod.id] = prod;
                productSelect.innerHTML += `<option value="${prod.id}">${escapeHtml(prod.name)}</option>`;
            });
        }
    } catch (err) {
        console.error('Failed to load products', err);
    }
}

window.currentEditingBatchId = null;

function resetAddStockModalState() {
    window.currentEditingBatchId = null;

    const titleEl = document.getElementById('addStockModalTitle');
    if (titleEl) titleEl.textContent = 'Add New Stock Batch';

    const saveBtn = document.getElementById('addStockModalBtn');
    if (saveBtn) saveBtn.textContent = 'Save Batch Entry';

    const productSelect = document.getElementById('stockProduct');
    if (productSelect) {
        productSelect.disabled = false;
        productSelect.innerHTML = '<option value="">-- Select Product --</option>';
    }

    // Clear fields
    const fields = ['stockVendor', 'stockPP', 'stockProfit', 'stockSP', 'retailBasePrice', 'retailProfit', 'retailSP', 'stockQty', 'stockDate'];
    fields.forEach(f => {
        const el = document.getElementById(f);
        if (el) el.value = '';
    });
    const catSel = document.getElementById('stockCategory');
    if (catSel) catSel.value = '';
}

const originalCloseModal = window.closeModal;
window.closeModal = function (modalId) {
    if (modalId === 'addStockModal') {
        resetAddStockModalState();
    }
    originalCloseModal(modalId);
};

// Save the original openModal if needed, or define a new one
const originalOpenModal = window.openModal;
window.openModal = function (modalId) {
    if (modalId === 'addStockModal') {
        if (!window.currentEditingBatchId) {
            resetAddStockModalState();
            loadProductsForAddStockModal();
        }
    }
    originalOpenModal(modalId);
};

// ────────────────────────────────────────────────────────────
// 3. UI Pricing Modes Toggle
// ────────────────────────────────────────────────────────────
function setPricingMode(mode) {
    const segW = document.getElementById('segWholesale');
    const segR = document.getElementById('segRetail');
    const retailCol = document.getElementById('retailColumn');
    const pricingGrid = document.getElementById('pricingGrid');

    if (!segW || !segR) return;

    if (mode === 'retail') {
        segR.classList.add('active');
        segW.classList.remove('active');
        if (retailCol) retailCol.style.display = 'block';
        if (pricingGrid) pricingGrid.style.gridTemplateColumns = '1fr 1fr';
    } else {
        segW.classList.add('active');
        segR.classList.remove('active');
        if (retailCol) retailCol.style.display = 'none';
        if (pricingGrid) pricingGrid.style.gridTemplateColumns = '1fr';
    }
}

// ────────────────────────────────────────────────────────────
// 4. Calculations (Wholesale / Retail)
// ────────────────────────────────────────────────────────────
function calculateInventoryMath(calcMode = 'profit') {
    const spInput = document.getElementById('stockSP');
    const ppInput = document.getElementById('stockPP');
    const profitInput = document.getElementById('stockProfit');
    const qtyInput = document.getElementById('stockQty');
    const retailBaseInput = document.getElementById('retailBasePrice');

    if (!ppInput || !spInput || !profitInput) return;

    const pp = parseFloat(ppInput.value) || 0;
    const qty = parseFloat(qtyInput.value) || 0;
    let sp = parseFloat(spInput.value) || 0;
    let profit = parseFloat(profitInput.value) || 0;

    // Update Retail Base Price (Auto)
    if (qty > 0 && retailBaseInput) {
        retailBaseInput.value = (pp / qty).toFixed(2);
        calculateRetailMath('profit'); // Refresh retail math
    }

    // Bidirectional calculation
    if (calcMode === 'profit') {
        profit = sp > 0 ? (sp - pp) : 0;
        profitInput.value = profit > 0 ? profit.toFixed(2) : '';
    } else if (calcMode === 'sp') {
        sp = pp + profit;
        spInput.value = sp > 0 ? sp.toFixed(2) : '';
    }

    // GST Calculation
    const productSelect = document.getElementById('stockProduct');
    const pid = productSelect ? productSelect.value : '';
    const product = getProduct(pid);
    const gstRate = product ? (parseFloat(product.gst_rate || product.gst) || 0) : 0;
    const outputGst = sp * (gstRate / 100);
    const finalCustomerBill = sp + outputGst;

    const gstRateText = document.getElementById('invGstRateText');
    if (gstRateText) {
        gstRateText.innerHTML = `GST (${gstRate}%): <strong>${formatCurrency(outputGst)}</strong>`;
    }
    const totalText = document.getElementById('invTotalText');
    if (totalText) {
        totalText.innerText = `Total: ${formatCurrency(finalCustomerBill)}`;
    }
}

function calculateRetailMath(calcMode = 'profit') {
    const rbInput = document.getElementById('retailBasePrice');
    const rsInput = document.getElementById('retailSP');
    const rpInput = document.getElementById('retailProfit');

    if (!rbInput || !rsInput || !rpInput) return;

    const rb = parseFloat(rbInput.value) || 0;
    let rs = parseFloat(rsInput.value) || 0;
    let rp = parseFloat(rpInput.value) || 0;

    if (calcMode === 'profit') {
        rp = rs > 0 ? (rs - rb) : 0;
        rpInput.value = rp > 0 ? rp.toFixed(2) : '';
    } else if (calcMode === 'sp') {
        rs = rb + rp;
        rsInput.value = rs > 0 ? rs.toFixed(2) : '';
    }

    const productSelect = document.getElementById('stockProduct');
    const pid = productSelect ? productSelect.value : '';
    const product = getProduct(pid);
    const gstRate = product ? (parseFloat(product.gst_rate || product.gst) || 0) : 0;
    const outputGst = rs * (gstRate / 100);
    const finalCustomerBill = rs + outputGst;

    const retailGstRateText = document.getElementById('retailGstRateText');
    if (retailGstRateText) {
        retailGstRateText.innerHTML = `GST (${gstRate}%): <strong>${formatCurrency(outputGst)}</strong>`;
    }
    const retailTotalText = document.getElementById('retailTotalText');
    if (retailTotalText) {
        retailTotalText.innerText = `Total: ${formatCurrency(finalCustomerBill)}`;
    }
}

// ────────────────────────────────────────────────────────────
// 5. Saving Stock
// ────────────────────────────────────────────────────────────
async function saveStock() {
    const productSelect = document.getElementById('stockProduct');
    const pid = productSelect ? productSelect.value : '';
    const vname = document.getElementById('stockVendor') ? document.getElementById('stockVendor').value : '';
    const pp = parseFloat(document.getElementById('stockPP') ? document.getElementById('stockPP').value : 0);
    const sp = parseFloat(document.getElementById('stockSP') ? document.getElementById('stockSP').value : 0);
    const retailSP = parseFloat(document.getElementById('retailSP') ? document.getElementById('retailSP').value : 0);
    const qty = parseInt(document.getElementById('stockQty') ? document.getElementById('stockQty').value : 0);
    const dateVal = document.getElementById('stockDate') ? document.getElementById('stockDate').value : '';

    if (!pid) return alert('Please select a product');
    if (!vname || isNaN(pp) || isNaN(sp) || isNaN(qty) || qty <= 0) {
        return alert('Please fill all required fields (Wholesale prices & Qty)');
    }

    const payload = {
        product_id: pid,
        vendor_name: vname,
        purchase_price: pp,
        selling_price: sp,
        retail_price: retailSP || (sp / qty),
        quantity: qty,
        created_at: dateVal || null
    };

    try {
        if (window.currentEditingBatchId) {
            // Edit existing batch
            const response = await window.apiRequest(`/api/inventory/batches/${window.currentEditingBatchId}`, {
                method: 'PUT',
                body: JSON.stringify(payload)
            });
            if (response && response.success) {
                // Update local batch list
                const idx = window.batches.findIndex(b => b.id === window.currentEditingBatchId);
                if (idx !== -1) {
                    window.batches[idx] = { ...window.batches[idx], ...payload };
                }
            } else {
                alert('Failed to update stock: ' + (response.error || 'Unknown error'));
                return;
            }
        } else {
            // Create new batch
            const response = await window.apiRequest('/api/inventory/batches', {
                method: 'POST',
                body: JSON.stringify(payload)
            });
            if (response && response.success) {
                window.batches.push(response.batch);
            } else {
                alert('Failed to save stock: ' + (response.error || 'Unknown error'));
                return;
            }
        }
    } catch (err) {
        console.error('Failed to save stock batch', err);
        alert('Failed to save stock batch');
        return;
    }

    window.closeModal('addStockModal');
    loadBatchesFromApi(currentInventoryPage);

    // Trigger POS updates if active
    if (typeof renderPOSItems === 'function') renderPOSItems();
    if (typeof renderPOSCatFilters === 'function') renderPOSCatFilters();
    if (typeof renderLowStockBanner === 'function') renderLowStockBanner();
}

// ────────────────────────────────────────────────────────────
// 6. Editing & Restocking stubs
// ────────────────────────────────────────────────────────────
async function editBatch(batchId) {
    const batch = window.batches.find(b => b.id === batchId);
    if (!batch) return;

    window.currentEditingBatchId = batchId;

    // 1. Update title and action buttons
    const titleEl = document.getElementById('addStockModalTitle');
    if (titleEl) titleEl.textContent = 'Edit Stock Batch';

    const saveBtn = document.getElementById('addStockModalBtn');
    if (saveBtn) saveBtn.textContent = 'Update Batch';

    // 2. Pre-fill product and disable change option
    const productSelect = document.getElementById('stockProduct');
    if (productSelect) {
        const product = getProduct(batch.product_id);
        if (product) {
            productSelect.innerHTML = `<option value="${product.id}">${escapeHtml(product.name)}</option>`;
            productSelect.value = product.id;
            productSelect.disabled = true;
        }
    }

    // 3. Populate base values
    const vendorEl = document.getElementById('stockVendor');
    if (vendorEl) vendorEl.value = batch.vendor_name || '';

    const qtyEl = document.getElementById('stockQty');
    if (qtyEl) qtyEl.value = batch.quantity || 0;

    const dateEl = document.getElementById('stockDate');
    if (dateEl && batch.created_at) {
        const dateObj = new Date(batch.created_at);
        if (!isNaN(dateObj.getTime())) {
            dateEl.value = dateObj.toISOString().split('T')[0];
        }
    }

    // 4. Populate wholesale pricing
    const ppEl = document.getElementById('stockPP');
    if (ppEl) ppEl.value = batch.purchase_price || 0;

    const spEl = document.getElementById('stockSP');
    if (spEl) spEl.value = batch.selling_price || 0;

    const profitEl = document.getElementById('stockProfit');
    if (profitEl && batch.selling_price && batch.purchase_price) {
        profitEl.value = (batch.selling_price - batch.purchase_price).toFixed(2);
    }

    // 5. Populate retail pricing
    const retailSPEl = document.getElementById('retailSP');
    if (retailSPEl) retailSPEl.value = batch.retail_price || 0;

    const retailBaseEl = document.getElementById('retailBasePrice');
    if (retailBaseEl && batch.purchase_price && batch.quantity) {
        retailBaseEl.value = (batch.purchase_price / batch.quantity).toFixed(2);
    }

    const retailProfitEl = document.getElementById('retailProfit');
    if (retailProfitEl && batch.retail_price && retailBaseEl.value) {
        retailProfitEl.value = (batch.retail_price - parseFloat(retailBaseEl.value)).toFixed(2);
    }

    // Adjust segment visual configuration
    if (batch.retail_price && batch.retail_price > 0) {
        setPricingMode('retail');
    } else {
        setPricingMode('wholesale');
    }

    // Recalculate margins and GST rates display
    calculateInventoryMath();
    calculateRetailMath();

    window.openModal('addStockModal');
}

async function openRestockForBatch(batchId) {
    const batch = window.batches.find(b => b.id === batchId);
    if (!batch) return;

    const product = getProduct(batch.product_id);
    const unit = product ? (product.unit || 'units') : 'units';
    const maxStock = 150;
    const currentStock = batch.quantity;
    const deficit = Math.max(0, maxStock - currentStock);

    window.currentRestockBatchId = batchId;

    // Populate modal fields
    const curStockEl = document.getElementById('restockCurrentStock');
    if (curStockEl) curStockEl.innerText = `${currentStock} ${unit}`;

    const maxStockEl = document.getElementById('restockMaxStock');
    if (maxStockEl) maxStockEl.innerText = `${maxStock} ${unit}`;

    const deficitEl = document.getElementById('restockDeficit');
    if (deficitEl) deficitEl.innerText = `${deficit} ${unit}`;

    const qtyLabelEl = document.getElementById('restockQtyLabel');
    if (qtyLabelEl) qtyLabelEl.innerHTML = `Order quantity (${unit})<span class="required">*</span>`;

    const unitSuffixEl = document.getElementById('restockUnitSuffix');
    if (unitSuffixEl) unitSuffixEl.innerText = unit;

    const qtyInput = document.getElementById('orderQty');
    if (qtyInput) {
        qtyInput.value = deficit;
        qtyInput.min = 1;
    }

    const helperTextEl = document.getElementById('restockHelperText');
    if (helperTextEl) {
        helperTextEl.innerHTML = `<i class="ti ti-info-circle"></i> Suggested based on your maximum stock limit of ${maxStock} ${unit}. You can adjust this amount before confirming.`;
    }

    window.openModal('modalOverlay');
}

async function confirmRestockOrder() {
    const batchId = window.currentRestockBatchId;
    if (!batchId) return;

    const batch = window.batches.find(b => b.id === batchId);
    if (!batch) return;

    const qtyInput = document.getElementById('orderQty');
    const addQty = parseInt(qtyInput ? qtyInput.value : 0);
    if (isNaN(addQty) || addQty <= 0) {
        alert('Please enter a valid order quantity');
        return;
    }

    const newQty = batch.quantity + addQty;
    try {
        const response = await window.apiRequest(`/api/inventory/batches/${batchId}`, {
            method: 'PUT',
            body: JSON.stringify({ quantity: newQty })
        });
        if (response && response.success) {
            batch.quantity = newQty;
            loadBatchesFromApi(currentInventoryPage);
            if (typeof renderPOSItems === 'function') renderPOSItems();
            window.closeModal('modalOverlay');
        } else {
            alert('Failed to restock: ' + (response.error || 'Unknown error'));
        }
    } catch (err) {
        console.error('Failed to restock batch', err);
        alert('Error restocking batch');
    }
}

// ────────────────────────────────────────────────────────────
// 7. Render Inventory Table & Stats
// ────────────────────────────────────────────────────────────
function renderInventory(stats = {}) {
    let calculatedStockValue = 0;
    let totalBatchesCalculated = 0;
    let lowStockCalculated = 0;

    window.batches.forEach(b => {
        calculatedStockValue += b.quantity * b.purchase_price;
        totalBatchesCalculated++;
        if (b.quantity <= 20) lowStockCalculated++;
    });

    const statsGrid = document.getElementById('inventoryStats');
    if (statsGrid) {
        const currentStockValue = typeof stats.total_stock_value !== 'undefined' ? stats.total_stock_value : calculatedStockValue;
        const totalBatches = typeof stats.total_batches !== 'undefined' ? stats.total_batches : totalBatchesCalculated;
        const lowStockCount = typeof stats.low_stock_count !== 'undefined' ? stats.low_stock_count : lowStockCalculated;
        let stockSoldValue = 0;
        window.sale_items.forEach(item => {
            stockSoldValue += item.qty * item.price;
        });
        statsGrid.innerHTML = `
            <div class="stat-card">
              <div class="stat-label">Current Stock Value</div>
              <div class="stat-value" style="color:var(--info)">${window.formatCurrency(currentStockValue)}</div>
              <div style="font-size:0.75rem; color:var(--muted); margin-top:4px;">Based on purchase cost</div>
            </div>
            <div class="stat-card">
              <div class="stat-label">Stock Sold Value</div>
              <div class="stat-value" style="color:var(--ok)">${window.formatCurrency(stockSoldValue)}</div>
              <div style="font-size:0.75rem; color:var(--muted); margin-top:4px;">Total revenue from sales</div>
            </div>
            <div class="stat-card">
              <div class="stat-label">Total Batches</div>
              <div class="stat-value">${totalBatches}</div>
              <div style="font-size:0.75rem; color:var(--muted); margin-top:4px;">Across all products</div>
            </div>
            <div class="stat-card">
              <div class="stat-label">Low / Out of Stock</div>
              <div class="stat-value" style="color:${lowStockCount > 0 ? 'var(--warn)' : 'var(--ok)'}">${lowStockCount}</div>
              <div style="font-size:0.75rem; color:var(--muted); margin-top:4px;">Batches needing attention</div>
            </div>
        `;
    }
    const tbody = document.querySelector('#inventoryTable tbody');
    if (!tbody) return;
    tbody.innerHTML = '';
    window.batches.forEach(b => {
        const p = getProduct(b.product_id);
        if (!p) return;
        const stockBadge = b.quantity > 20 ? `<span class="badge badge-ok">In Stock</span>` :
            (b.quantity > 0 ? `<span class="badge badge-warn">Low Stock</span>` : `<span class="badge badge-danger">Out of Stock</span>`);
        const gstRate = parseFloat(p.gst_rate || p.gst || 0);
        const gstText = gstRate ? ` <span style="font-size:0.75rem; color:var(--muted)">+${gstRate}% GST</span>` : '';
        let dateStr = '';
        if (b.created_at instanceof Date) {
            dateStr = b.created_at.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
        } else {
            const dateObj = new Date(b.created_at);
            dateStr = isNaN(dateObj.getTime()) ? '' : dateObj.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
        }
        tbody.innerHTML += `
          <tr>
            <td style="font-family: var(--mono); color: var(--muted-strong);">${b.id}</td>
            <td style="color: var(--muted);">${dateStr}</td>
            <td style="font-weight: 500; color: var(--text-strong);">${escapeHtml(p.name)}</td>
            <td>${window.formatCurrency(b.purchase_price)}</td>
            <td>${window.formatCurrency(b.selling_price)}</td>
            <td>${window.formatCurrency(b.retail_price)}</td>
            <td><span style="font-weight:600; color:${b.quantity <= 20 ? 'var(--warn)' : 'inherit'}">${b.quantity}</span></td>
            <td>${stockBadge}</td>
            <td>
              <div style="display:flex; gap:0.5rem;">
                <button class="btn-icon restock-btn" onclick="openRestockForBatch('${b.id}')" title="Restock">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                </button>
                <button class="btn-icon edit-btn" onclick="editBatch('${b.id}')" title="Edit Batch">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </button>
              </div>
            </td>
          </tr>
        `;
    });
}


// ────────────────────────────────────────────────────────────
// 8. Initialise inventory page
// ────────────────────────────────────────────────────────────
async function loadBatchesFromApi(page = 1) {
    currentInventoryPage = page;
    const catFilter = document.getElementById('invCatFilter')?.value || '';
    const subCatFilter = document.getElementById('invSubCatFilter')?.value || '';
    const srch = document.getElementById('invSearch')?.value || '';

    let url = `/api/inventory/batches?page=${page}&limit=5`;
    if (catFilter) url += `&category_id=${catFilter}`;
    if (subCatFilter) url += `&subcategory_id=${subCatFilter}`;
    if (srch) url += `&search=${encodeURIComponent(srch)}`;

    try {
        const data = await window.apiRequest(url);
        if (data && !data.error) {
            window.batches = data.data || [];
            totalInventoryPages = data.pagination.total_pages;
            renderInventory(data.stats || {});
            renderInventoryPaginationControls(data.pagination);
        } else {
            window.batches = [];
            totalInventoryPages = 1;
            renderInventory({});
            renderInventoryPaginationControls({ current_page: 1, total_pages: 1, has_prev: false, has_next: false });
        }
    } catch (err) {
        console.error('Failed to load batches from API', err);
        window.batches = [];
        renderInventory({});
    }
}

function renderInventoryPaginationControls(pagination) {
    const container = document.getElementById('inventoryPaginationControls');
    if (!container) return;

    if (!pagination || pagination.total_pages <= 1) {
        container.style.display = 'none';
        return;
    }

    container.style.display = 'flex';
    container.innerHTML = `
        <button class="pagination-btn" id="prevInvPageBtn" ${!pagination.has_prev ? 'disabled' : ''}>← Previous</button>
        <span class="pagination-info">Page ${pagination.current_page} of ${pagination.total_pages}</span>
        <button class="pagination-btn" id="nextInvPageBtn" ${!pagination.has_next ? 'disabled' : ''}>Next →</button>
    `;

    document.getElementById('prevInvPageBtn')?.addEventListener('click', () => {
        if (pagination.has_prev) loadBatchesFromApi(pagination.current_page - 1);
    });
    document.getElementById('nextInvPageBtn')?.addEventListener('click', () => {
        if (pagination.has_next) loadBatchesFromApi(pagination.current_page + 1);
    });
}

let inventorySearchTimeout = null;
function handleSearchInput() {
    clearTimeout(inventorySearchTimeout);
    inventorySearchTimeout = setTimeout(() => {
        loadBatchesFromApi(1);
    }, 300);
}



async function initInventory() {
    await loadCategoriesForInventory();

    const initialCategory = document.getElementById('invCatFilter')?.value;
    if (initialCategory) {
        await loadSubcategoriesForCategory(initialCategory, 'invSubCatFilter');
    }

    // Load all products by default
    await loadProductsByCategory('all');

    // Load initial batches from the API database
    await loadBatchesFromApi();
}

// Load subcategories for a given category and populate a dropdown
async function loadSubcategoriesForCategory(categoryId, targetSelectId) {
    const select = document.getElementById(targetSelectId);
    if (!select) return;
    if (!categoryId) {
        select.innerHTML = '<option value="">All Subcategories</option>';
        return;
    }
    try {
        const data = await window.apiRequest(`/api/subcategories?category_id=${categoryId}`);
        if (data && !data.error) {
            select.innerHTML = '<option value="">All Subcategories</option>';
            data.forEach(sub => {
                select.innerHTML += `<option value="${sub.id}">${escapeHtml(sub.name)}</option>`;
            });
        }
    } catch (err) {
        console.error('Failed to load subcategories', err);
    }
}

// When category filter changes, update subcategory filter and reload batches
document.getElementById('invCatFilter')?.addEventListener('change', async (e) => {
    const categoryId = e.target.value;
    await loadSubcategoriesForCategory(categoryId, 'invSubCatFilter');
    loadBatchesFromApi(1);
});

// When subcategory filter changes, reload batches
document.getElementById('invSubCatFilter')?.addEventListener('change', () => {
    loadBatchesFromApi(1);
});

// Attach functions to window object
window.setPricingMode = setPricingMode;
window.calculateInventoryMath = calculateInventoryMath;
window.calculateRetailMath = calculateRetailMath;
window.saveStock = saveStock;
window.editBatch = editBatch;
window.openRestockForBatch = openRestockForBatch;
window.confirmRestockOrder = confirmRestockOrder;
window.renderInventory = renderInventory;
window.loadBatchesFromApi = loadBatchesFromApi;
window.handleSearchInput = handleSearchInput;
window.renderInventoryPaginationControls = renderInventoryPaginationControls;


// Run when inventory section becomes visible
if (document.getElementById('inventory') && document.getElementById('inventory').classList.contains('active')) {
    initInventory();
} else {
    window.initInventory = initInventory;
}