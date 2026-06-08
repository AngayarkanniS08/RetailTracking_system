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
async function loadProductsForAddStockModal(selectedProductId = null) {
    const categoryId = document.getElementById('invCatFilter')?.value || '';
    const subcategoryId = document.getElementById('invSubCatFilter')?.value || '';
    const productSelect = document.getElementById('stockProduct');
    if (!productSelect) return;

    productSelect.innerHTML = '<option value="">-- Select Product --</option>';

    let url = '/api/products?limit=100';
    if (!selectedProductId) {
        if (categoryId) url += `&category_id=${categoryId}`;
        if (subcategoryId) url += `&subcategory_id=${subcategoryId}`;
    }

    try {
        const data = await window.apiRequest(url);
        const productList = Array.isArray(data) ? data : (data && Array.isArray(data.data) ? data.data : []);
        if (productList) {
            productList.forEach(prod => {
                // Cache this product so getProduct(pid) retrieves it correctly for GST/calculations
                window.inventoryProductsCache[prod.id] = prod;
                const selectedAttr = (selectedProductId && prod.id === selectedProductId) ? ' selected' : '';
                productSelect.innerHTML += `<option value="${prod.id}"${selectedAttr}>${escapeHtml(prod.name)}</option>`;
            });
            if (selectedProductId) {
                productSelect.value = selectedProductId;
                if (typeof calculateInventoryMath === 'function') {
                    calculateInventoryMath();
                }
            }
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
            const preselectedId = window.pendingRestockProductId || null;
            window.pendingRestockProductId = null; // Clear it
            loadProductsForAddStockModal(preselectedId);
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

    if (typeof fetchAndRenderDbAlerts === 'function') fetchAndRenderDbAlerts();

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
            if (typeof fetchAndRenderDbAlerts === 'function') fetchAndRenderDbAlerts();
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
    // 1. Calculate Sales Revenue & Costs
    let stockSoldValue = 0;
    let stockSoldCost = 0;

    window.sale_items.forEach(item => {
        stockSoldValue += item.qty * item.price;

        // Attempt to find the purchase cost to calculate net margins
        let pCost = 0;
        if (item.batch_id) {
            const batch = window.batches.find(b => b.id === item.batch_id);
            pCost = batch ? batch.purchase_price : 0;
        }
        if (pCost === 0 && item.product_id) {
            const productBatch = window.batches.find(b => b.product_id === item.product_id);
            pCost = productBatch ? productBatch.purchase_price : 0;
        }
        stockSoldCost += item.qty * pCost;
    });

    // 2. Compute Profit Amount
    const profitAmount = stockSoldValue - stockSoldCost;


    window.batches.forEach(b => {
        calculatedStockValue += b.quantity * b.purchase_price;
        totalBatchesCalculated++;
        if (b.quantity <= 20) lowStockCalculated++;
    });

    const statsGrid = document.getElementById('inventoryStats');
    if (statsGrid) {
        const currentStockValue = typeof stats.total_stock_value !== 'undefined' ? stats.total_stock_value : calculatedStockValue;
        const lowStockCount = typeof stats.low_stock_count !== 'undefined' ? stats.low_stock_count : lowStockCalculated;

        statsGrid.innerHTML = `
            <!-- Card 1: Current Stock Value -->
            <div class="stat-card">
              <div class="stat-label">Current Stock Value</div>
              <div class="stat-value" style="color:var(--info)">${window.formatCurrency(currentStockValue)}</div>
              <div style="font-size:0.75rem; color:var(--muted); margin-top:4px;">Based on purchase cost</div>
            </div>

            <!-- Card 2: Stock Sold Value -->
            <div class="stat-card">
              <div class="stat-label">Stock Sold Value</div>
              <div class="stat-value" style="color:var(--ok)">${window.formatCurrency(stockSoldValue)}</div>
              <div style="font-size:0.75rem; color:var(--muted); margin-top:4px;">Total revenue from sales</div>
            </div>

            <!-- Card 3: Profit Amount -->
            <div class="stat-card">
              <div class="stat-label">Profit Amount</div>
              <div class="stat-value" style="color:var(--accent-2)">${window.formatCurrency(profitAmount)}</div>
              <div style="font-size:0.75rem; color:var(--muted); margin-top:4px;">Total profit from sales</div>
            </div>

            <!-- Card 4: Low Stock Alert -->
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
            <td style="color: var(--muted-strong);">${escapeHtml(b.vendor_name || '')}</td>
            <td style="font-weight: 500; color: var(--text-strong);">${escapeHtml(p.name)}</td>
            <td>${window.formatCurrency(b.purchase_price)}</td>
            <td>
              <div style="font-weight:600; color:var(--accent);">W: ${window.formatCurrency(b.selling_price)}</div>
              <div style="font-size:0.8rem; color:var(--warn);">R: ${window.formatCurrency(b.retail_price || (b.selling_price / b.quantity))}</div>
              ${gstText}
            </td>
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

/**
 * Fetches active low-stock alerts from the database and updates the UI badge/banner.
 * Active = products where current stock <= configured ROP, regardless of alert_triggered.
 */
async function fetchAndRenderDbAlerts() {
    try {
        const response = await window.apiRequest('/api/inventory/alerts');
        const badge = document.getElementById('topbarAlertBadge');
        const banner = document.getElementById('globalLowStockBanner');
        const bannerMsg = document.getElementById('globalLowStockBannerMessage');

        if (response && response.success && Array.isArray(response.data)) {
            const activeAlerts = response.data;
            const belowCount = activeAlerts.length;

            if (belowCount > 0) {
                if (badge) {
                    badge.style.display = 'block';
                }
                if (banner) {
                    if (window.globalLowStockBannerDismissed) {
                        banner.style.display = 'none';
                    } else {
                        banner.style.display = 'flex';
                        if (bannerMsg) {
                            const productNames = activeAlerts.map(
                                a => `${a.product_name} (Stock: ${a.current_stock} / ROP: ${a.rop})`
                            );
                            bannerMsg.innerHTML =
                                `<strong>Low Stock Alert:</strong> ${productNames.join(', ')}`;
                        }
                    }
                }
            } else {
                if (badge) badge.style.display = 'none';
                if (banner) banner.style.display = 'none';
                // Reset dismissed flag when alerts are cleared so it can pop up next time new alerts trigger
                window.globalLowStockBannerDismissed = false;
            }
        }
    } catch (err) {
        console.error('Failed to poll stock alerts', err);
    }
}

function closeGlobalLowStockBanner() {
    window.globalLowStockBannerDismissed = true;
    const banner = document.getElementById('globalLowStockBanner');
    if (banner) {
        banner.style.display = 'none';
    }
}
window.closeGlobalLowStockBanner = closeGlobalLowStockBanner;

// FIX: pause polling when the tab is hidden to avoid unnecessary server load
// from long-lived background tabs left open overnight.
let pollingInterval = null;

function startPolling() {
    if (pollingInterval) return;
    pollingInterval = setInterval(fetchAndRenderDbAlerts, 60000);
}

function stopPolling() {
    clearInterval(pollingInterval);
    pollingInterval = null;
}

document.addEventListener('visibilitychange', () => {
    if (!document.getElementById('dashboardView')) return;
    if (document.visibilityState === 'visible') {
        fetchAndRenderDbAlerts(); // immediate refresh on tab focus
        startPolling();
    } else {
        stopPolling();
    }
});

// Initialize on document ready
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('dashboardView')) {
        fetchAndRenderDbAlerts();
        startPolling();
    }
});

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

// ────────────────────────────────────────────────────────────
// 10. Low Stock Alerts Modal & Calculation Handlers
// ────────────────────────────────────────────────────────────

// Populates the product select dropdown in the Alert Modal using the same API that products loading in the inventorypage uses
async function populateAlertProductSelect() {
    const selectEl = document.getElementById('alertProductSelect');
    if (!selectEl) return;

    selectEl.innerHTML = '<option value="">-- Select Product --</option>';

    // Use the same category & subcategory filters currently set in the inventory page
    const categoryId = document.getElementById('invCatFilter')?.value || '';
    const subcategoryId = document.getElementById('invSubCatFilter')?.value || '';

    let url = '/api/products?limit=100';
    if (categoryId) url += `&category_id=${categoryId}`;
    if (subcategoryId) url += `&subcategory_id=${subcategoryId}`;

    try {
        const data = await window.apiRequest(url);
        const productsList = Array.isArray(data) ? data : (data && Array.isArray(data.data) ? data.data : []);

        if (productsList) {
            productsList.forEach(p => {
                // Cache this product so getProduct(pid) or handleAlertProductChange retrieves it correctly
                window.inventoryProductsCache[p.id] = p;

                const hasAlert = p.rop && parseInt(p.rop) > 0;
                const statusSuffix = hasAlert ? ` (Alert set: ${p.rop} ${escapeHtml(p.unit)})` : '';
                selectEl.innerHTML += `<option value="${p.id}">${escapeHtml(p.name)}${statusSuffix}</option>`;
            });
        }
    } catch (err) {
        console.error('Error populating alert product select', err);
    }
    await renderExistingAlerts();
}

// Renders the list of active/configured alerts in the database
async function renderExistingAlerts() {
    const container = document.getElementById('existingAlertsList');
    if (!container) return;

    try {
        const response = await window.apiRequest('/api/inventory/alerts');
        if (response && response.success && Array.isArray(response.data)) {
            const alerts = response.data;
            if (alerts.length === 0) {
                container.innerHTML = '<div style="font-size:0.8rem; color:var(--muted); padding:8px 0;">No active low stock alerts.</div>';
                return;
            }

            let html = '<div style="font-size:0.8rem; font-weight:600; color:var(--text-strong); margin-bottom:6px;">Current Low Stock Products:</div>';
            alerts.forEach(a => {
                html += `
                    <div style="display:flex; justify-content:space-between; align-items:center; padding:6px 10px; background:var(--bg-elevated); border-radius:var(--radius-sm); margin-bottom:4px; font-size:0.82rem;">
                        <span><strong>${escapeHtml(a.product_name)}</strong> — Stock: <span style="color:var(--danger); font-weight:600;">${a.current_stock}</span> / ROP: ${a.rop} ${escapeHtml(a.unit)}</span>
                        <button class="btn btn-sm" style="padding:2px 8px; font-size:0.7rem; color:var(--danger); border-color:var(--danger); background:transparent;" onclick="disableLowStockAlert('${a.product_id}')">Disable</button>
                    </div>
                `;
            });
            container.innerHTML = html;
        } else {
            container.innerHTML = '<div style="font-size:0.8rem; color:var(--muted); padding:8px 0;">No active low stock alerts.</div>';
        }
    } catch (err) {
        console.error('Error rendering existing alerts', err);
        container.innerHTML = '';
    }
}

// Disables/soft-resets alert parameters for a product in the database
async function disableLowStockAlert(productId) {
    if (!confirm('Are you sure you want to disable alerts for this product?')) {
        return;
    }
    try {
        const response = await window.apiRequest(`/api/inventory/alerts/${productId}/disable`, {
            method: 'PATCH'
        });
        if (response && response.success) {
            if (window.inventoryProductsCache[productId]) {
                window.inventoryProductsCache[productId].daily_sales = 0;
                window.inventoryProductsCache[productId].lead_time = 0;
                window.inventoryProductsCache[productId].emergency_stock = 0;
                window.inventoryProductsCache[productId].rop = 0;
                window.inventoryProductsCache[productId].alert_triggered = false;
            }
            alert('Alert disabled successfully');
            await populateAlertProductSelect();
            if (typeof fetchAndRenderDbAlerts === 'function') {
                await fetchAndRenderDbAlerts();
            }
        } else {
            alert('Failed to disable alert: ' + (response?.error || 'Unknown error'));
        }
    } catch (err) {
        console.error('Failed to disable alert', err);
        alert('Error disabling alert: ' + err.message);
    }
}

// Helper to open modal, populate products using the active filters, and reset inputs
async function openLowStockAlertModal() {
    await populateAlertProductSelect();

    const selectEl = document.getElementById('alertProductSelect');
    if (selectEl && !selectEl.dataset.listenerAttached) {
        selectEl.addEventListener('change', handleAlertProductChange);
        selectEl.dataset.listenerAttached = 'true';
    }

    const leadTimeInput = document.getElementById('alertLeadTime');
    const dailySaleInput = document.getElementById('alertDailySale');
    const emergencyStockInput = document.getElementById('alertEmergencyStock');
    const thresholdInput = document.getElementById('alertThreshold');

    if (leadTimeInput) leadTimeInput.value = '';
    if (dailySaleInput) dailySaleInput.value = '';
    if (emergencyStockInput) emergencyStockInput.value = '';
    if (thresholdInput) thresholdInput.value = '';

    openModal('lowStockAlertModal');
}

// Handles dropdown selection changes to load existing parameters of a product
function handleAlertProductChange() {
    const selectEl = document.getElementById('alertProductSelect');
    if (!selectEl) return;

    const productId = selectEl.value;
    const leadTimeInput = document.getElementById('alertLeadTime');
    const dailySaleInput = document.getElementById('alertDailySale');
    const emergencyStockInput = document.getElementById('alertEmergencyStock');
    const thresholdInput = document.getElementById('alertThreshold');

    if (!leadTimeInput || !dailySaleInput || !emergencyStockInput || !thresholdInput) return;

    if (!productId) {
        leadTimeInput.value = '';
        dailySaleInput.value = '';
        emergencyStockInput.value = '';
        thresholdInput.value = '';
        return;
    }

    const prod = window.inventoryProductsCache[productId];
    if (prod) {
        leadTimeInput.value = prod.lead_time !== undefined && prod.lead_time !== null && prod.lead_time > 0 ? prod.lead_time : '';
        dailySaleInput.value = prod.daily_sales !== undefined && prod.daily_sales !== null && prod.daily_sales > 0 ? prod.daily_sales : '';
        emergencyStockInput.value = prod.emergency_stock !== undefined && prod.emergency_stock !== null && prod.emergency_stock > 0 ? prod.emergency_stock : '';
        thresholdInput.value = prod.rop !== undefined && prod.rop !== null && prod.rop > 0 ? prod.rop : '';
    } else {
        leadTimeInput.value = '';
        dailySaleInput.value = '';
        emergencyStockInput.value = '';
        thresholdInput.value = '';
    }
}

// Dynamic Client Calculation: ROP = [lead time * dailysaleqty] + emergency stock
function calculateReorderPoint() {
    const leadTimeInput = document.getElementById('alertLeadTime');
    const dailySaleInput = document.getElementById('alertDailySale');
    const emergencyStockInput = document.getElementById('alertEmergencyStock');
    const thresholdInput = document.getElementById('alertThreshold');

    if (!leadTimeInput || !dailySaleInput || !emergencyStockInput || !thresholdInput) return;

    const leadTime = parseFloat(leadTimeInput.value) || 0;
    const dailySale = parseFloat(dailySaleInput.value) || 0;
    const emergencyStock = parseFloat(emergencyStockInput.value) || 0;

    const rop = Math.ceil((leadTime * dailySale) + emergencyStock);
    thresholdInput.value = rop > 0 ? rop : '';
}

// Saves reorder point settings to the backend database
async function saveLowStockAlert() {
    const selectEl = document.getElementById('alertProductSelect');
    if (!selectEl) return;

    const productId = selectEl.value;
    if (!productId) {
        alert('Please select a product');
        return;
    }

    const leadTime = parseInt(document.getElementById('alertLeadTime')?.value || '0', 10);
    const dailySales = parseInt(document.getElementById('alertDailySale')?.value || '0', 10);
    const emergencyStock = parseInt(document.getElementById('alertEmergencyStock')?.value || '0', 10);
    const threshold = parseInt(document.getElementById('alertThreshold')?.value || '0', 10);

    if (isNaN(threshold) || threshold <= 0) {
        alert('Reorder point must be greater than 0');
        return;
    }

    try {
        const response = await window.apiRequest('/api/inventory/alerts', {
            method: 'POST',
            body: JSON.stringify({
                product_id: productId,
                daily_sales: dailySales,
                lead_time: leadTime,
                emergency_stock: emergencyStock
            })
        });

        if (response && response.success) {
            // Update local cache
            if (window.inventoryProductsCache[productId]) {
                window.inventoryProductsCache[productId].daily_sales = dailySales;
                window.inventoryProductsCache[productId].lead_time = leadTime;
                window.inventoryProductsCache[productId].emergency_stock = emergencyStock;
                window.inventoryProductsCache[productId].rop = threshold;
            }
            alert('Reorder point alert saved successfully');
            closeModal('lowStockAlertModal');
            if (typeof fetchAndRenderDbAlerts === 'function') {
                await fetchAndRenderDbAlerts();
            }
        } else {
            alert('Failed to save reorder point: ' + (response?.error || 'Unknown error'));
        }
    } catch (err) {
        console.error('Failed to save alert', err);
        alert('Error saving alert: ' + err.message);
    }
}

// Opens the active low stock alerts modal and fetches the list of currently triggered products
async function openActiveAlertsModal() {
    const listEl = document.getElementById('activeAlertsModalList');
    if (!listEl) return;

    listEl.innerHTML = '<div style="text-align: center; padding: 15px; color: var(--muted);">Loading alerts...</div>';

    openModal('activeAlertsModal');

    try {
        const response = await window.apiRequest('/api/inventory/alerts');
        if (response && response.success && Array.isArray(response.data)) {
            const alerts = response.data;
            if (alerts.length === 0) {
                listEl.innerHTML = '<div style="text-align: center; padding: 20px; color: var(--muted); font-size: 0.9rem;">🎉 All products are sufficiently stocked.</div>';
                return;
            }

            let html = '';
            alerts.forEach(a => {
                html += `
                    <div style="padding: 12px; background: var(--bg-100); border-radius: var(--radius-md); border-left: 4px solid var(--danger); margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center;">
                        <div style="flex: 1; padding-right: 12px;">
                            <div style="font-weight: 600; color: var(--text-strong); font-size: 0.92rem;">${escapeHtml(a.product_name)}</div>
                            <div style="font-size: 0.8rem; color: var(--muted); margin-top: 2px;">
                                Current Stock: <span style="color: var(--danger); font-weight: 600;">${a.current_stock}</span> / ROP: ${a.rop} ${escapeHtml(a.unit)}
                            </div>
                        </div>
                        <button class="btn btn-sm" style="padding: 4px 10px; font-size: 0.75rem; border-color: var(--border);" onclick="closeModal('activeAlertsModal'); openRestockForProduct('${a.product_id}')">Restock</button>
                    </div>
                `;
            });
            listEl.innerHTML = html;
        } else {
            listEl.innerHTML = '<div style="text-align: center; padding: 20px; color: var(--danger); font-size: 0.9rem;">Failed to load alerts.</div>';
        }
    } catch (err) {
        console.error('Error fetching active alerts for modal', err);
        listEl.innerHTML = '<div style="text-align: center; padding: 20px; color: var(--danger); font-size: 0.9rem;">Error fetching alerts.</div>';
    }
}

// Redirects the user to restock the specific low-stock product
async function openRestockForProduct(productId) {
    try {
        // 1. Ensure products cache has this product
        let product = getProduct(productId);
        if (!product) {
            const prodData = await window.apiRequest('/api/products?limit=1000');
            const prodList = Array.isArray(prodData) ? prodData : (prodData && Array.isArray(prodData.data) ? prodData.data : []);
            if (prodList) {
                prodList.forEach(p => {
                    window.inventoryProductsCache[p.id] = p;
                });
            }
            product = getProduct(productId);
        }

        // 2. Fetch or find the batches for this product
        let productBatches = [];
        if (Array.isArray(window.batches) && window.batches.length > 0) {
            productBatches = window.batches.filter(b => b.product_id === productId);
        }

        if (productBatches.length === 0) {
            const batchData = await window.apiRequest('/api/inventory/batches?limit=1000');
            const batchList = Array.isArray(batchData) ? batchData : (batchData && Array.isArray(batchData.data) ? batchData.data : []);
            if (batchList) {
                productBatches = batchList.filter(b => b.product_id === productId);
            }
        }

        if (productBatches.length > 0) {
            // Sort to find the batch with the lowest remaining quantity (quantity)
            productBatches.sort((a, b) => a.quantity - b.quantity);
            const targetBatch = productBatches[0];

            // Ensure the chosen batch is in window.batches so openRestockForBatch can find it
            if (!Array.isArray(window.batches)) {
                window.batches = [];
            }
            if (!window.batches.some(b => b.id === targetBatch.id)) {
                window.batches.push(targetBatch);
            }

            // Open the restock modal (modalOverlay) for this batch
            openRestockForBatch(targetBatch.id);
        } else {
            // Fallback: If no batch exists, open the addStockModal so they can add a new batch
            window.pendingRestockProductId = productId;
            openModal('addStockModal');
        }
    } catch (err) {
        console.error('Error opening restock modal for product:', err);
        // Fallback: open addStockModal
        window.pendingRestockProductId = productId;
        openModal('addStockModal');
    }
}

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
window.openLowStockAlertModal = openLowStockAlertModal;
window.calculateReorderPoint = calculateReorderPoint;
window.saveLowStockAlert = saveLowStockAlert;
window.disableLowStockAlert = disableLowStockAlert;
window.handleAlertProductChange = handleAlertProductChange;
window.populateAlertProductSelect = populateAlertProductSelect;
window.renderExistingAlerts = renderExistingAlerts;
window.fetchAndRenderDbAlerts = fetchAndRenderDbAlerts;
window.openActiveAlertsModal = openActiveAlertsModal;
window.openRestockForProduct = openRestockForProduct;
// Run when inventory section becomes visible
if (document.getElementById('inventory') && document.getElementById('inventory').classList.contains('active')) {
    initInventory();
} else {
    window.initInventory = initInventory;
}