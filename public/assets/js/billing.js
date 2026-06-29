/* billing.js — POS Billing Module */

window.posProducts = [];
window.posBatches = [];
window.posCategories = [];
window.posCategoryMap = {};
window.activePosCategory = '';
window.cart = [];

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

        const searchStr = `${p.name} ${b.batch_number} ${b.vendor_name || ''} ${catName}`.toLowerCase();
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

    window._posCalc = { subtotal, totalGst, billDiscount, totalDiscount, roundOff, grandTotal, applyGst };
}

async function populateCustomerSelect() {
    const select = document.getElementById('billCustomerSelect');
    if (!select) return;
    try {
        const data = await window.apiRequest('/api/customers?limit=500');
        const customers = Array.isArray(data) ? data : (data.data || []);
        select.innerHTML = '<option value="">-- Walk-in Customer (No Credit) --</option>';
        customers.forEach(c => {
            select.innerHTML += `<option value="${c.id}">${c.name} (${c.phone})</option>`;
        });
    } catch (e) {
        console.error('Failed to load customers:', e);
    }
}

async function processCheckout() {
    const amountPaid = parseFloat(document.getElementById('amountPaidInput')?.value || 0);
    const customerId = document.getElementById('billCustomerSelect')?.value || null;
    const customerName = customerId ? null : (document.getElementById('billCustomerSelect')?.selectedOptions?.[0]?.text || 'Walk-in');
    const calc = window._posCalc;

    if (!cart.length) { alert('Cart is empty'); return; }
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

    try {
        const result = await window.apiRequest('/api/invoices', {
            method: 'POST',
            body: JSON.stringify(payload)
        });

        if (result.success) {
            cart = [];
            renderCart();
            document.getElementById('amountPaidInput').value = '';
            document.getElementById('cartDiscountInput').value = '';
            alert('Invoice created: ' + result.invoice.invoiceNumber);
            loadPOSData();
        }
    } catch (e) {
        alert('Checkout failed: ' + e.message);
    }
}
