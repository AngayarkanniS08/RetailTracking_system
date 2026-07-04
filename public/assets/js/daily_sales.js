// Daily Sales Timeline — paginated by month

var _tlGroups = {};
var _tlLoading = false;
var _tlDone = false;

function initDayToDaySelling() {
    _tlGroups = {};
    _tlLoading = false;
    _tlDone = false;
    loadOlder();
}

function loadOlder() {
    if (_tlLoading || _tlDone) return;
    _tlLoading = true;

    var toDate;
    var allDates = Object.keys(_tlGroups).sort();
    if (allDates.length === 0) {
        toDate = new Date();
    } else {
        toDate = new Date(allDates[0] + 'T00:00:00');
        toDate.setDate(toDate.getDate() - 1);
    }

    var fromDate = new Date(toDate.getFullYear(), toDate.getMonth() - 2, 1);
    var pFrom = toDateStr(fromDate);
    var pTo = toDateStr(toDate);

    renderSalesTimeline();

    window.apiRequest('/api/invoices?date_from=' + pFrom + '&date_to=' + pTo + '&limit=500')
        .then(function(data) {
            _tlLoading = false;
            var invoices = data.invoices || data.data || data || [];
            var completed = invoices.filter(function(inv) {
                return inv.invoiceStatus === 'completed';
            });
            if (completed.length === 0) {
                _tlDone = true;
            } else {
                var groups = groupInvoicesByDate(completed);
                mergeGroups(groups);
            }
            renderSalesTimeline();
        })
        .catch(function(err) {
            _tlLoading = false;
            console.error('Failed to load:', err);
            renderSalesTimeline();
        });
}

// ── pure helpers ──

function groupInvoicesByDate(invoices) {
    var groups = {};
    (invoices || []).forEach(function(inv) {
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

function mergeGroups(source) {
    Object.keys(source).forEach(function(key) {
        if (_tlGroups[key]) {
            _tlGroups[key].invoices = _tlGroups[key].invoices.concat(source[key].invoices);
            _tlGroups[key].count += source[key].count;
            _tlGroups[key].total += source[key].total;
        } else {
            _tlGroups[key] = source[key];
        }
    });
}

function renderSalesTimeline() {
    var tbody = document.querySelector('#salesTimelineTable tbody');
    if (!tbody) return;
    tbody.innerHTML = '';

    var dates = Object.keys(_tlGroups).sort(function(a, b) { return b.localeCompare(a); });

    if (dates.length === 0 && !_tlLoading) {
        tbody.innerHTML = '<tr><td colspan="5" style="color:var(--muted);text-align:center;padding:2rem;">No sales found</td></tr>';
        return;
    }

    dates.forEach(function(dateStr, index) {
        var g = _tlGroups[dateStr];
        var avg = g.count > 0 ? Math.round(g.total / g.count) : 0;
        var cls = 'bsg-' + index;

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

    if (_tlLoading) {
        tbody.innerHTML += '<tr><td colspan="5" style="text-align:center;padding:1rem;color:var(--muted);">Loading older bills...</td></tr>';
    } else if (!_tlDone) {
        tbody.innerHTML += ''
            + '<tr>'
                + '<td colspan="5" style="text-align:center;padding:1rem;">'
                    + '<button class="btn btn-outline btn-sm" onclick="loadOlder()" style="color:var(--muted);">Load Older</button>'
                + '</td>'
            + '</tr>';
    } else {
        tbody.innerHTML += '<tr><td colspan="5" style="text-align:center;padding:1rem;color:var(--muted);font-size:0.8rem;">\u2014 All bills loaded \u2014</td></tr>';
    }
}

// ── interaction ──

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

// ── helpers ──

function toDateStr(d) {
    var y = d.getFullYear();
    var m = String(d.getMonth() + 1).padStart(2, '0');
    var day = String(d.getDate()).padStart(2, '0');
    return y + '-' + m + '-' + day;
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
window.loadOlder = loadOlder;
