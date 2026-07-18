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
        
        const products = Array.isArray(data) ? data : (data.data || []);
        
        select.innerHTML = '<option value="">-- Select Product --</option>';
        products.forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.id;
            opt.textContent = p.name;
            opt.dataset.gst = p.gst_rate ?? 0;
            select.appendChild(opt);
        });
    } catch (err) {
        console.error('Failed to load products', err);
        select.innerHTML = '<option value="">Error loading products</option>';
    }
}

document.getElementById('slStockName')?.addEventListener('change', function() {
    const selected = this.options[this.selectedIndex];
    const gstRate = parseFloat(selected?.dataset?.gst) || 0;
    document.getElementById('purchaseGstRate').value = gstRate;
    calculatePurchaseTotal();
});

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
    const gstRate = parseFloat(document.getElementById('purchaseGstRate').value) || 0;
    const totalAmount = baseAmount + (baseAmount * gstRate / 100);
    if (amountPaid > totalAmount) {
        alert('Amount paid cannot exceed total amount');
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
            unit_price: Number((baseAmount / quantity).toFixed(2)),
            gst_rate: gstRate
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
            if (typeof loadVendorSummaries === 'function') {
                loadVendorSummaries();
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

function calculatePurchaseTotal() {
    const baseAmount = parseFloat(document.getElementById('slAmount').value) || 0;
    const gstRate = parseFloat(document.getElementById('purchaseGstRate').value) || 0;
    const gstAmount = baseAmount * (gstRate / 100);
    const totalAmount = baseAmount + gstAmount;

    let display = document.getElementById('purchaseGstDisplay');
    if (!display) {
        const container = document.getElementById('purchaseGstRate').closest('.input-group');
        display = document.createElement('div');
        display.id = 'purchaseGstDisplay';
        display.style.cssText = 'font-size:0.8rem; margin-top:4px; color:var(--muted-strong);';
        container.appendChild(display);
    }
    display.innerHTML = `Base: <strong>${formatCurrency(baseAmount)}</strong> &nbsp;|&nbsp; GST @${gstRate}%: <strong>${formatCurrency(gstAmount)}</strong> &nbsp;|&nbsp; Total: <strong>${formatCurrency(totalAmount)}</strong>`;
}

// ============================================
// 4. Load and render vendor summary (one row per vendor)
// ============================================
async function loadVendorSummaries() {
    await loadPurchases(1);
}

async function loadPurchases(page = 1) {
    currentPurchasePage = page;
    const search = document.getElementById('vendorSearch')?.value || '';
    let url = `/api/purchases?page=${page}&limit=5`;
    if (search) url += `&search=${encodeURIComponent(search)}`;
    try {
        const data = await window.apiRequest(url);
        if (data && !data.error) {
            const purchases = data.data || [];
            totalPurchasePages = data.pagination?.total_pages || 1;
            const stats = data.stats || {};

            const totalVendorsEl = document.getElementById('slTotalVendors');
            const totalPurchasedEl = document.getElementById('slTotalAmount');
            const totalPaidEl = document.getElementById('slTotalPaid');
            const totalBalanceEl = document.getElementById('slTotalBalance');
            if (totalVendorsEl) totalVendorsEl.innerText = stats.total_vendors ?? purchases.length;
            if (totalPurchasedEl) totalPurchasedEl.innerText = formatCurrency(stats.total_purchased ?? 0);
            if (totalPaidEl) totalPaidEl.innerText = formatCurrency(stats.total_paid ?? 0);
            if (totalBalanceEl) totalBalanceEl.innerText = formatCurrency(stats.balance_due ?? 0);

            renderVendorSummaryTable(purchases);
            renderPurchasePagination(data.pagination);
        } else {
            console.error('Failed to load purchases');
        }
    } catch (err) {
        console.error('Error loading purchases', err);
    }
}

function renderVendorSummaryTable(vendors) {
    const tbody = document.querySelector('#vendorPurchaseTable tbody');
    if (!tbody) return;
    tbody.innerHTML = '';
    if (vendors.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; color:var(--muted); padding:2rem;">No vendors found</td></tr>';
        return;
    }
    vendors.forEach(v => {
        const tr = tbody.insertRow();

        tr.insertCell().innerText = v.vendorName || '-';
        tr.insertCell().innerText = v.vendorPhone || '-';

        const ordersCell = tr.insertCell();
        ordersCell.innerHTML = `<span style="font-weight:500;">📦 ${v.totalOrders}</span>`;

        tr.insertCell().innerText = formatCurrency(v.totalBilled);
        tr.insertCell().innerText = formatCurrency(v.totalPaid);

        const balance = Math.max(0, v.balanceDue);
        const balanceCell = tr.insertCell();
        balanceCell.innerHTML = `<span style="font-weight:700; color:${balance > 0 ? 'var(--danger)' : 'var(--ok)'};">${formatCurrency(balance)}</span>`;

        const actionCell = tr.insertCell();
        actionCell.innerHTML = `
            <div style="white-space:nowrap;">
                <button class="btn btn-sm btn-outline" onclick="switchTab('vendorhistory','${v.vendorId}')" style="padding:2px 10px; font-size:0.75rem;">
                    View History
                </button>
                <button class="btn btn-sm btn-primary" onclick="openQuickPurchaseForVendor('${v.vendorId}','${(v.vendorName || '').replace(/'/g, "\\'")}','${(v.vendorPhone || '').replace(/'/g, "\\'")}')" style="padding:2px 10px; font-size:0.75rem;">
                    +Add
                </button>
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
    container.style.cssText = 'display:flex; justify-content:center; align-items:center; gap:12px;';
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
    const purchBtn = document.getElementById('togglePurchasesBtn');
    if (purchBtn) purchBtn.click();
}

function viewVendorHistory(vendorId) {
    if (!vendorId) {
        alert('No vendor selected');
        return;
    }
    // Redirect to vendor history page with vendor_id as query parameter
    window.location.href = `/vendor/history.php?vendor_id=${vendorId}`;
}

// ============================================
// 6. Record payment
// ============================================
async function recordPayment(purchaseId) {
    try {
        const data = await window.apiRequest(`/api/purchases/${purchaseId}`);
        if (!data || data.error) {
            alert(data?.error || 'Failed to load purchase details');
            return;
        }
        const totalAmount = data.totalAmount || ((data.baseAmount || 0) + (data.totalGst || 0));
        const balance = totalAmount - (data.amountPaid || 0);
        document.getElementById('vpPurchaseId').value = purchaseId;
        document.getElementById('vpVendorId').value = data.vendorId || '';
        document.getElementById('vpVendorName').value = data.vendorName || '';
        document.getElementById('slAmountPaying').value = '';
        document.getElementById('vpPaymentDate').value = new Date().toISOString().slice(0, 10);
        const balanceSpan = document.getElementById('slBalanceText');
        balanceSpan.dataset.originalBalance = balance;
        balanceSpan.textContent = 'Balance After Payment: ₹' + balance.toLocaleString('en-IN', { minimumFractionDigits: 2 });
        document.getElementById('slAmountPaying').oninput = function() {
            const entered = parseFloat(this.value) || 0;
            const orig = parseFloat(balanceSpan.dataset.originalBalance) || 0;
            const remaining = Math.max(0, orig - entered);
            balanceSpan.textContent = 'Balance After Payment: ₹' + remaining.toLocaleString('en-IN', { minimumFractionDigits: 2 });
        };
        openModal('vendorPaymentModal');
    } catch (err) {
        console.error(err);
        alert('Error loading purchase details');
    }
}

async function submitVendorPayment() {
    const purchaseId = document.getElementById('vpPurchaseId').value;
    const amountInput = document.getElementById('slAmountPaying');
    const payAmount = parseFloat(amountInput.value);
    if (!purchaseId) {
        alert('Purchase ID is missing');
        return;
    }
    if (isNaN(payAmount) || payAmount <= 0) {
        alert('Please enter a valid positive amount');
        return;
    }
    const paymentDate = document.getElementById('vpPaymentDate').value || new Date().toISOString().split('T')[0];
    try {
        const response = await window.apiRequest(`/api/purchases/${purchaseId}/pay`, {
            method: 'POST',
            body: JSON.stringify({ amount: payAmount, payment_date: paymentDate })
        });
        if (response && response.success) {
            alert('Payment recorded');
            closeModal('vendorPaymentModal');
            const vendorId = document.getElementById('vpVendorId').value;
            loadPurchases(currentPurchasePage);
            const historySection = document.getElementById('vendorhistory');
            if (historySection && historySection.classList.contains('active')) {
                initVendorHistory(vendorId || null);
            }
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

    // GST Rate (%)
    const gstInput = document.createElement('input');
    gstInput.type = 'number';
    gstInput.className = 'input-field';
    gstInput.style.cssText = 'flex: 0.6;';
    gstInput.placeholder = 'GST %';
    gstInput.value = item?.gstRate || 0;
    gstInput.step = '0.01';
    row.appendChild(gstInput);

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
    // ── Collect items from the form ──
    const itemRows = document.querySelectorAll('.edit-item-row');
    const items = [];
    let hasError = false;
    let totalGst = 0;
    itemRows.forEach(row => {
        const productId = row.querySelector('input[name*="product_id"]')?.value || '';
        const quantity = parseFloat(row.querySelector('input[placeholder="Qty"]')?.value) || 0;
        const unitPrice = parseFloat(row.querySelector('input[placeholder="Unit Price"]')?.value) || 0;
        const gstRate = parseFloat(row.querySelector('input[placeholder="GST %"]')?.value) || 0;
        if (!productId) {
            hasError = true;
            return;
        }
        if (quantity <= 0) {
            hasError = true;
            return;
        }
        totalGst += quantity * unitPrice * (gstRate / 100);
        items.push({
            product_id: productId,
            quantity: quantity,
            unit_price: unitPrice,
            gst_rate: gstRate
        });
    });

    if (hasError || items.length === 0) {
        alert('Please fill all item fields correctly');
        return;
    }
    if (amountPaid > baseAmount + totalGst) {
        alert('Amount paid cannot exceed total amount');
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
// ── Payment / Purchase history toggle state ──────────────────────────
let currentHistoryVendorId = null;
let currentHistoryMode = 'purchases';
let currentHistoryPage = 1;
let totalHistoryPages = 1;

async function initVendorHistory(vendorId = null) {
    currentHistoryVendorId = vendorId;
    currentHistoryMode = 'purchases';
    currentHistoryPage = 1;

    const purchBtn = document.getElementById('togglePurchasesBtn');
    const payBtn = document.getElementById('togglePaymentsBtn');
    if (purchBtn) { purchBtn.className = 'btn btn-sm btn-primary'; }
    if (payBtn) { payBtn.className = 'btn btn-sm btn-outline'; }

    document.getElementById('purchaseStats')?.style.setProperty('display', '');
    document.getElementById('paymentStats')?.style.setProperty('display', 'none');

    loadCurrentHistory();
}

async function loadCurrentHistory() {
    const titleEl = document.getElementById('vendorHistoryTitle');
    const subtitleEl = document.getElementById('vendorHistorySubtitle');
    const bodyEl = document.getElementById('vendorHistoryBody');

    if (currentHistoryMode === 'purchases') {
        titleEl.innerText = currentHistoryVendorId ? 'Loading vendor history...' : 'Purchase History — All Vendors';
    } else {
        titleEl.innerText = currentHistoryVendorId ? 'Loading payment history...' : 'Payment History — All Vendors';
    }
    subtitleEl.innerText = '';
    bodyEl.innerHTML = '<p style="color:var(--muted); text-align:center; padding:2rem;">Loading...</p>';

    const vhMonth = document.getElementById('vhMonthSearch')?.value || '';
    const vhDate = document.getElementById('vhDateSearch')?.value || '';
    let url = currentHistoryMode === 'purchases'
        ? (currentHistoryVendorId ? `/api/vendors/${encodeURIComponent(currentHistoryVendorId)}/history` : '/api/vendors/history/all')
        : (currentHistoryVendorId ? `/api/vendors/${encodeURIComponent(currentHistoryVendorId)}/payments` : '/api/vendors/payments/all');
    const monthNames = {jan:'01',feb:'02',mar:'03',apr:'04',may:'05',jun:'06',jul:'07',aug:'08',sep:'09',oct:'10',nov:'11',dec:'12'};
    const params = [];
    if (vhDate) {
        params.push(`date=${encodeURIComponent(vhDate)}`);
    } else if (vhMonth) {
        const parts = vhMonth.split('-');
        if (parts.length >= 2 && parts[0].length === 4 && parts[1].length === 2) {
            params.push(`year=${parts[0]}&month=${parts[1]}`);
        } else {
            const trimmed = vhMonth.trim();
            const m = monthNames[trimmed.toLowerCase().slice(0,3)];
            if (m) {
                params.push(`year=${new Date().getFullYear()}&month=${m}`);
            } else {
                bodyEl.innerHTML = `<p style="color:var(--danger); text-align:center; padding:2rem;">Invalid month. Use the picker or type a month name (e.g. June).</p>`;
                return;
            }
        }
    }
    if (params.length) url += '?' + params.join('&');

    try {
        const data = await window.apiRequest(url);
        const records = Array.isArray(data) ? data : (data?.data || []);

        if (records.length === 0) {
            bodyEl.innerHTML = `<p style="color:var(--muted); text-align:center; padding:2rem;">No ${currentHistoryMode === 'purchases' ? 'purchases' : 'payments'} found.</p>`;
            document.getElementById('purchaseStats')?.style.setProperty('display', currentHistoryMode === 'purchases' ? '' : 'none');
            document.getElementById('paymentStats')?.style.setProperty('display', currentHistoryMode === 'payments' ? '' : 'none');
            return;
        }

        if (currentHistoryMode === 'purchases') {
            if (currentHistoryVendorId && records[0]?.vendorName) {
                titleEl.innerText = records[0].vendorName + ' – History';
                subtitleEl.innerText = records[0].vendorPhone || '';
            }
            renderVendorHistoryStats(records);
            const grouped = groupByDate(records, 'purchaseDate');
            const sortedDates = Object.keys(grouped).sort((a, b) => new Date(b) - new Date(a));
            totalHistoryPages = Math.max(1, Math.ceil(sortedDates.length / 5));
            renderVendorHistoryBody(grouped, sortedDates, currentHistoryPage);
            renderHistoryPagination();
        } else {
            if (currentHistoryVendorId && records[0]?.vendorName) {
                titleEl.innerText = records[0].vendorName + ' – Payment History';
                subtitleEl.innerText = records[0].vendorPhone || '';
            }
            renderPaymentHistoryStats(records);
            const grouped = groupByDate(records, 'paymentDate');
            const sortedDates = Object.keys(grouped).sort((a, b) => new Date(b) - new Date(a));
            totalHistoryPages = Math.max(1, Math.ceil(sortedDates.length / 5));
            renderPaymentHistoryBody(grouped, sortedDates, currentHistoryPage);
            renderHistoryPagination();
        }
    } catch (err) {
        console.error('Error loading history:', err);
        bodyEl.innerHTML = `<p style="color:var(--danger); text-align:center; padding:2rem;">Failed to load ${currentHistoryMode === 'purchases' ? 'purchase' : 'payment'} history. Please try again.</p>`;
    }
}

function switchHistoryTab(mode) {
    currentHistoryMode = mode;
    currentHistoryPage = 1;
    const purchBtn = document.getElementById('togglePurchasesBtn');
    const payBtn = document.getElementById('togglePaymentsBtn');
    if (purchBtn) { purchBtn.className = mode === 'purchases' ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-outline'; }
    if (payBtn) { payBtn.className = mode === 'payments' ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-outline'; }
    document.getElementById('purchaseStats')?.style.setProperty('display', mode === 'purchases' ? '' : 'none');
    document.getElementById('paymentStats')?.style.setProperty('display', mode === 'payments' ? '' : 'none');
    loadCurrentHistory();
}

function groupByDate(records, dateField) {
    const grouped = {};
    records.forEach(r => {
        const date = formatDate(r[dateField]);
        (grouped[date] ||= []).push(r);
    });
    return grouped;
}

function calcTotalWithGst(p) {
    if (p.totalAmount) return p.totalAmount;
    let gst = 0;
    if (p.items) p.items.forEach(item => { gst += item.quantity * item.unit_price * (item.gst_rate || 0) / 100; });
    return (p.baseAmount || 0) + gst;
}
function renderVendorHistoryStats(purchases) {
    const totalBilled = purchases.reduce((sum, p) => sum + calcTotalWithGst(p), 0);
    const totalPaid = purchases.reduce((sum, p) => sum + (p.amountPaid || 0), 0);
    document.getElementById('vhTotalBilled').innerText = formatCurrency(totalBilled);
    document.getElementById('vhTotalPaid').innerText = formatCurrency(totalPaid);
    document.getElementById('vhBalance').innerText = formatCurrency(Math.max(0, totalBilled - totalPaid));
}

function renderVendorHistoryBody(grouped, sortedDates, page) {
    const body = document.getElementById('vendorHistoryBody');
    if (!sortedDates || sortedDates.length === 0) {
        body.innerHTML = '<p style="color:var(--muted); text-align:center; padding:2rem;">No purchases found.</p>';
        return;
    }
    const perPage = 5;
    const start = (page - 1) * perPage;
    const pageDates = sortedDates.slice(start, start + perPage);
    let html = '';
    pageDates.forEach(date => {
        const orders = grouped[date].length;
        const totalBilled = grouped[date].reduce((sum, p) => sum + calcTotalWithGst(p), 0);
        const totalPaid = grouped[date].reduce((sum, p) => sum + (p.amountPaid || 0), 0);
        const totalDue = Math.max(0, totalBilled - totalPaid);
        html += `
            <details class="accordion" open style="background:var(--card-bg); border:1px solid var(--border); border-radius:var(--radius-lg); margin-bottom:1rem; overflow:hidden;">
                <summary class="accordion-header" style="cursor:pointer; display:flex; align-items:center; justify-content:center; gap:1.5rem; font-size:0.9rem; padding:0.85rem 1rem; user-select:none; transition:background 0.2s;">
                    <span style="font-weight:700; font-size:1.05rem; color:var(--text);">${date}</span>
                    <span style="color:var(--muted-strong);">Orders: <strong>${orders}</strong></span>
                    <span>Total: ${formatCurrency(totalBilled)}</span>
                    <span>Paid: ${formatCurrency(totalPaid)}</span>
                    <span style="font-weight:700; color:${totalDue > 0 ? 'var(--danger)' : 'var(--ok)'};">Due: ${formatCurrency(totalDue)}</span>
                </summary>
                <div class="accordion-body" style="padding:0.5rem; overflow-x:auto;">
                    <table class="table" style="min-width:900px;">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Vendor</th>
                                <th>Items</th>
                                <th>Base Amt</th>
                                <th>GST</th>
                                <th>Total</th>
                                <th>Paid</th>
                                <th>Balance</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
        `;
        grouped[date].forEach(p => {
            const statusClass = p.status === 'paid' ? 'badge-success' : 'badge-warning';
            let itemsHtml = '';
            let totalGst = 0;
            if (p.items && p.items.length > 0) {
                p.items.forEach(item => {
                    const gstAmount = (item.quantity * item.unit_price * (item.gst_rate || 0)) / 100;
                    totalGst += gstAmount;
                    itemsHtml += `
                            <div style="font-size:0.8rem; padding:2px 0; color:var(--muted);">
                                • ${item.product_name || 'Product'} — ${item.quantity} × ₹${item.unit_price}
                                <span style="color:var(--muted-strong);"> (GST @${item.gst_rate || 0}%: ₹${gstAmount.toFixed(2)})</span>
                            </div>`;
                });
            }
            const totalAmount = p.totalAmount || ((p.baseAmount || 0) + totalGst);
            const balance = Math.max(0, totalAmount - (p.amountPaid || 0));
            html += `
                            <tr>
                                <td style="font-family:var(--mono); font-size:0.8rem; color:var(--muted-strong);">${p.id ? p.id.slice(0, 8) : '-'}</td>
                                <td>${p.vendorName || '-'}</td>
                                <td>${itemsHtml || '-'}</td>
                                <td>${formatCurrency(p.baseAmount || 0)}</td>
                                <td style="font-size:0.8rem; color:var(--muted-strong);">${formatCurrency(totalGst)}</td>
                                <td style="font-weight:600;">${formatCurrency(totalAmount)}</td>
                                <td style="color:var(--ok);">${formatCurrency(p.amountPaid || 0)}</td>
                                <td style="font-weight:700; color:${balance > 0 ? 'var(--danger)' : 'var(--ok)'};">${formatCurrency(balance)}</td>
                                <td><span class="badge ${statusClass}">${p.status || 'N/A'}</span></td>
                                <td>
                                    <div style="display:flex; gap:4px;">
                                        <button class="btn btn-sm btn-primary" onclick="editPurchase('${p.id}')" style="padding:2px 10px; font-size:0.75rem;">
                                            Edit
                                        </button>
                                        ${balance > 0 ? `<button class="btn btn-sm btn-outline" onclick="recordPayment('${p.id}')" style="padding:2px 10px; font-size:0.75rem;">
                                            Pay
                                        </button>` : ''}
                                    </div>
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

function renderHistoryPagination() {
    const container = document.getElementById('historyPaginationControls');
    if (!container) return;
    if (totalHistoryPages <= 1) {
        container.style.display = 'none';
        return;
    }
    container.style.cssText = 'display:flex; justify-content:center; align-items:center; gap:12px; margin-top:1rem;';
    container.innerHTML = `
        <button class="pagination-btn" id="prevHistoryBtn" ${currentHistoryPage <= 1 ? 'disabled' : ''}>← Previous</button>
        <span class="pagination-info">Page ${currentHistoryPage} of ${totalHistoryPages}</span>
        <button class="pagination-btn" id="nextHistoryBtn" ${currentHistoryPage >= totalHistoryPages ? 'disabled' : ''}>Next →</button>
    `;
    document.getElementById('prevHistoryBtn')?.addEventListener('click', () => {
        if (currentHistoryPage > 1) {
            currentHistoryPage--;
            loadCurrentHistory();
        }
    });
    document.getElementById('nextHistoryBtn')?.addEventListener('click', () => {
        if (currentHistoryPage < totalHistoryPages) {
            currentHistoryPage++;
            loadCurrentHistory();
        }
    });
}

function renderPaymentHistoryStats(payments) {
    const total = payments.reduce((sum, p) => sum + (p.amount || 0), 0);
    const count = payments.length;
    const avg = count > 0 ? total / count : 0;
    document.getElementById('vhPaymentTotal').innerText = formatCurrency(total);
    document.getElementById('vhPaymentCount').innerText = count;
    document.getElementById('vhAvgPayment').innerText = formatCurrency(avg);
}

function renderPaymentHistoryBody(grouped, sortedDates, page) {
    const body = document.getElementById('vendorHistoryBody');
    if (!sortedDates || sortedDates.length === 0) {
        body.innerHTML = '<p style="color:var(--muted); text-align:center; padding:2rem;">No payments found.</p>';
        return;
    }
    const perPage = 5;
    const start = (page - 1) * perPage;
    const pageDates = sortedDates.slice(start, start + perPage);
    let html = '';
    pageDates.forEach(date => {
        const paymentsOnDate = grouped[date];
        const totalAmount = paymentsOnDate.reduce((sum, p) => sum + (p.amount || 0), 0);
        html += `
            <details class="accordion" open style="background:var(--card-bg); border:1px solid var(--border); border-radius:var(--radius-lg); margin-bottom:1rem; overflow:hidden;">
                <summary class="accordion-header" style="cursor:pointer; display:flex; align-items:center; justify-content:center; gap:1.5rem; font-size:0.9rem; padding:0.85rem 1rem; user-select:none; transition:background 0.2s;">
                    <span style="font-weight:700; font-size:1.05rem; color:var(--text);">${date}</span>
                    <span style="color:var(--muted-strong);">Payments: <strong>${paymentsOnDate.length}</strong></span>
                    <span>Total: ${formatCurrency(totalAmount)}</span>
                </summary>
                <div class="accordion-body" style="padding:0.5rem; overflow-x:auto;">
                    <table class="table" style="min-width:500px;">
                        <thead>
                            <tr>
                                ${!currentHistoryVendorId ? '<th>Vendor</th>' : ''}
                                <th>Amount</th>
                                <th>Purchase Total</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
        `;
        paymentsOnDate.forEach(p => {
            html += `
                            <tr>
                                ${!currentHistoryVendorId ? `<td>${p.vendorName || '-'}</td>` : ''}
                                <td style="font-weight:600; color:var(--ok);">${formatCurrency(p.amount || 0)}</td>
                                <td>${formatCurrency(p.purchaseBaseAmount || 0)}</td>
                                <td>
                                    <button class="btn btn-sm btn-outline" onclick="viewPurchase('${p.purchaseId}')" style="padding:2px 10px; font-size:0.75rem;">
                                        View Purchase
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
// 7. Quick Purchase (existing vendor)
// ============================================
async function loadVendorsForQuickPurchase() {
    const select = document.getElementById('qpVendorId');
    if (!select) return;
    select.innerHTML = '<option value="">Loading vendors...</option>';
    try {
        const data = await window.apiRequest('/api/vendors');
        const vendors = Array.isArray(data) ? data : (data.data || []);
        select.innerHTML = '<option value="">-- Select Vendor --</option>';
        vendors.forEach(v => {
            const opt = document.createElement('option');
            opt.value = v.id;
            opt.textContent = v.name;
            opt.dataset.phone = v.contact_info || '';
            select.appendChild(opt);
        });
    } catch (err) {
        console.error('Failed to load vendors', err);
        select.innerHTML = '<option value="">Error loading vendors</option>';
    }
}

async function openQuickPurchaseForVendor(vendorId, vendorName, vendorPhone) {
    openModal('quickPurchaseModal');
    document.getElementById('qpVendorName').value = vendorName;
    document.getElementById('qpVendorPhone').value = vendorPhone;
    loadProductsForQuickPurchase();
}

function onVendorSelect() {
    const select = document.getElementById('qpVendorId');
    const selected = select.options[select.selectedIndex];
    document.getElementById('qpVendorName').value = selected?.textContent || '';
    document.getElementById('qpVendorPhone').value = selected?.dataset?.phone || '';
}

function loadProductsForQuickPurchase() {
    const select = document.getElementById('qpStockName');
    if (!select) return;
    select.innerHTML = '<option value="">Loading products...</option>';
    window.apiRequest('/api/products?limit=1000').then(data => {
        const products = Array.isArray(data) ? data : (data.data || []);
        select.innerHTML = '<option value="">-- Select Product --</option>';
        products.forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.id;
            opt.textContent = p.name;
            opt.dataset.gst = p.gst_rate ?? 0;
            select.appendChild(opt);
        });
    }).catch(err => {
        console.error('Failed to load products', err);
        select.innerHTML = '<option value="">Error loading products</option>';
    });
}

function calculateQpTotal() {
    const qty = parseFloat(document.getElementById('qpQty').value) || 0;
    const unitPrice = parseFloat(document.getElementById('qpUnitPrice').value) || 0;
    const gstRate = parseFloat(document.getElementById('qpGstRate').value) || 0;
    const base = qty * unitPrice;
    document.getElementById('qpBaseAmount').value = base.toFixed(2);
    const gstAmount = base * (gstRate / 100);
    let display = document.getElementById('qpGstDisplay');
    if (!display) {
        display = document.createElement('div');
        display.id = 'qpGstDisplay';
        display.style.cssText = 'font-size:0.8rem; margin-top:4px; color:var(--muted-strong);';
        document.getElementById('qpGstRate').closest('.input-group').appendChild(display);
    }
    display.innerHTML = `Base: <strong>${formatCurrency(base)}</strong> &nbsp;|&nbsp; GST @${gstRate}%: <strong>${formatCurrency(gstAmount)}</strong> &nbsp;|&nbsp; Total: <strong>${formatCurrency(base + gstAmount)}</strong>`;
}

async function saveQuickPurchase() {
    const vendorName = document.getElementById('qpVendorName').value.trim();
    const vendorPhone = document.getElementById('qpVendorPhone').value.trim();
    const productSelect = document.getElementById('qpStockName');
    const productId = productSelect.value;
    const quantity = parseFloat(document.getElementById('qpQty').value) || 0;
    const unitPrice = parseFloat(document.getElementById('qpUnitPrice').value) || 0;
    const baseAmount = parseFloat(document.getElementById('qpBaseAmount').value) || 0;
    const gstRate = parseFloat(document.getElementById('qpGstRate').value) || 0;
    const amountPaid = parseFloat(document.getElementById('qpPaid').value) || 0;
    const purchaseDate = document.getElementById('qpPurchaseDate').value || new Date().toISOString().split('T')[0];

    if (!vendorName) { alert('Vendor name is required'); return; }
    if (!productId) { alert('Please select a product'); return; }
    if (quantity <= 0) { alert('Quantity must be greater than zero'); return; }
    if (unitPrice <= 0) { alert('Unit price must be greater than zero'); return; }

    const totalAmount = baseAmount + (baseAmount * gstRate / 100);
    if (amountPaid > totalAmount) { alert('Amount paid cannot exceed total amount'); return; }

    const payload = {
        vendor_name: vendorName,
        phone: vendorPhone,
        purchase_date: purchaseDate,
        base_amount: baseAmount,
        amount_paid: amountPaid,
        items: [{
            product_id: productId,
            quantity: quantity,
            unit_price: unitPrice,
            gst_rate: gstRate
        }]
    };

    const btn = document.getElementById('saveQpBtn');
    btn.disabled = true;
    btn.innerText = 'Saving...';
    try {
        const response = await window.apiRequest('/api/purchases', {
            method: 'POST',
            body: JSON.stringify(payload)
        });
        if (response && response.success) {
            alert('Purchase saved successfully');
            closeModal('quickPurchaseModal');
            if (typeof loadVendorSummaries === 'function') loadVendorSummaries();
        } else {
            alert(response?.error || 'Failed to save purchase');
        }
    } catch (err) {
        console.error(err);
        alert('Network error');
    } finally {
        btn.disabled = false;
        btn.innerText = 'Save Purchase';
    }
}

document.getElementById('qpStockName')?.addEventListener('change', function() {
    const selected = this.options[this.selectedIndex];
    const gstRate = parseFloat(selected?.dataset?.gst) || 0;
    document.getElementById('qpGstRate').value = gstRate;
    calculateQpTotal();
});

// ============================================
// 8. Initialisation
// ============================================
async function initVendorPage() {
    await loadVendorSummaries();
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
window.submitVendorPayment = submitVendorPayment;
function searchVendorHistory() {
    currentHistoryPage = 1;
    loadCurrentHistory();
}

function clearVendorHistorySearch() {
    document.getElementById('vhMonthSearch').value = '';
    document.getElementById('vhDateSearch').value = '';
    currentHistoryPage = 1;
    loadCurrentHistory();
}

window.loadProductsForQuickPurchase = loadProductsForQuickPurchase;
window.calculateQpTotal = calculateQpTotal;
window.saveQuickPurchase = saveQuickPurchase;
window.openQuickPurchaseForVendor = openQuickPurchaseForVendor;
window.switchHistoryTab = switchHistoryTab;
window.searchVendorHistory = searchVendorHistory;
window.clearVendorHistorySearch = clearVendorHistorySearch;
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('vendor_list')?.classList.contains('active')) {
        initVendorPage();
    }
});