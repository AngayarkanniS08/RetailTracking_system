// Product History Module — redesigned velocity analytics
var _currentProductId = null;
var _productList = [];
var _productUnit = 'units';

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

    window.apiRequest('/api/products/with-stock')
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
            showProductError('Could not load product list. Make sure the API server is running on port 8081.');
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
        '<div style="font-size:2.5rem;margin-bottom:0.75rem;">🔍</div>' +
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
    if (subtitleEl) subtitleEl.textContent = 'Loading…';

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
            var msg = friendlyError(err.message);
            showProductError(msg + '<br><button class="btn btn-outline btn-sm" style="margin-top:10px;" onclick="fetchProductAnalytics(\'' + escHtmlAttr(productId) + '\')">Retry</button>');
        });
}

function showProductError(html) {
    var card = document.getElementById('phProductCard');
    if (card) {
        card.innerHTML = '<div style="padding:2rem;text-align:center;color:var(--danger);">' + html + '</div>';
    }
}

function friendlyError(msg) {
    if (!msg) return 'Unknown error';
    if (msg.indexOf('original_quantity') !== -1) return 'Database schema missing column. Run: php Database/Migrate.php';
    if (msg.indexOf('not found') !== -1 || msg.indexOf('404') !== -1) return 'Product data not available. It may have been deleted.';
    if (msg.indexOf('Connection refused') !== -1 || msg.indexOf('Network') !== -1) return 'Cannot reach API server on port 8081. Is it running?';
    if (msg.indexOf('Unauthorized') !== -1 || msg.indexOf('401') !== -1) return 'Session expired. Please log in again.';
    return 'Something went wrong. Please try again.';
}

function ensureCardDOM() {
    var card = document.getElementById('phProductCard');
    if (!card) return;
    // If classification block already exists, DOM is intact
    if (card.querySelector('.ph-class-block')) return;

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
        '<div class="ph-class-block" id="phClassBlock">' +
        '  <div class="ph-class-title">Classification — based on velocity</div>' +
        '  <div class="ph-class-pills" id="phClassPills"></div>' +
        '  <div class="ph-class-reason" id="phClassReason"></div>' +
        '</div>' +
        '<div class="ph-hero-row">' +
        '  <div class="ph-hero-card accent">' +
        '    <div class="ph-hero-badge" id="phHeroBadge"></div>' +
        '    <div class="ph-hero-lbl">Daily velocity (30d avg)</div>' +
        '    <div class="ph-hero-val" style="color:var(--info)" id="phHeroVelocity">--</div>' +
        '    <div class="ph-hero-sub" id="phHeroVelUnit">units per day</div>' +
        '  </div>' +
        '  <div class="ph-hero-card">' +
        '    <div class="ph-hero-lbl">Days of supply left</div>' +
        '    <div class="ph-hero-val" id="phHeroDos">&infin;</div>' +
        '    <div class="ph-hero-sub">days before reorder needed</div>' +
        '  </div>' +
        '  <div class="ph-hero-card">' +
        '    <div class="ph-hero-lbl">Revenue (30d)</div>' +
        '    <div class="ph-hero-val" id="phHeroRevenue">₹0</div>' +
        '    <div class="ph-hero-sub">from <span id="phHeroSoldUnits">0</span> units sold</div>' +
        '  </div>' +
        '</div>' +
        '<div class="ph-vel-block">' +
        '  <div class="ph-vel-title">Velocity detail — how fast is it selling?</div>' +
        '  <div class="ph-vel-row">' +
        '    <div class="ph-vel-cell">' +
        '      <div class="ph-vel-lbl">Last 7 days</div>' +
        '      <div class="ph-vel-val" style="color:var(--ok)" id="phVel7">-- /day</div>' +
        '      <div class="ph-vel-desc"><span id="phVel7Sold">0</span> units sold</div>' +
        '    </div>' +
        '    <div class="ph-vel-cell">' +
        '      <div class="ph-vel-lbl">Last 30 days</div>' +
        '      <div class="ph-vel-val" style="color:var(--info)" id="phVel30">-- /day</div>' +
        '      <div class="ph-vel-desc"><span id="phVel30Sold">0</span> units sold</div>' +
        '    </div>' +
        '    <div class="ph-vel-cell">' +
        '      <div class="ph-vel-lbl">Catalog average</div>' +
        '      <div class="ph-vel-val" style="color:var(--muted-strong)" id="phVelCat">-- /day</div>' +
        '      <div class="ph-vel-desc">all products avg</div>' +
        '    </div>' +
        '  </div>' +
        '' +
        '</div>' +
        '<div class="ph-stock-block">' +
        '  <div class="ph-stock-title">Stock status</div>' +
        '  <div class="ph-stock-row">' +
        '    <div class="ph-sc"><div class="ph-sc-lbl">Stock left</div><div class="ph-sc-val" id="phStockLeftVal">--</div></div>' +
        '    <div class="ph-sc"><div class="ph-sc-lbl">Oldest batch</div><div class="ph-sc-val" id="phOldestBatchVal">--</div></div>' +
        '    <div class="ph-sc"><div class="ph-sc-lbl">Margin</div><div class="ph-sc-val" id="phMarginVal">--</div></div>' +
        '  </div>' +
        '  <div class="ph-bar-track"><div class="ph-bar-fill" id="phStockBarFill" style="width:100%"></div></div>' +
        '  <div class="ph-bar-meta"><span id="phStockRemaining">--</span><span id="phStockPctLabel">--</span></div>' +
        '  <div class="ph-alert-box" id="phAlertBox"></div>' +
        '</div>';
}

function renderProductAnalytics(data) {
    var p = data.product || {};
    var a = data.analytics || {};
    var badges = data.badges || [];
    var alert = data.alert || {};
    var unit = p.unit || 'units';
    _productUnit = unit;

    // Header
    setText('phProductName', p.name || '-');
    setText('phProductMeta', (p.id || '') + ' &middot; ' + (p.category || ''));
    setText('phSubtitle', (p.name || '') + ' &middot; ' + unit);

    var icon = document.getElementById('phIcon');
    if (icon) icon.textContent = (p.name || '??').slice(0, 2).toUpperCase();

    // Badges — line of small tags
    var badgeHtml = '';
    badges.forEach(function(tag) {
        if (tag === 'high')    badgeHtml += '<span class="si-badge high">🔥 High selling</span> ';
        if (tag === 'low')     badgeHtml += '<span class="si-badge low">📉 Low selling</span> ';
        if (tag === 'old')     badgeHtml += '<span class="si-badge old">📦 Old stock</span> ';
        if (tag === 'dead')    badgeHtml += '<span class="si-badge" style="background:var(--danger-subtle);color:var(--danger);border:1px solid var(--danger);">💀 No sales</span> ';
        if (tag === 'new')     badgeHtml += '<span class="si-badge" style="background:color-mix(in srgb, var(--info) 15%, transparent);color:var(--info);border:1px solid var(--info);">🆕 New</span> ';
        if (tag === 'out')     badgeHtml += '<span class="si-badge" style="background:#333;color:#fff;">Out of stock</span> ';
        if (tag === 'reorder') badgeHtml += '<span class="si-badge" style="background:var(--warn-subtle);color:var(--warn);border:1px solid var(--warn);">Reorder</span> ';
    });
    document.getElementById('phBadges').innerHTML = badgeHtml || '<span style="color:var(--muted);font-size:0.85rem;">→ Normal</span>';

    // --- Classification pill (top priority) ---
    renderClassification(badges, a, unit);

    // --- Hero row ---
    var vel = a.velocity || 0;
    setText('phHeroVelocity', formatVelocityValue(vel, unit));
    setText('phHeroVelUnit', formatVelocityUnit(vel, unit));

    var dos = a.days_of_supply;
    var dosEl = document.getElementById('phHeroDos');
    if (dosEl) dosEl.textContent = formatDaysOfSupply(dos);

    setText('phHeroRevenue', window.formatCurrency(a.revenue_30d || 0));
    setText('phHeroSoldUnits', a.sold_30d || 0);

    // Hero badge (trend)
    var trend = a.trend_text || 'steady';
    var heroBadgeEl = document.getElementById('phHeroBadge');
    if (heroBadgeEl) {
        var hbMap = {
            'up':     { text: '↑ trending up', cls: 'up' },
            'down':   { text: '↓ slowing down', cls: 'down' },
            'steady': { text: '→ steady', cls: 'flat' }
        };
        var hb = hbMap[trend] || hbMap['steady'];
        heroBadgeEl.textContent = hb.text;
        heroBadgeEl.className = 'ph-hero-badge ' + hb.cls;
    }

    // --- Velocity detail ---
    var vel7 = a.avg_daily_7d || 0;
    var vel30 = a.avg_daily_30d || 0;
    var catAvg = a.avg_velocity || 0;
    var sold7 = a.sold_7d || 0;
    var sold30 = a.sold_30d || 0;

    setText('phVel7', formatVelocity(vel7, unit));
    setText('phVel30', formatVelocity(vel30, unit));
    setText('phVelCat', formatVelocity(catAvg, unit));
    setText('phVel7Sold', sold7);
    setText('phVel30Sold', sold30);

    // --- Stock status ---
    var stockLeft = a.stock_left || 0;
    var maxAge = a.max_batch_age_days || 0;
    var marginPct = a.margin_pct || 0;
    var stockPct = a.stock_pct != null ? a.stock_pct : 100;

    setText('phStockLeftVal', formatNum(stockLeft) + ' ' + unit);
    setText('phOldestBatchVal', maxAge + 'd');
    setText('phMarginVal', marginPct + '%');

    // Stock remaining bar
    var barColor = stockPct <= 10 ? 'var(--danger)' : (stockPct <= 30 ? 'var(--warn)' : 'var(--ok)');
    var barEl = document.getElementById('phStockBarFill');
    if (barEl) {
        barEl.style.width = stockPct + '%';
        barEl.style.background = barColor;
    }

    setText('phStockRemaining', formatNum(stockLeft) + ' ' + unit + ' remaining');
    var pctLabelEl = document.getElementById('phStockPctLabel');
    if (pctLabelEl) {
        pctLabelEl.textContent = stockPct + '% of original stock';
        pctLabelEl.style.color = barColor;
    }

    // Alert
    var alertHtml = '';
    if (alert.type === 'critical' || alert.type === 'warning') {
        var isWarn = alert.type === 'warning';
        alertHtml = '<div class="ph-alert-box ' + (isWarn ? 'ph-alert-warn' : 'ph-alert-warn') + '">⚠ ' + escH(alert.message) + '</div>';
    } else if (alert.type === 'good') {
        alertHtml = '<div class="ph-alert-box ph-alert-ok">✅ ' + escH(alert.message) + '</div>';
    } else {
        // Generate reorder alert from data
        var dos = a.days_of_supply;
        if (dos != null && dos <= 14 && dos > 0 && stockLeft > 0) {
            alertHtml = '<div class="ph-alert-box ph-alert-warn">⚠ At ' + formatVelocity(vel, unit) + ' — stock runs out in ' + formatDaysOfSupply(dos) + '.</div>';
        } else if (stockLeft <= 0) {
            alertHtml = '<div class="ph-alert-box" style="background:var(--danger-subtle);color:var(--danger);">⛔ Out of stock</div>';
        } else {
            alertHtml = '<div class="ph-alert-box ph-alert-ok">✅ Stock is sufficient. ' + formatDaysOfSupply(dos) + ' of supply remaining.</div>';
        }
    }
    document.getElementById('phAlertBox').innerHTML = alertHtml;
}

function renderClassification(badges, a, unit) {
    // Priority: dead > old > high > low > new > normal
    var priority = ['dead', 'old', 'high', 'low', 'new'];
    var mainTag = 'normal';
    for (var i = 0; i < priority.length; i++) {
        if (badges.indexOf(priority[i]) !== -1) {
            mainTag = priority[i];
            break;
        }
    }

    var vel = a.velocity || 0;
    var catAvg = a.avg_velocity || 0;
    var ratio = catAvg > 0 ? (vel / catAvg) : 0;
    var age = a.max_batch_age_days || 0;
    var stockPct = a.stock_pct != null ? a.stock_pct : 100;

    var pillHtml = '';
    var reason = '';

    function velText(v, u) { return '<strong>' + formatVelocityValue(v, u) + '</strong> ' + formatVelocityUnit(v, u); }

    switch (mainTag) {
        case 'high':
            pillHtml = '<span class="ph-cpill high">🔥 High Selling</span>';
            reason = 'Selling ' + velText(vel, unit) + ' — <strong>' + formatNum(ratio) + '×</strong> ' + (ratio >= 1 ? 'faster' : 'slower') + ' than your catalog average of ' + velText(catAvg, unit) + '. Consistently above average for last 30 days.';
            break;
        case 'low':
            pillHtml = '<span class="ph-cpill low">📉 Low Selling</span>';
            reason = 'Selling ' + velText(vel, unit) + ' — significantly below your catalog average of ' + velText(catAvg, unit) + '. Consider promotion or bundling.';
            break;
        case 'dead':
            pillHtml = '<span class="ph-cpill dead">💀 No Sales</span>';
            reason = 'No sales recorded in the last 30 days and the batch is <strong>' + age + ' days</strong> old. Consider discounting or removal.';
            break;
        case 'old':
            pillHtml = '<span class="ph-cpill old">📦 Old Stock</span>';
            reason = 'Batch is <strong>' + age + ' days</strong> old with <strong>' + stockPct + '%</strong> stock remaining. Only ' + velText(vel, unit) + ' — well below catalog average of ' + velText(catAvg, unit) + '.';
            break;
        case 'new':
            pillHtml = '<span class="ph-cpill new">🆕 New Product</span>';
            reason = 'New product — only <strong>' + age + ' days</strong> since first sale. Still gathering data to classify.';
            break;
        default:
            pillHtml = '<span class="ph-cpill normal">⚖️ Normal</span>';
            reason = 'Selling ' + velText(vel, unit) + ' — at or near your catalog average of ' + velText(catAvg, unit) + '.';
    }

    document.getElementById('phClassPills').innerHTML = pillHtml;
    document.getElementById('phClassReason').innerHTML = reason;

    // Color the class block border to match
    var block = document.getElementById('phClassBlock');
    if (block) {
        var borderMap = {
            'high': 'var(--ok)',
            'low': 'var(--warn)',
            'dead': 'var(--danger)',
            'old': 'var(--border-strong)',
            'new': 'var(--info)'
        };
        block.style.borderColor = borderMap[mainTag] || 'var(--accent-2-muted)';
    }
}

var WHOLE_UNITS = ['pcs', 'pieces', 'nos', 'box', 'packet', 'bottle', 'pair', 'set'];

function isWholeUnit(unit) {
    return WHOLE_UNITS.indexOf((unit || '').toLowerCase()) !== -1;
}

function formatVelocity(velocity, unit) {
    if (velocity == null || isNaN(velocity)) return '0 ' + (unit || 'units') + '/day';
    var v = Number(velocity);
    var u = unit || 'units';

    if (isWholeUnit(u)) {
        if (v < 1) {
            var perWeek = Math.round(v * 7);
            if (perWeek === 0) return 'Less than 1/week';
            return '~' + perWeek + ' ' + u + '/week';
        }
        return Math.round(v) + ' ' + u + '/day';
    }

    return v.toFixed(1) + ' ' + u + '/day';
}

function formatVelocityValue(velocity, unit) {
    if (velocity == null || isNaN(velocity)) return '0';
    var v = Number(velocity);
    if (v === 0) return '0';

    if (isWholeUnit(unit)) {
        if (v < 1) {
            var perWeek = Math.round(v * 7);
            if (perWeek === 0) return 'Less than 1';
            return '~' + perWeek;
        }
        return Math.round(v) + '';
    }

    return v.toFixed(1).replace(/\.0$/, '');
}

function formatVelocityUnit(velocity, unit) {
    if (velocity == null || isNaN(velocity)) return (unit || 'units') + '/day';
    var v = Number(velocity);
    var u = unit || 'units';

    if (v === 0) return u + '/day';

    if (isWholeUnit(u) && v < 1) {
        var perWeek = Math.round(v * 7);
        if (perWeek === 0) return '';
        return u + '/week';
    }

    return u + '/day';
}

function formatDaysOfSupply(days) {
    if (days == null || days === 999 || !isFinite(days)) return '∞';
    var d = Number(days);
    if (d >= 90) return '90+';
    if (d >= 30) return '~' + Math.round(d / 7) + ' wk';
    return Math.round(d) + '';
}

function formatNum(n) {
    if (n == null || isNaN(n)) return '0';
    return Number(n).toFixed(1).replace(/\.0$/, '');
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
