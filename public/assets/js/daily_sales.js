// Daily Sales Timeline

var _tlGroups = {};
var _tlPage = 1;
var _tlTotalPages = 1;
var _tlPerPage = 6;
var _tlSearchTerm = '';
var _tlSearchTimer = null;

function initDayToDaySelling() {
    _tlGroups = {};
    _tlPage = 1;
    _tlTotalPages = 1;
    var tbody = document.querySelector('#salesTimelineTable tbody');
    if (tbody) tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:2rem;color:var(--muted);">Loading...</td></tr>';

    var url = '/api/invoices?limit=5000';
    if (_tlSearchTerm) url += '&search=' + encodeURIComponent(_tlSearchTerm);

    window.apiRequest(url).then(function(data) {
        var invoices = data.invoices || data.data || data || [];
        _tlGroups = groupInvoicesByDate(invoices);
        renderSalesTimeline();
        renderPagination();
    }).catch(function(err) {
        console.error('Sales Timeline error:', err);
        var tbody = document.querySelector('#salesTimelineTable tbody');
        if (tbody) tbody.innerHTML = '<tr><td colspan="5" style="color:var(--muted);text-align:center;padding:2rem;">Failed to load sales data</td></tr>';
    });
}

function onSalesSearchInput() {
    clearTimeout(_tlSearchTimer);
    _tlSearchTimer = setTimeout(function() {
        var input = document.getElementById('salesSearch');
        var term = input ? input.value.trim() : '';
        if (term === _tlSearchTerm) return;
        _tlSearchTerm = term;
        _tlPage = 1;
        initDayToDaySelling();
    }, 300);
}

function goToPage(page) {
    var dates = Object.keys(_tlGroups).sort(function(a, b) { return b.localeCompare(a); });
    _tlTotalPages = Math.max(1, Math.ceil(dates.length / _tlPerPage));
    if (page < 1) page = 1;
    if (page > _tlTotalPages) page = _tlTotalPages;
    _tlPage = page;
    renderSalesTimeline();
    renderPagination();
}

function renderPagination() {
    var container = document.getElementById('salesTimelinePagination');
    if (!container) return;

    if (_tlTotalPages <= 1) {
        container.style.display = 'none';
        return;
    }
    container.style.display = 'flex';
    container.innerHTML = ''
        + '<button class="pagination-btn" id="prevTimelineBtn"'
            + (_tlPage <= 1 ? ' disabled' : '')
        + '>\u2190 Previous</button>'
        + '<span class="pagination-info">Page ' + _tlPage + ' of ' + _tlTotalPages + '</span>'
        + '<button class="pagination-btn" id="nextTimelineBtn"'
            + (_tlPage >= _tlTotalPages ? ' disabled' : '')
        + '>Next \u2192</button>';

    document.getElementById('prevTimelineBtn')?.addEventListener('click', function() {
        if (_tlPage > 1) goToPage(_tlPage - 1);
    });
    document.getElementById('nextTimelineBtn')?.addEventListener('click', function() {
        if (_tlPage < _tlTotalPages) goToPage(_tlPage + 1);
    });
}

function groupInvoicesByDate(invoices) {
    var groups = {};
    (invoices || []).forEach(function(inv) {
        if (inv.invoiceStatus !== 'completed') return;
        var raw = inv.billedAt || inv.createdAt;
        if (!raw) return;
        var d = new Date(raw);
        if (isNaN(d.getTime())) return;
        var key = d.toISOString().split('T')[0];
        if (!groups[key]) groups[key] = { invoices: [], count: 0, total: 0 };
        groups[key].invoices.push(inv);
        groups[key].count++;
        groups[key].total += Number(inv.grandTotal || 0);
    });
    return groups;
}

function renderSalesTimeline() {
    var tbody = document.querySelector('#salesTimelineTable tbody');
    var emptyState = document.getElementById('salesEmptyState');
    var table = document.getElementById('salesTimelineTable');
    if (!tbody) return;
    tbody.innerHTML = '';

    var dates = Object.keys(_tlGroups).sort(function(a, b) { return b.localeCompare(a); });
    _tlTotalPages = Math.max(1, Math.ceil(dates.length / _tlPerPage));

    // Calculate KPI Totals
    var totalRevenue = 0;
    var totalBillsCount = 0;
    dates.forEach(function(d) {
        totalRevenue += _tlGroups[d].total;
        totalBillsCount += _tlGroups[d].count;
    });

    var todayStr = new Date().toISOString().split('T')[0];
    var todayRevenue = _tlGroups[todayStr] ? _tlGroups[todayStr].total : 0;
    var avgBill = totalBillsCount > 0 ? Math.round(totalRevenue / totalBillsCount) : 0;

    var elToday = document.getElementById('kpiTodaySales');
    var elBills = document.getElementById('kpiTotalBills');
    var elAvg = document.getElementById('kpiAvgBill');
    var elRatio = document.getElementById('kpiRatio');

    if (elToday) elToday.innerText = '₹' + formatNumber(todayRevenue);
    if (elBills) elBills.innerText = totalBillsCount;
    if (elAvg) elAvg.innerText = '₹' + formatNumber(avgBill);
    if (elRatio) elRatio.innerText = totalBillsCount > 0 ? '100% Cash' : '0% Sales';

    if (dates.length === 0) {
        if (table) table.style.display = 'none';
        if (emptyState) emptyState.style.display = 'flex';
        return;
    }

    if (table) table.style.display = 'table';
    if (emptyState) emptyState.style.display = 'none';

    var start = (_tlPage - 1) * _tlPerPage;
    var pageDates = dates.slice(start, start + _tlPerPage);
    var globalIndex = start;

    pageDates.forEach(function(dateStr) {
        var g = _tlGroups[dateStr];
        var avg = g.count > 0 ? Math.round(g.total / g.count) : 0;
        var cls = 'bsg-' + globalIndex;
        globalIndex++;

        tbody.innerHTML += ''
            + '<tr onclick="toggleSalesBills(\'' + cls + '\')" style="cursor:pointer; background: var(--surface-container-low); border-bottom: 1px solid var(--border);">'
                + '<td style="padding:14px 16px; font-weight:600; color:var(--text-strong)">' + displayDate(dateStr) + '</td>'
                + '<td style="padding:14px 16px; font-weight:600; color:var(--accent);">Summary Row (' + g.count + ' bills)</td>'
                + '<td style="padding:14px 16px; color:var(--muted);">-</td>'
                + '<td style="padding:14px 16px; color:var(--muted);">' + g.count + ' orders</td>'
                + '<td style="padding:14px 16px; font-weight:700; color:var(--success);">\u20b9' + formatNumber(g.total) + '</td>'
                + '<td style="padding:14px 16px;"><span class="badge rounded-pill bg-success">CASH</span></td>'
                + '<td style="padding:14px 16px;"><span class="badge rounded-pill bg-success">COMPLETED</span></td>'
                + '<td style="padding:14px 16px; text-align:right;"><span style="font-size:0.75rem; color:var(--accent); text-decoration:underline;">View Details</span></td>'
            + '</tr>';

        g.invoices.forEach(function(inv) {
            var isCompletable = inv.invoiceStatus === 'completed';
            var pMode = (inv.paymentMode || inv.payment_mode || 'CASH').toUpperCase();
            var st    = (inv.invoiceStatus || inv.invoice_status || 'COMPLETED').toUpperCase();

            var modeBg = pMode === 'CREDIT' ? 'bg-warning text-dark' : (pMode === 'CARD' ? 'bg-primary' : (pMode === 'UPI' ? 'bg-info text-dark' : 'bg-success'));
            var stBg   = st === 'CANCELLED' ? 'bg-danger' : (st === 'PENDING' || st === 'PARTIAL' ? 'bg-warning text-dark' : 'bg-success');

            tbody.innerHTML += ''
                + '<tr class="bill-row ' + cls + '" style="display:none; background:var(--card); border-bottom: 1px solid var(--border);">'
                    + '<td style="padding: 12px 16px 12px 28px; color: var(--muted); font-size: 0.8rem;">' + (inv.billedAt ? new Date(inv.billedAt).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : '-') + '</td>'
                    + '<td style="padding: 12px 16px; font-weight:600; color:var(--accent); font-family: var(--font-mono, monospace);">\uD83D\uDCC4 ' + escHtml(inv.invoiceNumber || '-') + '</td>'
                    + '<td style="padding: 12px 16px; color: var(--text-strong);">' + escHtml(inv.customerNameSnapshot || inv.customerName || 'Walk-in Customer') + '</td>'
                    + '<td style="padding: 12px 16px; color: var(--muted);">' + (inv.itemsCount || inv.items?.length || 1) + ' items</td>'
                    + '<td style="padding: 12px 16px; font-weight:600; color:var(--success);">\u20b9' + formatNumber(inv.grandTotal) + '</td>'
                    + '<td style="padding: 12px 16px;"><span class="badge rounded-pill ' + modeBg + '">' + escHtml(pMode) + '</span></td>'
                    + '<td style="padding: 12px 16px;"><span class="badge rounded-pill ' + stBg + '">' + escHtml(st) + '</span></td>'
                    + '<td style="padding: 12px 16px; text-align:right;">'
                        + '<button class="btn btn-sm btn-outline" onclick="event.stopPropagation();viewInvoiceReceipt(\'' + inv.id + '\')" style="font-size:0.75rem; padding:3px 8px;">View</button>'
                        + (isCompletable ? '<button class="btn btn-sm btn-outline" onclick="event.stopPropagation();openReturnModal(\'' + inv.id + '\')" style="margin-left:6px; font-size:0.75rem; padding:3px 8px;">Return</button>' : '')
                    + '</td>'
                + '</tr>';
        });
    });
}

function toggleSalesBills(cls) {
    var rows = document.querySelectorAll('.' + cls);
    rows.forEach(function(row) {
        row.style.display = row.style.display === 'none' ? 'table-row' : 'none';
    });
}

function viewInvoiceReceipt(invoiceId) {
    var token = localStorage.getItem('auth_token');
    if (!token) return;
    var url = window.location.protocol + '//' + window.location.hostname + ':8081/api/invoices/' + invoiceId + '/receipt?token=' + encodeURIComponent(token);
    window.open(url, '_blank');
}

function confirmDeleteInvoice(invoiceId) {
    var modal = document.getElementById('deleteBillModal');
    if (modal) {
        modal.classList.add('active');
        var msg = document.getElementById('deleteBillMessage');
        if (msg) msg.textContent = 'This invoice will be cancelled and stock will be restored.';
        var btn = document.getElementById('deleteBillConfirmBtn');
        if (btn) btn.onclick = function() { cancelInvoice(invoiceId); };
    } else {
        if (!confirm('Delete this invoice? Stock will be restored.')) return;
        cancelInvoice(invoiceId);
    }
}

function cancelInvoice(invoiceId) {
    window.apiRequest('/api/invoices/' + invoiceId + '/cancel', { method: 'POST' })
        .then(function() {
            if (window.notify) window.notify.success('Invoice cancelled successfully');
            else showToast('Invoice cancelled successfully', 'ok');
            closeModal('deleteBillModal');
            initDayToDaySelling();
        })
        .catch(function(err) {
            if (window.notify) window.notify.error('Failed to delete: ' + err.message);
            else showToast('Failed to delete: ' + err.message, 'danger');
        });
}

function formatNumber(n) {
    return Number(n).toLocaleString('en-IN', { maximumFractionDigits: 2 });
}

function displayDate(dateStr) {
    var d = new Date(dateStr + 'T00:00:00');
    return d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}

function escHtml(str) {
    if (str == null) return '';
    var d = document.createElement('div');
    d.textContent = String(str);
    return d.innerHTML;
}

// ── Return Items ────────────────────────────────────────

function openReturnModal(invoiceId) {
    window.apiRequest('/api/invoices/' + invoiceId)
        .then(function(inv) {
            document.getElementById('returnInvoiceId').value = inv.id;
            document.getElementById('returnInvoiceNumber').textContent = inv.invoiceNumber || '-';
            document.getElementById('returnCustomerName').textContent = inv.customerNameSnapshot || inv.customerName || 'Walk-in';
            document.getElementById('returnDate').textContent = inv.billedAt ? new Date(inv.billedAt).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }) : '-';

            var tbody = document.getElementById('returnItemsBody');
            tbody.innerHTML = '';
            (inv.items || []).forEach(function(item) {
                var returnable = Number(item.quantity) - Number(item.alreadyReturned || 0);
                if (returnable < 0) returnable = 0;
                var alreadyRet = Number(item.alreadyReturned || 0);
                var tr = document.createElement('tr');
                tr.style.borderBottom = '1px solid var(--border)';
                tr.innerHTML = ''
                    + '<td style="padding:8px 4px;font-weight:500;">' + escHtml(item.productNameSnapshot) + '</td>'
                    + '<td style="text-align:center;padding:8px 4px;">' + formatNumber(item.quantity) + '</td>'
                    + '<td style="text-align:center;padding:8px 4px;color:var(--muted);">' + (alreadyRet > 0 ? formatNumber(alreadyRet) : '-') + '</td>'
                    + '<td style="text-align:center;padding:8px 4px;">'
                        + (returnable > 0
                            ? '<input type="number" class="return-qty" data-item-id="' + item.id + '" data-unit-price="' + item.unitPrice + '" data-max="' + returnable + '" value="0" min="0" max="' + returnable + '" style="width:60px;padding:4px;text-align:center;border:1px solid var(--border);border-radius:4px;background:var(--bg);color:var(--text);" oninput="returnCalcRefund(this)">'
                            : '<span style="color:var(--muted);font-size:0.75rem;">Fully Returned</span>')
                    + '</td>'
                    + '<td style="text-align:right;padding:8px 4px;">'
                        + '<input type="number" class="return-refund" data-item-id="' + item.id + '" value="0" min="0" style="width:90px;padding:4px;text-align:right;border:1px solid var(--border);border-radius:4px;background:var(--bg);color:var(--text);" oninput="returnCalcQty(this)">'
                    + '</td>'
                    + '<td style="text-align:center;padding:8px 4px;font-size:0.7rem;color:var(--muted);">'
                        + escHtml(item.unitSnapshot || '') + ' × ₹' + formatNumber(item.unitPrice)
                    + '</td>';
                tbody.appendChild(tr);

                var refundInput = tr.querySelector('.return-refund');
                if (refundInput) {
                    var qtyInput = tr.querySelector('.return-qty');
                    refundInput.value = qtyInput ? (parseFloat(qtyInput.value || 0) * item.unitPrice).toFixed(2) : '0.00';
                }
            });

            document.getElementById('returnReason').value = '';
            openModal('returnItemsModal');
        })
        .catch(function(err) {
            if (window.notify) window.notify.error('Failed to load invoice: ' + err.message);
            else showToast('Failed to load invoice: ' + err.message, 'danger');
        });
}

function returnCalcRefund(el) {
    var qty = parseFloat(el.value) || 0;
    var maxQty = parseFloat(el.dataset.max) || 0;
    if (qty > maxQty) { qty = maxQty; el.value = maxQty; }
    var unitPrice = parseFloat(el.dataset.unitPrice) || 0;
    var refundInput = el.closest('tr').querySelector('.return-refund');
    if (refundInput) refundInput.value = (qty * unitPrice).toFixed(2);
}

function returnCalcQty(el) {
    var tr = el.closest('tr');
    var qtyInput = tr.querySelector('.return-qty');
    if (!qtyInput) return;
    var refund = parseFloat(el.value) || 0;
    var unitPrice = parseFloat(qtyInput.dataset.unitPrice) || 0;
    if (unitPrice > 0) {
        var qty = Math.round(refund / unitPrice);
        var maxQty = parseFloat(qtyInput.dataset.max) || 0;
        if (qty > maxQty) qty = maxQty;
        qtyInput.value = qty;
    }
}

function submitReturn() {
    var invoiceId = document.getElementById('returnInvoiceId').value;
    var reason = document.getElementById('returnReason').value.trim();

    if (!reason || reason.length < 3) {
        if (window.notify) window.notify.warning('Please enter a return reason (min 3 characters)');
        else showToast('Please enter a return reason (min 3 characters)', 'warn');
        return;
    }

    var qtyInputs = document.querySelectorAll('#returnItemsBody .return-qty');
    var items = [];

    qtyInputs.forEach(function(qtyInput) {
        var qty = parseFloat(qtyInput.value) || 0;
        if (qty <= 0) return;
        var itemId = qtyInput.dataset.itemId;
        var tr = qtyInput.closest('tr');
        var refundInput = tr ? tr.querySelector('.return-refund') : null;
        var refund = refundInput ? (parseFloat(refundInput.value) || 0) : 0;
        items.push({
            invoice_item_id: itemId,
            qty_returned: qty,
            refund_amount: refund
        });
    });

    if (items.length === 0) {
        if (window.notify) window.notify.warning('No items selected for return');
        else showToast('No items selected for return', 'warn');
        return;
    }

    var modal = document.getElementById('returnItemsModal');
    var submitBtn = modal ? modal.querySelector('.btn-primary') : null;
    var closeBtn = modal ? modal.querySelector('.close-btn, .btn-close, [data-close]') : null;
    if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Processing...'; }
    if (closeBtn) closeBtn.style.pointerEvents = 'none';
    if (modal) modal.style.pointerEvents = 'none';

    window.apiRequest('/api/invoices/' + invoiceId + '/return', {
        method: 'POST',
        body: JSON.stringify({ items: items, reason: reason })
    })
    .then(function(data) {
        closeModal('returnItemsModal');
        if (window.notify) window.notify.success('Return processed successfully');
        if (data && data.warning) {
            if (window.notify) window.notify.warning(data.warning);
            else showToast(data.warning, 'warn');
        }
        if (data && data.stock_warning) {
            if (window.notify) window.notify.warning('⚠ Stock note: ' + data.stock_warning + ' — please adjust inventory manually.');
            else showToast('⚠ Stock note: ' + data.stock_warning + ' — please adjust inventory manually.', 'warn');
        }
        initDayToDaySelling();
    })
    .catch(function(err) {
        if (window.notify) window.notify.error('Return failed: ' + err.message);
        else showToast('Return failed: ' + err.message, 'danger');
        if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Submit Return'; }
        if (closeBtn) closeBtn.style.pointerEvents = '';
        if (modal) modal.style.pointerEvents = '';
    });
}

window.initDayToDaySelling = initDayToDaySelling;
window.toggleSalesBills = toggleSalesBills;
window.viewInvoiceReceipt = viewInvoiceReceipt;
window.confirmDeleteInvoice = confirmDeleteInvoice;
window.goToPage = goToPage;
window.openReturnModal = openReturnModal;
window.submitReturn = submitReturn;
window.returnCalcRefund = returnCalcRefund;
window.returnCalcQty = returnCalcQty;
