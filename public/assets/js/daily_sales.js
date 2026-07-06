// Daily Sales Timeline

var _tlGroups = {};
var _tlPage = 1;
var _tlTotalPages = 1;
var _tlPerPage = 6;

function initDayToDaySelling() {
    _tlGroups = {};
    _tlPage = 1;
    _tlTotalPages = 1;
    var tbody = document.querySelector('#salesTimelineTable tbody');
    if (tbody) tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:2rem;color:var(--muted);">Loading...</td></tr>';

    window.apiRequest('/api/invoices?limit=5000').then(function(data) {
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
    if (!tbody) return;
    tbody.innerHTML = '';

    var dates = Object.keys(_tlGroups).sort(function(a, b) { return b.localeCompare(a); });
    _tlTotalPages = Math.max(1, Math.ceil(dates.length / _tlPerPage));

    if (dates.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" style="color:var(--muted);text-align:center;padding:2rem;">No sales found</td></tr>';
        return;
    }

    var start = (_tlPage - 1) * _tlPerPage;
    var pageDates = dates.slice(start, start + _tlPerPage);
    var globalIndex = start;

    pageDates.forEach(function(dateStr) {
        var g = _tlGroups[dateStr];
        var avg = g.count > 0 ? Math.round(g.total / g.count) : 0;
        var cls = 'bsg-' + globalIndex;
        globalIndex++;

        tbody.innerHTML += ''
            + '<tr onclick="toggleSalesBills(\'' + cls + '\')" style="cursor:pointer;">'
                + '<td style="font-weight:500;color:var(--text-strong)">' + displayDate(dateStr) + '</td>'
                + '<td style="font-weight:600">' + g.count + '</td>'
                + '<td style="font-weight:600;color:var(--ok)">\u20b9' + formatNumber(g.total) + '</td>'
                + '<td style="color:var(--muted-strong)">\u20b9' + formatNumber(avg) + '</td>'
                + '<td style="text-align:right">-</td>'
            + '</tr>';

        g.invoices.forEach(function(inv) {
            tbody.innerHTML += ''
                + '<tr class="bill-row ' + cls + '" style="display:none;background:var(--bg-hover);">'
                    + '<td style="padding-left:20px;font-weight:600;color:var(--accent);">\uD83D\uDCC4 ' + escHtml(inv.invoiceNumber || '-') + '</td>'
                    + '<td>' + escHtml(inv.customerNameSnapshot || inv.customerName || 'Walk-in') + '</td>'
                    + '<td style="color:var(--ok)">\u20b9' + formatNumber(inv.grandTotal) + '</td>'
                    + '<td style="text-align:right">'
+ '<button class="btn btn-sm" onclick="event.stopPropagation();viewInvoiceReceipt(\'' + inv.id + '\')">View</button>'
+ '<button class="btn btn-sm btn-outline" onclick="event.stopPropagation();confirmDeleteInvoice(\'' + inv.id + '\')" style="margin-left:6px;color:var(--danger);border-color:rgba(239,68,68,0.3);font-size:0.7rem;padding:2px 8px;">Delete</button>'
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
        .then(function() { closeModal('deleteBillModal'); initDayToDaySelling(); })
        .catch(function(err) { alert('Failed to delete: ' + err.message); });
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

window.initDayToDaySelling = initDayToDaySelling;
window.toggleSalesBills = toggleSalesBills;
window.viewInvoiceReceipt = viewInvoiceReceipt;
window.confirmDeleteInvoice = confirmDeleteInvoice;
window.goToPage = goToPage;
