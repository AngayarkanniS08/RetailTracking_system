// Product History Module

var _currentProductId = null;
var _productList = [];

function initProductHistory() {
    if (_productList.length === 0) {
        fetchProductList();
    } else {
        populateProductSelect(_productList);
    }
    if (_currentProductId) {
        fetchProductAnalytics(_currentProductId);
    } else {
        showProductPrompt();
    }
}

function fetchProductList() {
    var select = document.getElementById('phProductSelect');
    if (select) select.innerHTML = '<option value="">Loading products...</option>';

    window.apiRequest('/api/products')
        .then(function(data) {
            _productList = Array.isArray(data) ? data : (data.data || []);
            populateProductSelect(_productList);
            if (!_currentProductId && _productList.length > 0) {
                openProductHistory(_productList[0].id, _productList[0].name);
            }
        })
        .catch(function(err) {
            console.error('Product list error:', err);
            if (select) select.innerHTML = '<option value="">Failed to load products</option>';
        });
}

function populateProductSelect(products) {
    var select = document.getElementById('phProductSelect');
    if (!select) return;
    select.innerHTML = '<option value="">-- Select a product --</option>';
    products.forEach(function(p) {
        var opt = document.createElement('option');
        opt.value = p.id;
        opt.textContent = p.name + ' (' + (p.category || '') + ')';
        if (p.id === _currentProductId) opt.selected = true;
        select.appendChild(opt);
    });
}

function showProductPrompt() {
    var card = document.getElementById('phProductCard');
    if (!card) return;
    card.innerHTML = '<div style="padding:3rem 2rem;text-align:center;color:var(--muted);">' +
        '<div style="font-size:2.5rem;margin-bottom:0.75rem;">\u{1F50D}</div>' +
        '<div style="font-size:1.1rem;font-weight:600;margin-bottom:0.25rem;">Select a product</div>' +
        '<div style="font-size:0.9rem;">Choose a product from the dropdown above to view its analytics.</div>' +
        '</div>';
}

function openProductHistory(productId, productName) {
    if (!productId) {
        _currentProductId = null;
        showProductPrompt();
        return;
    }

    _currentProductId = productId;
    ensureCardDOM();

    var subtitleEl = document.getElementById('phSubtitle');
    if (subtitleEl) subtitleEl.textContent = 'Loading\u2026';

    var select = document.getElementById('phProductSelect');
    if (select) {
        for (var i = 0; i < select.options.length; i++) {
            if (select.options[i].value === productId) {
                select.selectedIndex = i;
                break;
            }
        }
    }

    switchTab('product_history');
    fetchProductAnalytics(productId);
}

function fetchProductAnalytics(productId) {
    window.apiRequest('/api/products/' + encodeURIComponent(productId) + '/history')
        .then(function(data) {
            renderProductAnalytics(data);
        })
        .catch(function(err) {
            console.error('Product history error:', err);
            var card = document.getElementById('phProductCard');
            if (card) {
                card.innerHTML = '<div style="padding:2rem;text-align:center;color:var(--danger);">Failed to load: ' + escH(err.message) + '</div>';
            }
        });
}

function ensureCardDOM() {
    var card = document.getElementById('phProductCard');
    if (!card) return;
    var hasHeader = card.querySelector('.si-header');
    if (hasHeader) return;

    card.innerHTML =
        '<div class="si-header">' +
        '  <div class="si-product">' +
        '    <div class="si-icon" style="background: var(--accent-subtle);" id="phIcon"></div>' +
        '    <div>' +
        '      <div class="si-name" id="phProductName">Product Name</div>' +
        '      <div class="si-meta" id="phProductMeta">Product ID &middot; Category</div>' +
        '    </div>' +
        '  </div>' +
        '  <div id="phBadges"></div>' +
        '</div>' +
        '<div style="font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:var(--muted-strong);margin:0.75rem 0 0.25rem;padding:0 0.25rem;">Sales Performance</div>' +
        '<div class="si-metrics">' +
        '  <div class="si-metric"><div class="si-metric-label">Sold (7d)</div><div class="si-metric-value" id="phSold7d">0</div></div>' +
        '  <div class="si-metric"><div class="si-metric-label">Sold (30d)</div><div class="si-metric-value" style="color:var(--ok)" id="phSold30d">0</div></div>' +
        '  <div class="si-metric"><div class="si-metric-label">Sold (90d)</div><div class="si-metric-value" id="phSold90d">0</div></div>' +
        '</div>' +
        '<div class="si-metrics">' +
        '  <div class="si-metric"><div class="si-metric-label">Avg Daily (7d)</div><div class="si-metric-value" id="phAvgDaily7d">0</div></div>' +
        '  <div class="si-metric"><div class="si-metric-label">Avg Daily (30d)</div><div class="si-metric-value" style="color:var(--info)" id="phAvgDaily30d">0</div></div>' +
        '  <div class="si-metric"><div class="si-metric-label">Avg Daily (90d)</div><div class="si-metric-value" id="phAvgDaily90d">0</div></div>' +
        '</div>' +
        '<div class="si-metrics">' +
        '  <div class="si-metric"><div class="si-metric-label">Revenue (30d)</div><div class="si-metric-value" id="phRevenue">&#x20b9;0</div></div>' +
        '  <div class="si-metric"><div class="si-metric-label">Velocity</div><div class="si-metric-value" style="color:var(--info)" id="phVelocity">0 /day</div></div>' +
        '  <div class="si-metric"><div class="si-metric-label">Trend</div><div class="si-metric-value" id="phTrend">--</div></div>' +
        '</div>' +
        '<div style="font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:var(--muted-strong);margin:1rem 0 0.25rem;padding:0 0.25rem;">Stock &amp; Supply</div>' +
        '<div class="si-metrics">' +
        '  <div class="si-metric"><div class="si-metric-label">Stock Left</div><div class="si-metric-value" id="phStockLeft">0</div></div>' +
        '  <div class="si-metric"><div class="si-metric-label">Days of Supply</div><div class="si-metric-value" id="phDaysOfSupply">&#x221e;</div></div>' +
        '  <div class="si-metric"><div class="si-metric-label">Batches</div><div class="si-metric-value" id="phBatchCount">0</div></div>' +
        '</div>' +
        '<div class="si-metrics">' +
        '  <div class="si-metric"><div class="si-metric-label">Stock Value</div><div class="si-metric-value" id="phStockValue">&#x20b9;0</div></div>' +
        '  <div class="si-metric"><div class="si-metric-label">Margin</div><div class="si-metric-value" id="phMargin">0%</div></div>' +
        '  <div class="si-metric"><div class="si-metric-label">Oldest Batch</div><div class="si-metric-value" id="phMaxBatchAge">0d</div></div>' +
        '</div>' +
        '<div class="si-metrics">' +
        '  <div class="si-metric"><div class="si-metric-label">Last Sale</div><div class="si-metric-value" id="phLastSale">--</div></div>' +
        '  <div class="si-metric"><div class="si-metric-label">First Sale</div><div class="si-metric-value" id="phFirstSale">--</div></div>' +
        '  <div class="si-metric"><div class="si-metric-label">Reorder</div><div class="si-metric-value" id="phReorderStatus">--</div></div>' +
        '</div>' +
        '<div class="si-stock-bar">' +
        '  <div class="si-stock-label"><span>Stock level</span><span id="phStockPct" style="color:var(--ok)">100%</span></div>' +
        '  <div class="si-bar-track"><div class="si-bar-fill" id="phStockBar" style="width:100%; background:var(--ok)"></div></div>' +
        '</div>' +
        '<div id="phAlert"></div>';
}

function renderProductAnalytics(data) {
    var p = data.product || {};
    var a = data.analytics || {};
    var badges = data.badges || [];
    var alert = data.alert || {};
    var unit = p.unit || 'units';

    // Header
    setText('phTitle', p.name || 'Product');
    setText('phSubtitle', (p.id || '') + ' &middot; ' + (p.category || '') + ' &middot; ' + unit);
    setText('phProductName', p.name || '-');
    setText('phProductMeta', (p.id || '') + ' &middot; ' + (p.category || ''));

    var icon = document.getElementById('phIcon');
    if (icon) icon.textContent = (p.name || '??').slice(0, 2).toUpperCase();

    // Badges
    var badgeHtml = '';
    badges.forEach(function(tag) {
        if (tag === 'high')    badgeHtml += '<span class="si-badge high">High selling</span> ';
        if (tag === 'low')     badgeHtml += '<span class="si-badge low">Low selling</span> ';
        if (tag === 'old')     badgeHtml += '<span class="si-badge old">Old stock</span> ';
        if (tag === 'dead')    badgeHtml += '<span class="si-badge" style="background:var(--danger);color:#fff;">No sales</span> ';
        if (tag === 'out')     badgeHtml += '<span class="si-badge" style="background:#333;color:#fff;">Out of stock</span> ';
        if (tag === 'reorder') badgeHtml += '<span class="si-badge" style="background:var(--warn);color:#fff;">Reorder</span> ';
    });
    document.getElementById('phBadges').innerHTML = badgeHtml || '<span style="color:var(--muted);font-size:0.85rem;">Normal</span>';

    // Sales metrics
    setText('phSold7d', a.sold_7d + ' ' + unit);
    setText('phSold30d', a.sold_30d + ' ' + unit);
    setText('phSold90d', a.sold_90d + ' ' + unit);
    setText('phAvgDaily7d', a.avg_daily_7d + ' ' + unit + '/d');
    setText('phAvgDaily30d', a.avg_daily_30d + ' ' + unit + '/d');
    setText('phAvgDaily90d', a.avg_daily_90d + ' ' + unit + '/d');
    setText('phRevenue', window.formatCurrency(a.revenue_30d || 0));
    setText('phVelocity', (a.velocity || 0) + ' ' + unit + '/day');

    // Trend
    var trendEl = document.getElementById('phTrend');
    if (trendEl) {
        if (a.trend_pct != null) {
            var arrow = a.trend_pct >= 0 ? '&#x2191;' : '&#x2193;';
            var color = a.trend_pct >= 0 ? 'var(--ok)' : 'var(--danger)';
            trendEl.innerHTML = '<span style="color:' + color + ';font-weight:700;">' + arrow + ' ' + Math.abs(a.trend_pct) + '%</span>';
        } else {
            trendEl.textContent = '--';
            trendEl.style.color = 'var(--muted)';
        }
    }

    // Stock metrics
    setText('phStockLeft', a.stock_left + ' ' + unit);
    setText('phDaysOfSupply', a.days_of_supply != null ? a.days_of_supply + ' days' : '\u221e');
    setText('phBatchCount', a.batch_count || 0);
    setText('phStockValue', window.formatCurrency(a.stock_value || 0));
    setText('phMargin', (a.margin_pct || 0) + '%');
    setText('phMaxBatchAge', (a.max_batch_age_days || 0) + 'd');

    // Dates
    setText('phLastSale', a.last_sale_date ? fmtDate(a.last_sale_date) : '--');
    setText('phFirstSale', a.first_sale_date ? fmtDate(a.first_sale_date) : '--');

    // Reorder status
    var rsEl = document.getElementById('phReorderStatus');
    if (rsEl) {
        var rs = a.reorder_status || 'ok';
        var rsLabels = {
            'ok':           { text: 'OK', color: 'var(--ok)' },
            'reorder_soon': { text: 'Reorder soon', color: 'var(--warn)' },
            'reorder_now':  { text: 'Reorder NOW', color: 'var(--danger)' },
            'emergency':    { text: 'EMERGENCY', color: 'var(--danger)' },
            'out_of_stock': { text: 'OUT OF STOCK', color: '#333' }
        };
        var rl = rsLabels[rs] || { text: rs, color: 'var(--muted)' };
        rsEl.innerHTML = '<span style="color:' + rl.color + ';font-weight:700;">' + rl.text + '</span>';
    }

    // Stock bar
    var stockPct = a.stock_pct != null ? a.stock_pct : 100;
    var barColor = stockPct <= 10 ? 'var(--danger)' : (stockPct <= 30 ? 'var(--warn)' : 'var(--ok)');
    var pctEl = document.getElementById('phStockPct');
    if (pctEl) {
        pctEl.textContent = stockPct + '%';
        pctEl.style.color = barColor;
    }
    var barEl = document.getElementById('phStockBar');
    if (barEl) {
        barEl.style.width = stockPct + '%';
        barEl.style.background = barColor;
    }

    // Alert
    var alertHtml = '';
    if (alert.type === 'critical') {
        alertHtml = '<div class="si-alert critical"><span style="margin-right:6px;">&#x2699;&#xfe0f;</span>' + escH(alert.message) + '</div>';
    } else if (alert.type === 'warning') {
        alertHtml = '<div class="si-alert warning"><span style="margin-right:6px;">&#x26a0;&#xfe0f;</span>' + escH(alert.message) + '</div>';
    } else if (alert.type === 'good') {
        alertHtml = '<div class="si-alert good"><span style="margin-right:6px;">&#x2705;</span>' + escH(alert.message) + '</div>';
    }
    document.getElementById('phAlert').innerHTML = alertHtml;
}

function fmtDate(dateStr) {
    if (!dateStr) return '--';
    try {
        var d = new Date(dateStr);
        return d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
    } catch(e) {
        return dateStr;
    }
}

function escH(str) {
    if (str == null || str === '') return '';
    var d = document.createElement('div');
    d.textContent = String(str);
    return d.innerHTML;
}

// Patch switchTab
var _origSwitchTabPH = window.switchTab;
window.switchTab = function(sectionId) {
    _origSwitchTabPH(sectionId);
    if (sectionId === 'product_history') {
        initProductHistory();
    }
};

window.openProductHistory = openProductHistory;
