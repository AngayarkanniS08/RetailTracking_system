// ============================================
// Vendor & Purchase Module
// ============================================

// ── Global state ──────────────────────────────────────────────
let currentPurchasePage = 1;
let totalPurchasePages = 1;

// ── Helper: escape HTML ──────────────────────────────────────
function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>]/g, m => {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}

// ── Helper: format currency ──────────────────────────────────
function formatCurrency(amount) {
    return '₹' + parseFloat(amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 });
}

// ── Helper: format date ──────────────────────────────────────
function formatDate(dateStr) {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    return d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}

// ============================================
// 1. Load products for "Add Purchase" modal
// ============================================
async function loadProductsForVendor() {
    const select = document.getElementById('slStockName');
    if (!select) {
        console.error('Dropdown #slStockName not found');
        return;
    }
    select.innerHTML = '<option value="">Loading products...</option>';
    try {
        const data = await window.apiRequest('/api/products?limit=1000');
        console.log('API response:', data);  // ← ADD THIS
        
        const products = Array.isArray(data) ? data : (data.data || []);
        console.log('Products array:', products);  // ← ADD THIS
        
        select.innerHTML = '<option value="">-- Select Product --</option>';
        products.forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.id;
            opt.textContent = p.name;
            select.appendChild(opt);
        });
    } catch (err) {
        console.error('Failed to load products', err);
        select.innerHTML = '<option value="">Error loading products</option>';
    }
}

// ============================================
// 2. Load vendors for dropdown
// ============================================
async function loadVendorsForDropdown() {
    const select = document.getElementById('vendorSelect');
    if (!select) return;
    select.innerHTML = '<option value="">Loading vendors...</option>';
    try {
        const data = await window.apiRequest('/api/vendors?limit=1000');
        const vendors = Array.isArray(data) ? data : (data.data || []);
        select.innerHTML = '<option value="">-- Select Vendor --</option>';
        vendors.forEach(v => {
            const opt = document.createElement('option');
            opt.value = v.id;
            opt.textContent = v.name;
            select.appendChild(opt);
        });
    } catch (err) {
        console.error('Failed to load vendors', err);
        select.innerHTML = '<option value="">Error loading vendors</option>';
    }
}

// ============================================
// 3. Save new purchase
// ============================================
// ─────────────────────────────────────────────────────────────
// Save Stock Purchase Entry (Add Stock Entry Modal)
// ─────────────────────────────────────────────────────────────
async function saveStockEntry() {
    // 1. Get values from the modal
    const productSelect = document.getElementById('slStockName');
    const productId = productSelect ? productSelect.value : '';
    const productName = productSelect ? productSelect.options[productSelect.selectedIndex]?.text : '';

    const vendorName = document.getElementById('slVendorName')?.value.trim() || '';
    const vendorPhone = document.getElementById('slVendorPhone')?.value.trim() || '';
    const quantity = parseFloat(document.getElementById('slQty')?.value) || 0;
    const baseAmount = parseFloat(document.getElementById('slAmount')?.value) || 0;
    const amountPaid = parseFloat(document.getElementById('slPaid')?.value) || 0;
    const purchaseDate = document.getElementById('slPurchaseDate')?.value || new Date().toISOString().split('T')[0];

    // 2. Validate
    if (!productId) {
        alert('Please select a product');
        return;
    }
    if (!vendorName) {
        alert('Please enter vendor name');
        return;
    }
    if (!vendorPhone) {
        alert('Please enter vendor contact number');
        return;
    }
    if (!/^[0-9]{10,15}$/.test(vendorPhone)) {
        alert('Vendor contact number must contain only digits and be between 10 and 15 digits long');
        return;
    }
    if (quantity <= 0) {
        alert('Quantity must be greater than zero');
        return;
    }
    if (baseAmount <= 0) {
        alert('Base amount must be greater than zero');
        return;
    }
    if (amountPaid > baseAmount) {
        alert('Amount paid cannot exceed base amount');
        return;
    }

    // 3. Build payload (matches your backend DTO)
    const payload = {
        vendor_name: vendorName,
        phone: vendorPhone,
        purchase_date: purchaseDate,
        base_amount: baseAmount,
        amount_paid: amountPaid,
        items: [{
            product_id: productId,
            quantity: quantity,
            unit_price: baseAmount / quantity   // calculate unit price
        }]
    };

    // 4. Show loading state
    const btn = document.getElementById('savePurchaseBtn');
    const originalText = btn.innerText;
    btn.disabled = true;
    btn.innerText = 'Saving...';

    try {
        const response = await window.apiRequest('/api/purchases', {
            method: 'POST',
            body: JSON.stringify(payload)
        });

        if (response && response.success) {
            alert('Stock purchase saved successfully');
            closeModal('addStockEntryModal');
            // Reset form
            document.getElementById('slStockName').value = '';
            document.getElementById('slVendorName').value = '';
            document.getElementById('slVendorPhone').value = '';
            document.getElementById('slQty').value = '';
            document.getElementById('slAmount').value = '';
            document.getElementById('slPaid').value = '';
            document.getElementById('slPurchaseDate').value = '';
            // Refresh purchase list if on vendor page
            if (typeof loadPurchases === 'function') {
                loadPurchases(1);
            }
        } else {
            alert(response?.error || 'Failed to save purchase');
        }
    } catch (err) {
        console.error('Save error:', err);
        alert('Network error. Please try again.');
    } finally {
        btn.disabled = false;
        btn.innerText = originalText;
    }
}

// ============================================
// 4. Load and render purchase list
// ============================================
async function loadPurchases(page = 1) {
    currentPurchasePage = page;
    const search = document.getElementById('vendorSearch')?.value || '';
    let url = `/api/purchases?page=${page}&limit=10`;
    if (search) url += `&search=${encodeURIComponent(search)}`;
    try {
        const data = await window.apiRequest(url);
        if (data && !data.error) {
            const purchases = data.data || [];
            totalPurchasePages = data.pagination?.total_pages || 1;

            // Update stats cards
            if (data.stats) {
                const totalVendorsEl = document.getElementById('slTotalVendors');
                const totalPurchasedEl = document.getElementById('slTotalAmount');
                const totalPaidEl = document.getElementById('slTotalPaid');
                const totalBalanceEl = document.getElementById('slTotalBalance');
                if (totalVendorsEl) totalVendorsEl.innerText = data.stats.total_vendors || 0;
                if (totalPurchasedEl) totalPurchasedEl.innerText = formatCurrency(data.stats.total_purchased || 0);
                if (totalPaidEl) totalPaidEl.innerText = formatCurrency(data.stats.total_paid || 0);
                if (totalBalanceEl) totalBalanceEl.innerText = formatCurrency(data.stats.balance_due || 0);
            }

            renderPurchaseTable(purchases);
            renderPurchasePagination(data.pagination);
        } else {
            console.error('Failed to load purchases');
        }
    } catch (err) {
        console.error('Error loading purchases', err);
    }
}

function renderPurchaseTable(purchases) {
    const tbody = document.querySelector('#vendorPurchaseTable tbody');
    if (!tbody) return;
    tbody.innerHTML = '';
    if (purchases.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; color:var(--muted); padding:2rem;">No purchases found</td></tr>';
        return;
    }
    purchases.forEach(p => {
        const tr = tbody.insertRow();
        
        // 1. Vendor Name
        tr.insertCell().innerText = p.vendorName || p.vendorId || '-';
        
        // 2. Contact Info
        tr.insertCell().innerText = p.vendorPhone || '-';
        
        // 3. Purchase Date
        tr.insertCell().innerText = formatDate(p.purchaseDate);
        
        // 4. Total orders
        const ordersCell = tr.insertCell();
        ordersCell.innerHTML = `
            <span style="font-weight: 500;">
                📦 ${p.totalOrders || 0}
            </span>
        `;
        
        // 5. Total Bill
        tr.insertCell().innerText = formatCurrency(p.baseAmount || 0);
        
        // 6. Amount Paid
        tr.insertCell().innerText = formatCurrency(p.amountPaid || 0);
        
        // 7. Balance Due
        tr.insertCell().innerText = formatCurrency((p.baseAmount || 0) - (p.amountPaid || 0));
        
        // 8. Status
        tr.insertCell().innerHTML = `<span class="badge badge-${p.status === 'paid' ? 'ok' : (p.status === 'partial' ? 'warn' : 'danger')}">${p.status}</span>`;
        
        // 9. Action
        tr.insertCell().innerHTML = `
            <button class="btn-icon" onclick="viewPurchase('${p.id}')" title="View Details">👁️</button>
            ${p.status !== 'paid' ? `<button class="btn-icon" onclick="recordPayment('${p.id}')" title="Record Payment">💰</button>` : ''}
        `;
    });
}

function renderPurchasePagination(pagination) {
    const container = document.getElementById('purchasePaginationControls');
    if (!container) return;
    if (!pagination || pagination.total_pages <= 1) {
        container.style.display = 'none';
        return;
    }
    container.style.display = 'flex';
    container.innerHTML = `
        <button class="pagination-btn" id="prevPurchaseBtn" ${!pagination.has_prev ? 'disabled' : ''}>← Previous</button>
        <span class="pagination-info">Page ${pagination.current_page} of ${pagination.total_pages}</span>
        <button class="pagination-btn" id="nextPurchaseBtn" ${!pagination.has_next ? 'disabled' : ''}>Next →</button>
    `;
    document.getElementById('prevPurchaseBtn')?.addEventListener('click', () => {
        if (pagination.has_prev) loadPurchases(pagination.current_page - 1);
    });
    document.getElementById('nextPurchaseBtn')?.addEventListener('click', () => {
        if (pagination.has_next) loadPurchases(pagination.current_page + 1);
    });
}

// ============================================
// 5. View purchase details (simple alert)
// ============================================
async function viewPurchase(purchaseId) {
    try {
        const data = await window.apiRequest(`/api/purchases/${purchaseId}`);
        if (data && !data.error) {
            const p = data;
            let details = `Vendor: ${p.vendorName || p.vendorId}\n`;
            details += `Date: ${formatDate(p.purchaseDate)}\n`;
            details += `Total: ${formatCurrency(p.baseAmount)}\n`;
            details += `Paid: ${formatCurrency(p.amountPaid)}\n`;
            details += `Status: ${p.status}\n`;
            details += `Items:\n`;
            if (p.items && p.items.length) {
                p.items.forEach(item => {
                    details += `  - ${item.productName || item.productId}: ${item.quantity} x ${formatCurrency(item.unitPrice)} = ${formatCurrency(item.quantity * item.unitPrice)}\n`;
                });
            }
            alert(details);
        } else {
            alert('Failed to load purchase details');
        }
    } catch (err) {
        console.error(err);
        alert('Error loading details');
    }
}

// ============================================
// 6. Record payment
// ============================================
async function recordPayment(purchaseId) {
    const amount = prompt('Enter payment amount (₹):', '0');
    if (!amount) return;
    const payAmount = parseFloat(amount);
    if (isNaN(payAmount) || payAmount <= 0) {
        alert('Please enter a valid positive amount');
        return;
    }
    try {
        const response = await window.apiRequest(`/api/purchases/${purchaseId}/pay`, {
            method: 'POST',
            body: JSON.stringify({ amount: payAmount })
        });
        if (response && response.success) {
            alert('Payment recorded');
            loadPurchases(currentPurchasePage);
        } else {
            alert(response?.error || 'Failed to record payment');
        }
    } catch (err) {
        console.error(err);
        alert('Network error');
    }
}

// ============================================
// 7. Initialisation
// ============================================
async function initVendorPage() {
    await loadPurchases(1);
}

// Call load functions when the modal opens (called from button)
window.loadProductsForVendor = loadProductsForVendor;
window.loadVendorsForDropdown = loadVendorsForDropdown;
window.saveStockEntry = saveStockEntry;
window.recordPayment = recordPayment;
window.viewPurchase = viewPurchase;
window.initVendorPage = initVendorPage;

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('vendor_list')?.classList.contains('active')) {
        initVendorPage();
    }
});