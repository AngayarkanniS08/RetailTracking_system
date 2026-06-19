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
        const actionCell = tr.insertCell();
        actionCell.innerHTML = `
            <div class="action-buttons">
                <button class="btn-icon edit-btn" onclick="editPurchase('${p.id}')" title="Edit Purchase">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                </button>
                ${p.vendorId ?`<button class="btn-icon history-btn" onclick="switchtab('vendorhistory','${p.vendorId}')" title="View History">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/>
                        <path d="M3 3v5h5"/>
                        <path d="M12 7v5l4 2"/>
                    </svg>
                </button>` : ''}
                ${p.status !== 'paid' ? `
                <button class="btn-icon restock-btn" onclick="recordPayment('${p.id}')" title="Record Payment">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="2" y="6" width="20" height="12" rx="2"/>
                        <circle cx="12" cy="12" r="2"/>
                        <path d="M6 12h.01M18 12h.01"/>
                    </svg>
                </button>` : ''}
            </div>
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

async function editPurchase(purchaseId) {
    try {
        const data = await window.apiRequest(`/api/purchases/${purchaseId}`);
        if (data && !data.error) {
            document.getElementById('editPurchaseId').value = purchaseId;
            let formattedDate = '';
            if (data.purchaseDate) {
                const dateObj = new Date(data.purchaseDate);
                if (!isNaN(dateObj.getTime())) {
                    const year = dateObj.getFullYear();
                    const month = String(dateObj.getMonth() + 1).padStart(2, '0');
                    const day = String(dateObj.getDate()).padStart(2, '0');
                    formattedDate = `${year}-${month}-${day}`;
                }
            }
            document.getElementById('editPurchaseDate').value = formattedDate;
            document.getElementById('editBaseAmount').value = data.baseAmount || 0;
            document.getElementById('editAmountPaid').value = data.amountPaid || 0;
             // ── Render Items ──
            const container = document.getElementById('editItemsContainer');
            container.innerHTML = '';
            if (data.items && data.items.length > 0) {
                data.items.forEach((item, index) => {
                    addEditItemRow(item, index);
                });
            } else {
                // Add one empty row if no items
                addEditItemRow(null, 0);
            }
            
            openModal('editPurchaseModal');
        } else {
            alert('Failed to load purchase details');
        }
    } catch (err) {
        console.error('Error loading purchase:', err);
        alert('Error loading purchase details');
    }
}

function addEditItemRow(item = null, index = 0) {
    const container = document.getElementById('editItemsContainer');
    const row = document.createElement('div');
    row.className = 'edit-item-row';
    row.style.cssText = 'display: flex; gap: 10px; margin-bottom: 10px; align-items: center;';
    row.dataset.index = index;

    // Product ID (hidden) – we'll keep it as a hidden field or a dropdown
    const productIdInput = document.createElement('input');
    productIdInput.type = 'hidden';
    productIdInput.name = `edit_items[${index}][product_id]`;
    productIdInput.value = item?.productId || '';
    row.appendChild(productIdInput);

    // Product Name (readonly display – or you can make it a dropdown)
    const productNameInput = document.createElement('input');
    productNameInput.type = 'text';
    productNameInput.className = 'input-field';
    productNameInput.style.cssText = 'flex: 2;';
    productNameInput.placeholder = 'Product Name';
    productNameInput.value = item?.productName || '';
    productNameInput.readOnly = true;
    row.appendChild(productNameInput);

    // Quantity
    const qtyInput = document.createElement('input');
    qtyInput.type = 'number';
    qtyInput.className = 'input-field';
    qtyInput.style.cssText = 'flex: 1;';
    qtyInput.placeholder = 'Qty';
    qtyInput.value = item?.quantity || 1;
    qtyInput.step = 'any';
    row.appendChild(qtyInput);

    // Unit Price
    const unitPriceInput = document.createElement('input');
    unitPriceInput.type = 'number';
    unitPriceInput.className = 'input-field';
    unitPriceInput.style.cssText = 'flex: 1;';
    unitPriceInput.placeholder = 'Unit Price';
    unitPriceInput.value = item?.unitPrice || 0;
    unitPriceInput.step = '0.01';
    row.appendChild(unitPriceInput);

    // Remove button
    const removeBtn = document.createElement('button');
    removeBtn.type = 'button';
    removeBtn.className = 'btn-icon delete-btn';
    removeBtn.innerHTML = '✕';
    removeBtn.title = 'Remove item';
    removeBtn.onclick = function() {
        if (container.children.length > 1) {
            row.remove();
        } else {
            alert('At least one item is required');
        }
    };
    row.appendChild(removeBtn);

    container.appendChild(row);
}

async function saveEditPurchase() {
    const purchaseId = document.getElementById('editPurchaseId').value;
    const purchaseDate = document.getElementById('editPurchaseDate').value;
    const baseAmount = parseFloat(document.getElementById('editBaseAmount').value) || 0;
    const amountPaid = parseFloat(document.getElementById('editAmountPaid').value) || 0;

    if (!purchaseId) {
        alert('Invalid purchase');
        return;
    }
    if (baseAmount < 0 || amountPaid < 0) {
        alert('Amounts cannot be negative');
        return;
    }
    if (amountPaid > baseAmount) {
        alert('Amount paid cannot exceed base amount');
        return;
    }

    // ── Collect items from the form ──
    const itemRows = document.querySelectorAll('.edit-item-row');
    const items = [];
    let hasError = false;
    itemRows.forEach(row => {
        const productId = row.querySelector('input[name*="product_id"]')?.value || '';
        const quantity = parseFloat(row.querySelector('input[placeholder="Qty"]')?.value) || 0;
        const unitPrice = parseFloat(row.querySelector('input[placeholder="Unit Price"]')?.value) || 0;
        if (!productId) {
            hasError = true;
            return;
        }
        if (quantity <= 0) {
            hasError = true;
            return;
        }
        items.push({
            product_id: productId,
            quantity: quantity,
            unit_price: unitPrice
        });
    });

    if (hasError || items.length === 0) {
        alert('Please fill all item fields correctly');
        return;
    }

    const payload = {
        purchase_date: purchaseDate,
        base_amount: baseAmount,
        amount_paid: amountPaid,
        items: items
    };

    const btn = document.getElementById('saveEditPurchaseBtn');
    const originalText = btn.innerText;
    btn.disabled = true;
    btn.innerText = 'Updating...';

    try {
        const response = await window.apiRequest(`/api/purchases/${purchaseId}`, {
            method: 'PUT',
            body: JSON.stringify(payload)
        });
        if (response && response.success) {
            alert('Purchase updated successfully');
            closeModal('editPurchaseModal');
            await loadPurchases(currentPurchasePage);
        } else {
            alert(response?.error || 'Failed to update purchase');
        }
    } catch (err) {
        console.error('Update error:', err);
        alert('Network error. Please try again.');
    } finally {
        btn.disabled = false;
        btn.innerText = originalText;
    }
}
async function initVendorHistory(vendorId = null) {
    const titleEl = document.getElementById('vendorHistoryTitle');
    const subtitleEl = document.getElementById('vendorHistorySubtitle');
    const bodyEl = document.getElementById('vendorHistoryBody');

    titleEl.innerText = vendorId ? 'Loading vendor history...' : 'Purchase History — All Vendors';
    subtitleEl.innerText = '';
    bodyEl.innerHTML = '<p style="color:var(--muted); text-align:center; padding:2rem;">Loading...</p>';

    const url = vendorId
        ? `/api/vendors/${encodeURIComponent(vendorId)}/history`
        : `/api/vendors/history/all`;

    try {
        const data = await window.apiRequest(url);
        console.log('Vendor history API response:', data); // Debug

        // Safely extract purchases
        const purchases = Array.isArray(data) ? data : (data?.data || []);
        console.log('Purchases extracted:', purchases); // Debug

        if (purchases.length === 0) {
            bodyEl.innerHTML = '<p style="color:var(--muted); text-align:center; padding:2rem;">No purchases found.</p>';
            return;
        }

        // Update title with vendor name if available
        if (vendorId && purchases[0]?.vendorName) {
            titleEl.innerText = purchases[0].vendorName + ' – History';
            subtitleEl.innerText = purchases[0].vendorPhone || '';
        }

        // Render stats and body
        renderVendorHistoryStats(purchases);
        renderVendorHistoryBody(purchases);

    } catch (err) {
        console.error('Error loading vendor history:', err);
        bodyEl.innerHTML = '<p style="color:var(--danger); text-align:center; padding:2rem;">Failed to load purchase history. Please try again.</p>';
    }
}

function renderVendorHistoryStats(purchases) {
    const totalBilled = purchases.reduce((sum, p) => sum + (p.baseAmount || 0), 0);
    const totalPaid = purchases.reduce((sum, p) => sum + (p.amountPaid || 0), 0);
    document.getElementById('vhTotalBilled').innerText = formatCurrency(totalBilled);
    document.getElementById('vhTotalPaid').innerText = formatCurrency(totalPaid);
    document.getElementById('vhBalance').innerText = formatCurrency(totalBilled - totalPaid);
}

function renderVendorHistoryBody(purchases) {
    const body = document.getElementById('vendorHistoryBody');
    if (purchases.length === 0) {
        body.innerHTML = '<p style="color:var(--muted); text-align:center; padding:2rem;">No purchases found.</p>';
        return;
    }
    const grouped = {};
    purchases.forEach(p => {
        const date = formatDate(p.purchaseDate);
        (grouped[date] ||= []).push(p);
    });

    const sortedDates = Object.keys(grouped).sort((a, b) => new Date(b) - new Date(a));
    let html = '';
    sortedDates.forEach(date => {
        const orders = grouped[date].length;
        const totalBilled = grouped[date].reduce((sum, p) => sum + (p.baseAmount || 0), 0);
        const totalPaid = grouped[date].reduce((sum, p) => sum + (p.amountPaid || 0), 0);
        const totalDue = totalBilled - totalPaid;
        html += `
            <details class="accordion" open style="background:var(--card-bg); border:1px solid var(--border); border-radius:var(--radius-lg); margin-bottom:1rem; overflow:hidden;">
                <summary class="accordion-header" style="cursor:pointer; display:flex; align-items:center; justify-content:center; gap:1.5rem; font-size:0.9rem; padding:0.85rem 1rem; user-select:none; transition:background 0.2s;">
                    <span style="font-weight:700; font-size:1.05rem; color:var(--text);">${date}</span>
                    <span style="color:var(--muted-strong);">Orders: <strong>${orders}</strong></span>
                    <span>Billed: ${formatCurrency(totalBilled)}</span>
                    <span>Paid: ${formatCurrency(totalPaid)}</span>
                    <span style="font-weight:700; color:${totalDue > 0 ? 'var(--danger)' : 'var(--ok)'};">Due: ${formatCurrency(totalDue)}</span>
                </summary>
                <div class="accordion-body" style="padding:0.5rem;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Vendor</th>
                                <th>Items</th>
                                <th>Billed</th>
                                <th>Paid</th>
                                <th>Balance</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
        `;
        grouped[date].forEach(p => {
            const balance = (p.baseAmount || 0) - (p.amountPaid || 0);
            const statusClass = p.status === 'completed' || p.status === 'paid' ? 'badge-success'
                : p.status === 'cancelled' ? 'badge-danger'
                : 'badge-warning';
            let itemsHtml = '';
            if (p.items && p.items.length > 0) {
                p.items.forEach(item => {
                    itemsHtml += `
                            <div style="font-size:0.8rem; padding:2px 0; color:var(--muted);">
                                • ${item.product_name || 'Product'} — ${item.quantity} × ₹${item.unit_price}
                            </div>`;
                });
            }
            html += `
                            <tr>
                                <td style="font-family:var(--mono); font-size:0.8rem; color:var(--muted-strong);">${p.id ? p.id.slice(0, 8) : '-'}</td>
                                <td>${p.vendorName || '-'}</td>
                                <td>${itemsHtml || '-'}</td>
                                <td>${formatCurrency(p.baseAmount || 0)}</td>
                                <td style="color:var(--ok);">${formatCurrency(p.amountPaid || 0)}</td>
                                <td style="font-weight:700; color:${balance > 0 ? 'var(--danger)' : 'var(--ok)'};">${formatCurrency(balance)}</td>
                                <td><span class="badge ${statusClass}">${p.status || 'N/A'}</span></td>
                                <td>
                                    <button class="btn btn-sm btn-primary" onclick="recordPayment('${p.id}')" style="padding:2px 10px; font-size:0.75rem;">
                                        Pay
                                    </button>
                                </td>
                            </tr>`;
        });
        html += `
                        </tbody>
                    </table>
                </div>
            </details>`;
    });
    body.innerHTML = html;
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
window.saveEditPurchase = saveEditPurchase;
window.editPurchase = editPurchase;

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('vendor_list')?.classList.contains('active')) {
        initVendorPage();
    }
});