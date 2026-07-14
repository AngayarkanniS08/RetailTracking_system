/* billing.js — POS Billing Module */

function escHtml(str) {
    const div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
}

window.posProducts = [];
window.posBatches = [];
window.posCategories = [];
window.posCategoryMap = {};
window.activePosCategory = '';
window.cart = [];
window.priceMode = 'wholesale';
/* Cache of search results for instant row filling (batches not yet in posBatches) */
window._posSearchCache = {};

function todayStr() {
    const d = new Date();
    return String(d.getDate()).padStart(2,'0') + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + d.getFullYear();
}

/* Search state for paginated API search */
window._posSearchState = { term: '', page: 1, totalPages: 1 };

/* Search autocomplete: Valkey-cached API with pagination */
async function onPOSSearchKeyup(e) {
    const input = document.getElementById('posSearch');
    const dropdown = document.getElementById('posSearchDropdown');
    if (!input || !dropdown) return;
    const term = input.value.trim().toLowerCase();

    if (!term) { dropdown.style.display = 'none'; return; }

    // Reset page on new term
    if (term !== _posSearchState.term) {
        _posSearchState.term = term;
        _posSearchState.page = 1;
    }

    try {
        const res = await window.apiRequest(`/api/pos/search?q=${encodeURIComponent(term)}&page=${_posSearchState.page}&limit=8`);
        const matches = Array.isArray(res) ? res : (res.data || []);
        const pagination = res.pagination || {};

        if (!matches.length) { dropdown.style.display = 'none'; return; }

        _posSearchState.totalPages = pagination.total_pages || 1;

        // Cache search results for instant grid filling (even if not in posBatches)
        matches.forEach(m => { _posSearchCache[m.batch_id] = m; });

        let html = '';
        matches.forEach(m => {
            const sp = parseFloat(m.selling_price) || 0;
            const rp = parseFloat(m.retail_price) || sp * 1.2;
            html += `<div class="psd-item" onclick="selectPOSProduct('${m.batch_id}')">
                <div>
                    <div class="psd-name">${m.product_name}</div>
                    <div class="psd-meta">${m.batch_number || m.batch_id} | ${m.vendor_name || '-'}</div>
                </div>
                <div class="psd-price">
                    <span style="color:var(--accent);font-weight:600;">W: ₹${sp.toFixed(2)}</span>
                    <span style="color:var(--warn);font-weight:600;"> R: ₹${rp.toFixed(2)}</span>
                </div>
            </div>`;
        });

        // Add pagination bar
        const start = (pagination.total > 0) ? ((pagination.current_page - 1) * 8 + 1) : 0;
        const end = Math.min(pagination.current_page * 8, pagination.total);
        html += `<div class="psd-pagination">
            <span>${start}-${end} of ${pagination.total}</span>
            <span>
                ${pagination.has_prev ? `<button class="psd-page-btn" onclick="event.stopPropagation();_posSearchState.page--;onPOSSearchKeyup({key:''})">◀ Prev</button>` : ''}
                ${pagination.has_next ? `<button class="psd-page-btn" onclick="event.stopPropagation();_posSearchState.page++;onPOSSearchKeyup({key:''})">Next ▶</button>` : ''}
            </span>
        </div>`;

        dropdown.innerHTML = html;
        dropdown.style.display = 'block';

        if (e.key === 'Enter' || e.key === 'Tab') {
            e.preventDefault();
            const firstItem = dropdown.querySelector('.psd-item');
            if (firstItem) firstItem.click();
        }
    } catch (err) {
        console.error('POS search error:', err);
        dropdown.style.display = 'none';
    }
}

/* Select a product from search → fill first empty row in billing grid */
function selectPOSProduct(batchId) {
    // Try search cache first (handles newly added batches)
    let cached = _posSearchCache[batchId];
    let batch = posBatches.find(b => b.id === batchId);
    let product = posProducts.find(p => batch && p.id === batch.product_id);

    // If not in global arrays but in search cache, add them dynamically
    if (!batch && cached) {
        batch = {
            id: cached.batch_id,
            batch_number: cached.batch_number,
            product_id: cached.product_id,
            quantity: parseInt(cached.quantity) || 0,
            selling_price: cached.selling_price,
            retail_price: cached.retail_price,
            vendor_name: cached.vendor_name || ''
        };
        posBatches.push(batch);

        product = posProducts.find(p => p.id === cached.product_id);
        if (!product) {
            product = {
                id: cached.product_id,
                name: cached.product_name,
                unit: cached.unit || 'Nos',
                gst_rate: parseFloat(cached.gst_rate) || 0,
                hsn_code: cached.hsn_code || ''
            };
            posProducts.push(product);
        }
    }

    if (!batch || !product) return;

    const tbody = document.querySelector('#billingGrid tbody');
    if (!tbody) return;

    let targetRow = null;
    const rows = tbody.querySelectorAll('tr');
    for (let i = 0; i < rows.length; i++) {
        const cells = rows[i].querySelectorAll('td');
        let isEmpty = true;
        for (let j = 0; j < cells.length; j++) {
            if (cells[j].textContent.trim() !== '') { isEmpty = false; break; }
        }
        if (isEmpty) { targetRow = rows[i]; break; }
    }
    if (!targetRow) return;

    const cells = targetRow.querySelectorAll('td');
    const qty = 1;
    const price = priceMode === 'wholesale'
        ? (parseFloat(batch.selling_price) || 0)
        : (parseFloat(batch.retail_price) || parseFloat(batch.selling_price) * 1.2 || 0);
    const amount = qty * price;
    const tax = parseFloat(product.gst_rate) || 0;

    targetRow.setAttribute('data-batch-id', batchId);
    cells[0].textContent = batch.batch_number || batch.id;    // Batch No
    cells[1].textContent = product.name;                      // Particulars
    cells[2].innerHTML = `<span class="row-price-val">${price.toFixed(2)}</span> <span class="row-price-mode">${priceMode === 'wholesale' ? 'W' : 'R'}</span>`;
    cells[3].textContent = '0.00';                            // Discount
    cells[4].textContent = product.unit || 'Nos';             // Unit
    cells[5].textContent = qty.toFixed(2);                    // Qty
    cells[6].textContent = tax.toFixed(1);                    // GST (%)
    cells[7].textContent = amount.toFixed(2);                 // Amount

    const existing = cart.find(c => c.batchId === batchId);
    if (existing) {
        const stock = batch.quantity || batch.remaining_qty || 0;
        if (existing.qty < stock) existing.qty++;
        existing.sellingPrice = price;
    } else {
        cart.push({
            batchId: batch.id,
            productId: product.id,
            name: product.name,
            unit: product.unit || 'pcs',
            qty: qty,
            sellingPrice: price,
            gstRate: tax,
            discount: 0
        });
    }
    calculateCart();

    rows.forEach(r => r.classList.remove('bg-active'));
    targetRow.classList.add('bg-active');
    cells[0].classList.add('cell-active');

    // Clear search & refocus search bar
    const searchInput = document.getElementById('posSearch');
    if (searchInput) { searchInput.value = ''; searchInput.focus(); }
    const dd = document.getElementById('posSearchDropdown');
    if (dd) { dd.style.display = 'none'; }
}

/* Close search dropdown on outside click */
document.addEventListener('click', function(e) {
    const dd = document.getElementById('posSearchDropdown');
    if (dd && !e.target.closest('.pos-search-area')) dd.style.display = 'none';
});

/* Toggle between wholesale / retail price mode */
function togglePriceMode(force) {
    if (force && (force === 'wholesale' || force === 'retail')) {
        if (priceMode === force) return;
        priceMode = force;
    } else {
        priceMode = priceMode === 'wholesale' ? 'retail' : 'wholesale';
    }
    const badge = document.getElementById('priceModeLabel');
    if (badge) {
        badge.textContent = priceMode === 'wholesale' ? 'W' : 'R';
        badge.classList.toggle('mode-retail', priceMode === 'retail');
    }

    const rows = document.querySelectorAll('#billingGrid tbody tr');
    let changed = false;
    rows.forEach(function(tr) {
        const bid = tr.getAttribute('data-batch-id');
        if (!bid) return;
        const batch = posBatches.find(b => b.id === bid);
        if (!batch) return;
        const cells = tr.querySelectorAll('td');
        const qty = parseFloat(cells[5].textContent) || 0;
        if (qty === 0) return;

        const newPrice = priceMode === 'wholesale'
            ? (parseFloat(batch.selling_price) || 0)
            : (parseFloat(batch.retail_price) || parseFloat(batch.selling_price) * 1.2 || 0);
        const newAmount = qty * newPrice;

        cells[2].textContent = newPrice.toFixed(2);
        cells[7].textContent = newAmount.toFixed(2);

        const item = cart.find(function(c) { return c.batchId === bid; });
        if (item) {
            item.sellingPrice = newPrice;
        }
        changed = true;
    });

    if (changed) calculateCart();
}

/* Toggle price mode for a single row */
function toggleRowPriceMode(tr, mode) {
    const bid = tr.getAttribute('data-batch-id');
    if (!bid) return;
    const batch = posBatches.find(b => b.id === bid);
    if (!batch) return;
    const cells = tr.querySelectorAll('td');
    const qty = parseFloat(cells[5].textContent) || 0;
    if (qty === 0) return;

    const newPrice = mode === 'wholesale'
        ? (parseFloat(batch.selling_price) || 0)
        : (parseFloat(batch.retail_price) || parseFloat(batch.selling_price) * 1.2 || 0);
    const newAmount = qty * newPrice;

    cells[2].innerHTML = `<span class="row-price-val">${newPrice.toFixed(2)}</span> <span class="row-price-mode">${mode === 'wholesale' ? 'W' : 'R'}</span>`;
    cells[7].textContent = newAmount.toFixed(2);

    const item = cart.find(function(c) { return c.batchId === bid; });
    if (item) {
        item.sellingPrice = newPrice;
    }
    calculateCart();
}

/* Keyboard shortcut: w/r toggles active row between wholesale and retail price */
document.addEventListener('keydown', function(e) {
    const key = (e.key || '').toLowerCase();
    if (key !== 'w' && key !== 'r') return;
    const td = e.target.closest('td');
    if (!td) return;
    const tr = td.closest('tr');
    if (!tr) return;
    if (tr.closest('#billingGrid') && Array.from(tr.cells).indexOf(td) === 2) {
        e.preventDefault();
        toggleRowPriceMode(tr, key === 'w' ? 'wholesale' : 'retail');
    }
});

/* Grid cell focus: highlight active row/cell */
(function initBillingGrid() {
    const grid = document.getElementById('billingGrid');
    if (!grid) return;

    grid.addEventListener('focusin', function(e) {
        const td = e.target.closest('td');
        if (!td) return;
        const tr = td.closest('tr');
        if (!tr) return;
        grid.querySelectorAll('tbody tr').forEach(r => r.classList.remove('bg-active'));
        grid.querySelectorAll('.cell-active').forEach(c => c.classList.remove('cell-active'));
        tr.classList.add('bg-active');
        td.classList.add('cell-active');
    });

    grid.addEventListener('keydown', function(e) {
        const td = e.target.closest('td');
        if (!td) return;
        const tr = td.closest('tr');
        if (!tr) return;
        const rows = grid.querySelectorAll('tbody tr');
        const currentRow = Array.from(rows).indexOf(tr);
        const cells = tr.querySelectorAll('td');
        const cellIndex = Array.from(tr.children).indexOf(td);

        if (e.key === 'Enter') {
            const nextRow = tr.nextElementSibling;
            if (nextRow) {
                e.preventDefault();
                const nextCells = nextRow.querySelectorAll('td');
                if (nextCells[cellIndex]) nextCells[cellIndex].focus();
            }
            return;
        }

        // Arrow Left / Right → move columns
        if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
            e.preventDefault();
            const next = e.key === 'ArrowRight' ? cellIndex + 1 : cellIndex - 1;
            if (next >= 0 && next < cells.length) cells[next].focus();
            return;
        }

        // Delete key → remove row with confirmation
        if (e.key === 'Delete') {
            const bid = tr.getAttribute('data-batch-id');
            if (!bid) return; // empty row, let browser default
            e.preventDefault();
            window._deleteTargetRow = tr;
            document.getElementById('deleteRowItemName').textContent = cells[1].textContent || 'this item';
            openModal('deleteRowModal');
            return;
        }

        // Arrow Up / Down
        if (e.key === 'ArrowUp' || e.key === 'ArrowDown') {
            e.preventDefault();
            const isDown = e.key === 'ArrowDown';
            const navDelta = isDown ? 1 : -1;
            const valDelta = isDown ? -1 : 1; // down = decrease, up = increase

            // Qty column (index 5) → increment/decrement qty
            if (cellIndex === 5) {
                const qty = parseFloat(cells[5].textContent) || 0;
                if (qty === 0) return;
                const newQty = Math.max(1, qty + valDelta);
                const bid = tr.getAttribute('data-batch-id');
                const batch = bid && posBatches.find(b => b.id === bid);
                const maxQty = batch ? (batch.quantity || batch.remaining_qty || 0) : Infinity;
                if (newQty > maxQty) return;
                const price = parseFloat(cells[2].textContent) || 0;
                cells[5].textContent = newQty.toFixed(2);
                cells[7].textContent = (newQty * price).toFixed(2);
                const item = cart.find(c => c.batchId === bid);
                if (item) { item.qty = newQty; }
                calculateCart();
                return;
            }

            // Discount column (index 3) → increment/decrement discount
            if (cellIndex === 3) {
                const disc = parseFloat(cells[3].textContent) || 0;
                const newDisc = Math.max(0, disc + valDelta);
                cells[3].textContent = newDisc.toFixed(2);
                const bid = tr.getAttribute('data-batch-id');
                const item = cart.find(c => c.batchId === bid);
                if (item) { item.discount = newDisc; }
                calculateCart();
                return;
            }

            // Other columns → move to same cell in prev/next row
            const targetRow = rows[currentRow + navDelta];
            if (!targetRow) return;
            const targetCells = targetRow.querySelectorAll('td');
            if (targetCells[cellIndex]) targetCells[cellIndex].focus();
        }
    });
})();

/* Track manual edits to amountPaidInput so calculateCart doesn't overwrite it */
document.addEventListener('DOMContentLoaded', function() {
    const paidInput = document.getElementById('amountPaidInput');
    if (paidInput) {
        paidInput.addEventListener('input', function() {
            this.dataset.userEdited = 'true';
        });
    }
});

async function loadPOSData() {
    try {
        const [productsRes, batchesRes, categories] = await Promise.all([
            window.apiRequest('/api/products?limit=1000'),
            window.apiRequest('/api/inventory/batches?limit=1000'),
            window.apiRequest('/api/categories')
        ]);

        posProducts = Array.isArray(productsRes) ? productsRes : (productsRes.data || []);
        posBatches = Array.isArray(batchesRes) ? batchesRes : (batchesRes.data || []);
        posCategories = Array.isArray(categories) ? categories : [];

        posCategoryMap = {};
        posCategories.forEach(c => { posCategoryMap[c.id] = c.name; });

        renderPOSCatFilters();
        renderPOSItems();
        populateCustomerSelect();
        restoreGridFromCart();
    } catch (e) {
        console.error('Failed to load POS data:', e);
    }
}

function renderPOSCatFilters() {
    const container = document.getElementById('posCatFilters');
    if (!container) return;

    const counts = {};
    const countedProducts = new Set();
    posBatches.forEach(b => {
        if (b.quantity <= 0) return;
        const p = posProducts.find(pr => pr.id === b.product_id);
        if (!p || countedProducts.has(p.id)) return;
        countedProducts.add(p.id);
        const catName = posCategoryMap[p.category_id] || 'Uncategorized';
        counts[catName] = (counts[catName] || 0) + 1;
    });

    const allCount = Object.values(counts).reduce((a, b) => a + b, 0);
    let html = `<button class="cat-btn ${activePosCategory === '' ? 'active' : ''}" onclick="setPosCategory('')">All<span class="product-count">${allCount}</span></button>`;

    Object.entries(counts).forEach(([catName, count]) => {
        html += `<button class="cat-btn ${activePosCategory === catName ? 'active' : ''}" onclick="setPosCategory('${catName}')">${catName}<span class="product-count">${count}</span></button>`;
    });

    container.innerHTML = html;
}

function setPosCategory(cat) {
    activePosCategory = cat;
    renderPOSCatFilters();
    renderPOSItems();
}

function renderPOSItems() {
    const term = (document.getElementById('posSearch')?.value || '').toLowerCase();
    const grid = document.getElementById('posItemsGrid');
    if (!grid) return;

    const rows = [];

    posBatches.forEach(b => {
        if (b.quantity <= 0) return;
        const p = posProducts.find(pr => pr.id === b.product_id);
        if (!p) return;

        const catName = posCategoryMap[p.category_id] || '';
        if (activePosCategory && catName !== activePosCategory) return;

        const searchStr = `${p.display_id ? '#' + p.display_id : ''} ${p.name} ${b.batch_number} ${b.vendor_name || ''} ${catName}`.toLowerCase();
        if (term && !searchStr.includes(term)) return;

        const stock = b.quantity;
        const stockColor = stock > 10 ? 'var(--ok)' : (stock > 0 ? 'var(--warn)' : 'var(--danger)');

        rows.push({
            batchId: b.id,
            productId: p.id,
            name: p.name,
            unit: p.unit || 'pcs',
            category: catName,
            vendorName: b.vendor_name || '-',
            sellingPrice: parseFloat(b.selling_price) || 0,
            retailPrice: parseFloat(b.retail_price) || 0,
            purchasePrice: parseFloat(b.purchase_price) || 0,
            stock: stock,
            stockColor: stockColor,
            gstRate: parseFloat(p.gst_rate) || 0,
            hsnCode: p.hsn_code || ''
        });
    });

    if (rows.length === 0) {
        grid.innerHTML = `<div style="text-align:center; padding: 60px 20px; background: var(--bg-card); border-radius: var(--radius-md); border: 1px dashed var(--border);">
          <div style="font-size: 2rem; margin-bottom: 10px;">📦</div>
          <div style="color: var(--text-strong); font-weight: 600;">No products available</div>
          <div style="color: var(--muted); font-size: 0.85rem; margin-top: 4px;">Add inventory batches first or adjust filters</div>
        </div>`;
        return;
    }

    let html = `<table class="pos-table"><thead><tr>
      <th>Product Details</th><th>Category</th><th>Price</th><th>Stock</th><th style="text-align:right">Action</th>
    </tr></thead><tbody>`;

    rows.forEach(r => {
        html += `<tr onclick="addToCart('${r.batchId}')">
          <td>
            <div class="t-name">${r.name}</div>
            <div style="font-size: 0.75rem; color: var(--muted);">Batch: ${r.batchId.slice(0,8)} | ${r.vendorName}</div>
          </td>
          <td><span class="badge" style="font-size: 0.7rem; padding: 2px 8px; background: var(--bg-hover);">${r.category}</span></td>
          <td class="t-price">₹${r.sellingPrice.toFixed(2)}</td>
          <td class="t-stock" style="color: ${r.stockColor}">${r.stock} ${r.unit}</td>
          <td style="text-align:right">
            <button class="btn btn-primary btn-sm" style="padding: 4px 12px; font-size: 0.75rem;">+ Add</button>
          </td>
        </tr>`;
    });

    html += '</tbody></table>';
    grid.innerHTML = html;
}

/* Persist cart to sessionStorage */
function saveCart() {
    try { sessionStorage.setItem('posCart', JSON.stringify(cart)); } catch (e) {}
}

/* Restore grid rows from sessionStorage cart after data loads */
function restoreGridFromCart() {
    const stored = sessionStorage.getItem('posCart');
    if (!stored) return;
    try {
        cart = JSON.parse(stored);
    } catch (e) { cart = []; return; }
    if (!cart.length) return;

    cart.forEach(function(item) {
        const batch = posBatches.find(function(b) { return b.id === item.batchId; });
        const product = posProducts.find(function(p) { return p.id === item.productId; });
        if (!batch || !product) return;

        const tbody = document.querySelector('#billingGrid tbody');
        if (!tbody) return;
        const rows = tbody.querySelectorAll('tr');
        let targetRow = null;
        for (let i = 0; i < rows.length; i++) {
            const cells = rows[i].querySelectorAll('td');
            let empty = true;
            for (let j = 0; j < cells.length; j++) {
                if (cells[j].textContent.trim() !== '') { empty = false; break; }
            }
            if (empty) { targetRow = rows[i]; break; }
        }
        if (!targetRow) return;

        const cells = targetRow.querySelectorAll('td');
        targetRow.setAttribute('data-batch-id', item.batchId);
        cells[0].textContent = batch.batch_number || batch.id;
        cells[1].textContent = product.name;
        cells[2].textContent = (item.sellingPrice || 0).toFixed(2);
        cells[3].textContent = (item.discount || 0).toFixed(2);
        cells[4].textContent = product.unit || 'Nos';
        cells[5].textContent = (item.qty || 1).toFixed(2);
        cells[6].textContent = (item.gstRate || 0).toFixed(1);
        cells[7].textContent = ((item.qty || 1) * (item.sellingPrice || 0)).toFixed(2);
    });
    calculateCart();
}

function addToCart(batchId) {
    const batch = posBatches.find(b => b.id === batchId);
    if (!batch || batch.quantity <= 0) return;

    const product = posProducts.find(p => p.id === batch.product_id);
    if (!product) return;

    const existing = cart.find(c => c.batchId === batchId);
    if (existing) {
        if (existing.qty >= batch.quantity) return;
        existing.qty++;
    } else {
        cart.push({
            batchId: batch.id,
            productId: product.id,
            name: product.name,
            unit: product.unit || 'pcs',
            qty: 1,
            sellingPrice: parseFloat(batch.selling_price) || 0,
            retailPrice: parseFloat(batch.retail_price) || 0,
            purchasePrice: parseFloat(batch.purchase_price) || 0,
            gstRate: parseFloat(product.gst_rate) || 0,
            hsnCode: product.hsn_code || '',
            discount: 0
        });
    }

    renderCart();
}

function renderCart() {
    const container = document.getElementById('cartItemsContainer');
    if (!container) return;

    if (cart.length === 0) {
        container.innerHTML = `<div class="text-center" style="color:var(--muted); margin-top: 50px;">Cart is empty. Select items to bill.</div>`;
        calculateCart();
        return;
    }

    let html = `<table class="pos-table" style="font-size:0.8rem;"><thead><tr>
      <th>Item</th><th>Price</th><th>Qty</th><th>Disc</th><th>Total</th><th></th>
    </tr></thead><tbody>`;

    cart.forEach((c, idx) => {
        const lineTotal = (c.qty * c.sellingPrice) - c.discount;
        html += `<tr>
          <td><div class="t-name" style="font-size:0.75rem;">${c.name}</div></td>
          <td>₹${c.sellingPrice.toFixed(2)}</td>
          <td>
            <button class="btn btn-sm" onclick="updateCartQty(${idx}, -1)" style="padding:1px 6px;">−</button>
            ${c.qty}
            <button class="btn btn-sm" onclick="updateCartQty(${idx}, 1)" style="padding:1px 6px;">+</button>
          </td>
          <td><input type="number" class="input-field" value="${c.discount}" min="0" style="width:55px;padding:2px 4px;font-size:0.75rem;" onchange="setItemDiscount(${idx}, this.value)"></td>
          <td>₹${lineTotal.toFixed(2)}</td>
          <td><button class="btn btn-sm" onclick="cart.splice(${idx},1);renderCart();" style="color:var(--danger);padding:1px 6px;">✕</button></td>
        </tr>`;
    });

    html += '</tbody></table>';
    container.innerHTML = html;
    calculateCart();
}

function updateCartQty(idx, delta) {
    const c = cart[idx];
    if (!c) return;
    const newQty = c.qty + delta;
    if (newQty <= 0) {
        cart.splice(idx, 1);
    } else {
        const batch = posBatches.find(b => b.id === c.batchId);
        if (batch && newQty > batch.quantity) return;
        c.qty = newQty;
    }
    renderCart();
}

function setItemDiscount(idx, val) {
    if (cart[idx]) cart[idx].discount = Math.max(0, parseFloat(val) || 0);
    calculateCart();
}

function calculateCart() {
    const applyGst = document.getElementById('enableGstToggle')?.checked || false;
    const billDiscount = parseFloat(document.getElementById('cartDiscountInput')?.value || 0);

    let subtotal = 0;
    let totalGst = 0;

    cart.forEach(c => {
        const lineSub = c.qty * c.sellingPrice;
        const gstRate = applyGst ? c.gstRate : 0;
        const gstAmt = lineSub * (gstRate / 100);
        subtotal += lineSub;
        totalGst += gstAmt;
    });

    const totalDiscount = billDiscount + cart.reduce((s, c) => s + (c.discount || 0), 0);
    const beforeRound = subtotal - totalDiscount + totalGst;
    const grandTotal = Math.round(beforeRound);
    const roundOff = grandTotal - beforeRound;

    document.getElementById('cartSubtotal').textContent = '₹' + subtotal.toFixed(2);
    document.getElementById('cartGst').textContent = '₹' + totalGst.toFixed(2);
    document.getElementById('cartTotal').textContent = '₹' + grandTotal.toFixed(2);

    const paidInput = document.getElementById('amountPaidInput');
    if (paidInput && !paidInput.dataset.userEdited) {
        paidInput.value = grandTotal.toFixed(2);
    }

    window._posCalc = { subtotal, totalGst, billDiscount, totalDiscount, roundOff, grandTotal, applyGst };
    saveCart();
}

// ── Customer search (typeahead) ──────────────────────────

let _custSearchState = { term: '', page: 1 };

async function onCustomerSearchKeyup(e) {
    const input = document.getElementById('customerSearchInput');
    const dropdown = document.getElementById('customerSearchDropdown');
    const wrap = document.querySelector('.customer-search-combobox');
    if (!input || !dropdown || !wrap) return;

    if (e.key === 'Escape') { dropdown.classList.remove('is-open'); dropdown.innerHTML = ''; return; }

    const term = input.value.trim().toLowerCase();

    if (term !== _custSearchState.term) {
        _custSearchState.term = term;
        _custSearchState.page = 1;
    }

    try {
        const url = term
            ? `/api/customers?search=${encodeURIComponent(term)}&page=${_custSearchState.page}&limit=8`
            : '/api/customers?limit=8';
        const res = await window.apiRequest(url);
        const customers = Array.isArray(res) ? res : (res.data || []);
        if (!customers.length) { dropdown.classList.remove('is-open'); return; }
        const pagination = res.pagination || {};
        renderCustomerDropdown(dropdown, customers, pagination.total || 0, pagination.current_page || 1);
        dropdown.classList.add('is-open');
        wrap.classList.add('is-open');

        if (term && (e.key === 'Enter' || e.key === 'Tab')) {
            e.preventDefault();
            const firstItem = dropdown.querySelector('.cs-item');
            if (firstItem) firstItem.click();
        }
    } catch (_) {
        dropdown.classList.remove('is-open');
    }
}

function renderCustomerDropdown(dropdown, customers, total, page) {
    const limit = 8;
    const start = total > 0 ? (page - 1) * limit + 1 : 0;
    const end = Math.min(page * limit, total);
    const totalPages = Math.ceil(total / limit) || 1;

    let html = '';
    customers.forEach(c => {
        const bal = parseFloat(c.current_balance || c.balance || 0);
        html += `<div class="cs-item" onclick="selectCustomer('${c.id}','${escHtml(c.name)}')">
            <div class="cs-item-main">${escHtml(c.name)}</div>
            <div class="cs-item-sub">${escHtml(c.phone)}${bal > 0 ? ' &middot; Due ₹' + bal.toFixed(2) : ''}</div>
        </div>`;
    });

    if (total > limit) {
        html += `<div class="cs-pagination">
            <span>${start}–${end} of ${total}</span>
            <span>
                ${page > 1 ? `<button class="cs-page-btn" onclick="event.stopPropagation();_custSearchState.page--;onCustomerSearchKeyup({key:''})">◀ Prev</button>` : ''}
                ${page < totalPages ? `<button class="cs-page-btn" onclick="event.stopPropagation();_custSearchState.page++;onCustomerSearchKeyup({key:''})">Next ▶</button>` : ''}
            </span>
        </div>`;
    }

    dropdown.innerHTML = html;
}

function selectCustomer(id, name) {
    document.getElementById('billCustomerId').value = id;
    document.getElementById('customerSearchInput').value = name;
    const dd = document.getElementById('customerSearchDropdown');
    dd.classList.remove('is-open');
    dd.innerHTML = '';
    const wrap = document.querySelector('.customer-search-combobox');
    if (wrap) wrap.classList.remove('is-open');
}

// Close dropdown on outside click
document.addEventListener('click', function(e) {
    const wrap = document.querySelector('.customer-search-combobox');
    if (wrap && !wrap.contains(e.target)) {
        const dd = document.getElementById('customerSearchDropdown');
        if (dd) { dd.classList.remove('is-open'); dd.innerHTML = ''; }
    }
});

// Backward compat: refresh customer search input (used by saveCustomer in credit.js)
async function populateCustomerSelect() {
    // Load latest customers into cache — no-op needed for typeahead
}

async function processCheckout() {
    const amountPaid = parseFloat(document.getElementById('amountPaidInput')?.value || 0);
    const customerId = document.getElementById('billCustomerId')?.value || null;
    const customerName = customerId
        ? null
        : (document.getElementById('customerSearchInput')?.value.trim() || 'Walk-in');
    const calc = window._posCalc;

    if (!cart.length) { alert('Cart is empty'); return; }
    if (amountPaid > calc.grandTotal) { alert('Amount paid is higher than total'); return; }
    if (amountPaid <= 0 && !customerId) { alert('Enter amount paid or select a credit customer'); return; }

    const items = cart.map(c => ({
        product_id: c.productId,
        batch_id: c.batchId,
        quantity: c.qty,
        unit_price: c.sellingPrice,
        discount_amount: c.discount || 0
    }));

    const payload = {
        customer_id: customerId || null,
        customer_name: customerName,
        customer_phone: null,
        apply_gst: calc.applyGst,
        discount_amount: calc.billDiscount,
        amount_paid: amountPaid,
        expected_grand_total: calc.grandTotal,
        items: items
    };

    // Open receipt window early (before await) to avoid popup blocker
    const apiBase = window.location.protocol + '//' + window.location.hostname + ':8081';
    let receiptWin = window.open('', '_blank');

    try {
        const result = await window.apiRequest('/api/invoices', {
            method: 'POST',
            body: JSON.stringify(payload)
        });

        if (result.success) {
            cart = [];

            // Clear all grid rows
            const tbody = document.querySelector('#billingGrid tbody');
            if (tbody) {
                tbody.querySelectorAll('tr').forEach(function(tr) {
                    tr.removeAttribute('data-batch-id');
                    tr.querySelectorAll('td').forEach(function(c) { c.textContent = ''; });
                    tr.classList.remove('bg-active');
                });
            }

            sessionStorage.removeItem('posCart');
            renderCart();
            const paidInput = document.getElementById('amountPaidInput');
            paidInput.value = '';
            delete paidInput.dataset.userEdited;
            document.getElementById('cartDiscountInput').value = '';
            document.getElementById('customerSearchInput').value = '';
            document.getElementById('billCustomerId').value = '';

            // Navigate receipt window with token as query param
            const token = localStorage.getItem('auth_token');
            const receiptUrl = apiBase + '/api/invoices/' + result.invoice.id + '/receipt?token=' + encodeURIComponent(token || '');
            if (receiptWin && !receiptWin.closed) {
                receiptWin.location.href = receiptUrl;
            } else {
                window.open(receiptUrl, '_blank');
            }

            loadPOSData();
        }
    } catch (e) {
        if (receiptWin && !receiptWin.closed) receiptWin.close();
        alert('Checkout failed: ' + e.message);
    }
}

/* Delete row confirmation */
function closeDeleteConfirm() {
    closeModal('deleteRowModal');
    window._deleteTargetRow = null;
}

function confirmDeleteRow() {
    const tr = window._deleteTargetRow;
    if (!tr) { closeDeleteConfirm(); return; }

    const bid = tr.getAttribute('data-batch-id');

    // Clear all cells
    tr.querySelectorAll('td').forEach(function(c) { c.textContent = ''; });
    tr.removeAttribute('data-batch-id');
    tr.classList.remove('bg-active');

    // Remove from cart
    if (bid) {
        const idx = cart.findIndex(function(c) { return c.batchId === bid; });
        if (idx !== -1) cart.splice(idx, 1);
    }

    calculateCart();
    closeDeleteConfirm();
}
