// Dashboard Module

function initDashboard() {
    fetchDashboardStats();
    fetchStockIntel();
}

function fetchDashboardStats() {
    var statsGrid = document.getElementById('reportsStats');
    if (statsGrid) statsGrid.innerHTML = '<div style="grid-column:1/-1;text-align:center;color:var(--muted);padding:1rem;">Loading...</div>';

    window.apiRequest('/api/dashboard/stats').then(function(data) {
        populateTimeCards(data);
        populatePurchaseCards(data);
        renderStatsGrid(data);
    }).catch(function(err) {
        console.error('Dashboard stats error:', err);
        if (statsGrid) statsGrid.innerHTML = '<div style="grid-column:1/-1;text-align:center;color:var(--danger);padding:1rem;">Failed to load stats</div>';
    });
}

function fetchStockIntel() {
    window.apiRequest('/api/dashboard/stock-intel').then(function(data) {
        renderHighSelling(data.high_selling || []);
        renderLowSelling(data.low_selling || []);
        renderOldStock(data.old_stock || []);
    }).catch(function(err) {
        console.error('Stock intel error:', err);
    });
}

function populateTimeCards(data) {
    setText('tcTodayRev', formatCurrency(data.today?.revenue));
    setText('tcTodayBills', data.today?.bills ?? 0);
    setText('tcTodayAvg', formatCurrency(data.today?.avg));

    setText('tcWeekRev', formatCurrency(data.week?.revenue));
    setText('tcWeekBills', data.week?.bills ?? 0);
    setText('tcWeekAvg', formatCurrency(data.week?.avg));

    setText('tcMonthRev', formatCurrency(data.month?.revenue));
    setText('tcMonthBills', data.month?.bills ?? 0);
    setText('tcMonthAvg', formatCurrency(data.month?.avg));
}

function populatePurchaseCards(data) {
    setText('pcWeekAmount', formatCurrency(data.purchase_week?.amount));
    setText('pcWeekPurchases', data.purchase_week?.count ?? 0);
    setText('pcWeekPaid', formatCurrency(data.purchase_week?.paid));

    setText('pcMonthAmount', formatCurrency(data.purchase_month?.amount));
    setText('pcMonthPurchases', data.purchase_month?.count ?? 0);
    setText('pcMonthPaid', formatCurrency(data.purchase_month?.paid));
}

function renderStatsGrid(data) {
    var container = document.getElementById('reportsStats');
    if (!container) return;

    var totalBills = data.total_bills ?? 0;
    var outstanding = data.outstanding_credit ?? 0;
    var stockValue = data.stock_value ?? 0;

    container.innerHTML = ''
        + '<div class="stat-card"><div class="stat-label">Bills Generated</div><div class="stat-value">' + totalBills + '</div></div>'
        + '<div class="stat-card"><div class="stat-label">Customer Kadan</div><div class="stat-value" style="color:var(--danger)">' + formatCurrency(outstanding) + '</div></div>'
        + '<div class="stat-card"><div class="stat-label">Current Stock Value</div><div class="stat-value" style="color:var(--info)">' + formatCurrency(stockValue) + '</div></div>';
}

function renderHighSelling(items) {
    var tbody = document.querySelector('#highSellingTable tbody');
    if (!tbody) return;

    if (!items || items.length === 0) {
        tbody.innerHTML = '<tr><td colspan="3" class="text-center">No sales data yet</td></tr>';
        return;
    }

    tbody.innerHTML = '';
    items.forEach(function(p) {
        tbody.innerHTML += ''
            + '<tr>'
                + '<td style="font-weight:500;color:var(--text-strong)">' + escHtml(p.name) + '</td>'
                + '<td style="font-weight:600;color:var(--ok)">' + (p.qty_sold ?? 0) + '</td>'
                + '<td>' + formatCurrency(p.revenue ?? 0) + '</td>'
            + '</tr>';
    });
}

function renderLowSelling(items) {
    var tbody = document.querySelector('#lowSellingTable tbody');
    if (!tbody) return;

    if (!items || items.length === 0) {
        tbody.innerHTML = '<tr><td colspan="3" class="text-center">No low selling data</td></tr>';
        return;
    }

    tbody.innerHTML = '';
    items.forEach(function(p) {
        tbody.innerHTML += ''
            + '<tr>'
                + '<td style="font-weight:500;color:var(--text-strong)">' + escHtml(p.name) + '</td>'
                + '<td style="font-weight:600;color:var(--warn)">' + (p.qty_sold ?? 0) + '</td>'
                + '<td>' + formatCurrency(p.revenue ?? 0) + '</td>'
            + '</tr>';
    });
}

function renderOldStock(items) {
    var tbody = document.querySelector('#oldStockTable tbody');
    if (!tbody) return;

    if (!items || items.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center">No old stock</td></tr>';
        return;
    }

    tbody.innerHTML = '';
    items.forEach(function(b) {
        var ageColor = b.age_days > 15 ? 'var(--danger)' : 'var(--warn)';
        tbody.innerHTML += ''
            + '<tr>'
                + '<td style="font-weight:500">' + escHtml(b.name) + '</td>'
                + '<td style="color:var(--muted)">' + escHtml(b.batch) + '</td>'
                + '<td style="font-weight:700;color:' + ageColor + '">' + (b.age_days ?? 0) + 'd</td>'
                + '<td>' + (b.qty ?? 0) + '</td>'
            + '</tr>';
    });
}

// ── Helpers ────────────────────────────────────────────────────────────────

function setText(id, value) {
    var el = document.getElementById(id);
    if (el) el.innerText = value == null ? '' : String(value);
}

function escHtml(str) {
    if (str == null) return '';
    var d = document.createElement('div');
    d.textContent = String(str);
    return d.innerHTML;
}

// ── Exports ────────────────────────────────────────────────────────────────
window.initDashboard = initDashboard;
