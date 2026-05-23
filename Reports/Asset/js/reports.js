    /* =======================================================================
       7. REPORTS MODULE
       ======================================================================= */

    function renderReports() {
      // 1. Stats
      let totalRev = sales.reduce((sum, s) => sum + s.total_amount, 0);
      let totalBills = sales.length;
      let outstanding = 0;
      customers.forEach(c => outstanding += getCustomerLedgerTotals(c.id).balance);

      let stockValue = 0;
      batches.forEach(b => stockValue += (b.quantity * b.purchase_price));

      document.getElementById('reportsStats').innerHTML = `
        <div class="stat-card"><div class="stat-label">Bills Generated</div><div class="stat-value">${totalBills}</div></div>
        <div class="stat-card"><div class="stat-label">Customer Kadan</div><div class="stat-value" style="color:var(--danger)">${formatCurrency(outstanding)}</div></div>
        <div class="stat-card"><div class="stat-label">Current Stock Value</div><div class="stat-value" style="color:var(--info)">${formatCurrency(stockValue)}</div></div>
      `;

      // 2. High Selling & Low Selling (by product)
      const productSales = {};
      sale_items.forEach(item => {
        const key = item.product_name;
        if (!productSales[key]) productSales[key] = { qty: 0, revenue: 0 };
        productSales[key].qty += item.qty;
        productSales[key].revenue += (item.qty * item.price);
      });

      const sortedProducts = Object.entries(productSales)
        .map(([name, data]) => ({ name, ...data }))
        .sort((a, b) => b.qty - a.qty);

      // High Selling (top 3 by qty)
      const hsBody = document.querySelector('#highSellingTable tbody');
      hsBody.innerHTML = '';
      const topSelling = sortedProducts.slice(0, 3);
      if (topSelling.length === 0) {
        hsBody.innerHTML = '<tr><td colspan="3" class="text-center">No sales data yet</td></tr>';
      } else {
        topSelling.forEach(p => {
          const product = products.find(prod => prod.name === p.name);
          const unit = product ? product.unit : 'pcs';
          hsBody.innerHTML += `<tr style="cursor:pointer" onclick="event.stopPropagation(); goToVendorForProduct('${p.name.replace(/'/g, "\\'")}')"><td style="font-weight:500;color:var(--text-strong)">${p.name}</td><td style="font-weight:600;color:var(--ok)">${p.qty} ${unit}</td><td>${formatCurrency(p.revenue)}</td></tr>`;
        });
      }

      // Low Selling — products with qty <= 10 that are NOT in the top 3 high sellers
      const highSellingNames = new Set(topSelling.map(p => p.name));
      const lsellBody = document.querySelector('#lowSellingTable tbody');
      lsellBody.innerHTML = '';
      // Low sellers = sold items with low qty, filtered out from high list
      const lowSelling = sortedProducts.filter(p => p.qty <= 10 && !highSellingNames.has(p.name));
      // Also include products in inventory with zero sales
      const zeroSaleProducts = products.filter(p => !productSales[p.name] && !highSellingNames.has(p.name));

      if (lowSelling.length === 0 && zeroSaleProducts.length === 0) {
        lsellBody.innerHTML = '<tr><td colspan="3" class="text-center">No low selling data</td></tr>';
      } else {
        lowSelling.forEach(p => {
          const product = products.find(prod => prod.name === p.name);
          const unit = product ? product.unit : 'pcs';
          lsellBody.innerHTML += `<tr style="cursor:pointer" onclick="event.stopPropagation(); goToVendorForProduct('${p.name.replace(/'/g, "\\'")}')"><td style="font-weight:500;color:var(--text-strong)">${p.name}</td><td style="font-weight:600;color:var(--warn)">${p.qty} ${unit}</td><td>${formatCurrency(p.revenue)}</td></tr>`;
        });
        zeroSaleProducts.forEach(p => {
          lsellBody.innerHTML += `<tr style="cursor:pointer" onclick="event.stopPropagation(); goToVendorForProduct('${p.name.replace(/'/g, "\\'")}')"><td style="font-weight:500;color:var(--text-strong)">${p.name}</td><td style="font-weight:600;color:var(--danger)">0 ${p.unit}</td><td>${formatCurrency(0)}</td></tr>`;
        });
      }

      // 5. Old Stock (batches older than 5 days with remaining qty)
      const osBody = document.querySelector('#oldStockTable tbody');
      osBody.innerHTML = '';
      const now = Date.now();
      const oldBatches = batches
        .filter(b => b.quantity > 0 && b.created_at)
        .map(b => ({ ...b, ageDays: Math.floor((now - b.created_at.getTime()) / 86400000) }))
        .filter(b => b.ageDays >= 5)
        .sort((a, b) => b.ageDays - a.ageDays);

      if (oldBatches.length === 0) {
        osBody.innerHTML = '<tr><td colspan="4" class="text-center">No old stock</td></tr>';
      } else {
        oldBatches.forEach(b => {
          const p = getProduct(b.product_id);
          const ageColor = b.ageDays > 15 ? 'var(--danger)' : 'var(--warn)';
          osBody.innerHTML += `<tr style="cursor:pointer" onclick="event.stopPropagation(); goToVendorForProduct('${p.name.replace(/'/g, "\\'")}')"><td style="font-weight:500">${p.name}</td><td style="color:var(--muted)">${b.id}</td><td style="font-weight:700;color:${ageColor}">${b.ageDays}d</td><td>${b.quantity} ${p.unit}</td></tr>`;
        });
      }

      // 6. Time Period Summary Cards (Today / This Week / This Month)
      const today = new Date();
      const startOfDay = new Date(today.getFullYear(), today.getMonth(), today.getDate());
      const dayOfWeek = today.getDay() || 7; // Sunday = 7
      const startOfWeek = new Date(startOfDay);
      startOfWeek.setDate(startOfWeek.getDate() - (dayOfWeek - 1));
      const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);

      function calcPeriod(startDate) {
        const filtered = sales.filter(s => s.created_at >= startDate);
        const rev = filtered.reduce((sum, s) => sum + s.total_amount, 0);
        const count = filtered.length;
        const avg = count > 0 ? rev / count : 0;
        return { rev, count, avg };
      }

      const todayData = calcPeriod(startOfDay);
      const weekData = calcPeriod(startOfWeek);
      const monthData = calcPeriod(startOfMonth);

      document.getElementById('tcTodayRev').innerText = formatCurrency(todayData.rev);
      document.getElementById('tcTodayBills').innerText = todayData.count;
      document.getElementById('tcTodayAvg').innerText = formatCurrency(todayData.avg);

      document.getElementById('tcWeekRev').innerText = formatCurrency(weekData.rev);
      document.getElementById('tcWeekBills').innerText = weekData.count;
      document.getElementById('tcWeekAvg').innerText = formatCurrency(weekData.avg);

      document.getElementById('tcMonthRev').innerText = formatCurrency(monthData.rev);
      document.getElementById('tcMonthBills').innerText = monthData.count;
      document.getElementById('tcMonthAvg').innerText = formatCurrency(monthData.avg);

      // Purchase Period Calculations
      function calcPurchasePeriod(startDate) {
        const filtered = stock_list.filter(p => p.date >= startDate);
        const amount = filtered.reduce((sum, p) => sum + p.amount, 0);
        const paid = filtered.reduce((sum, p) => sum + p.paid, 0);
        const count = filtered.length;
        return { amount, paid, count };
      }

      const todayPurchase = calcPurchasePeriod(startOfDay);
      const weekPurchase = calcPurchasePeriod(startOfWeek);
      const monthPurchase = calcPurchasePeriod(startOfMonth);

      document.getElementById('pcWeekAmount').innerText = formatCurrency(weekPurchase.amount);
      document.getElementById('pcWeekPurchases').innerText = weekPurchase.count;
      document.getElementById('pcWeekPaid').innerText = formatCurrency(weekPurchase.paid);

      document.getElementById('pcMonthAmount').innerText = formatCurrency(monthPurchase.amount);
      document.getElementById('pcMonthPurchases').innerText = monthPurchase.count;
      document.getElementById('pcMonthPaid').innerText = formatCurrency(monthPurchase.paid);

      // 7. Sales Timeline (day-by-day breakdown)
      // MOVED TO renderAnalytics()
    }

    function renderAnalytics() {
      // 1. Overall Metrics
      let totalRev = sales.reduce((sum, s) => sum + s.total_amount, 0);
      let totalPurchasedCost = batches.reduce((sum, b) => sum + (b.quantity_total || b.quantity) * b.purchase_price, 0);

      // Calculate Stock Sold (at cost)
      let totalSoldCost = 0;
      const productMetrics = {};

      sale_items.forEach(item => {
        let pCost = 0;
        if (item.batch_id) {
          const batch = batches.find(b => b.id === item.batch_id);
          pCost = batch ? batch.purchase_price : 0;
        }

        // Fallback: If no batch_id or batch not found, look up the product's base price from the latest batch
        if (pCost === 0) {
          const product = products.find(p => p.name === item.product_name);
          if (product) {
            const productBatch = batches.find(b => b.product_id === product.id);
            pCost = productBatch ? productBatch.purchase_price : 0;
          }
        }

        const lineSoldCost = item.qty * pCost;
        totalSoldCost += lineSoldCost;

        if (!productMetrics[item.product_name]) {
          productMetrics[item.product_name] = { purchased: 0, sold: 0 };
        }
        productMetrics[item.product_name].sold += lineSoldCost;
      });

      // Calculate purchased cost per product
      batches.forEach(b => {
        const p = getProduct(b.product_id);
        if (p) {
          if (!productMetrics[p.name]) productMetrics[p.name] = { purchased: 0, sold: 0 };
          productMetrics[p.name].purchased += (b.quantity_total || b.quantity) * b.purchase_price;
        }
      });

      let currentStockValue = batches.reduce((sum, b) => sum + (b.quantity * b.purchase_price), 0);

      document.getElementById('analyticsComparisonCards').innerHTML = `
        <div class="stat-card">
          <div class="stat-label">Stock Value Purchased</div>
          <div class="stat-value" style="color:var(--info)">${formatCurrency(totalPurchasedCost)}</div>
          <div style="font-size:0.7rem; color:var(--muted); margin-top:4px;">Total investment in stock</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Stock Value Sold (at Cost)</div>
          <div class="stat-value" style="color:var(--ok)">${formatCurrency(totalSoldCost)}</div>
          <div style="font-size:0.7rem; color:var(--muted); margin-top:4px;">Investment recovered from sales</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Current Asset Value</div>
          <div class="stat-value" style="color:var(--accent)">${formatCurrency(currentStockValue)}</div>
          <div style="font-size:0.7rem; color:var(--muted); margin-top:4px;">Value of stock in hand</div>
        </div>
      `;

      // 2. Recommendations Table
      const recBody = document.querySelector('#recommendationTable tbody');
      if (recBody) {
        recBody.innerHTML = '';
        const metricsArray = Object.entries(productMetrics).map(([name, data]) => ({
          name,
          ...data,
          ratio: data.purchased > 0 ? (data.sold / data.purchased) : 0
        })).sort((a, b) => b.ratio - a.ratio);

        if (metricsArray.length === 0) {
          recBody.innerHTML = '<tr><td colspan="5" class="text-center">No data available yet</td></tr>';
        } else {
          metricsArray.forEach(m => {
            let rec = '';
            let recColor = '';
            if (m.ratio > 0.7) { rec = '🚀 Fast Moving! Buy More'; recColor = 'var(--ok)'; }
            else if (m.ratio > 0.3) { rec = '✅ Steady. Maintain Stock'; recColor = 'var(--info)'; }
            else if (m.ratio > 0.1) { rec = '⚠️ Slow. Reduce Order'; recColor = 'var(--warn)'; }
            else { rec = '🛑 Dead Stock. Liquidate'; recColor = 'var(--danger)'; }

            recBody.innerHTML += `
              <tr>
                <td style="font-weight:600">${m.name}</td>
                <td style="color:var(--muted-strong)">${formatCurrency(m.purchased)}</td>
                <td style="color:var(--ok)">${formatCurrency(m.sold)}</td>
                <td style="font-weight:700">${(m.ratio * 100).toFixed(1)}%</td>
                <td style="font-weight:700; color:${recColor}">${rec}</td>
              </tr>
            `;
          });
        }
      }

      // 3. Investment Insights
      const margin = totalSoldCost > 0 ? ((totalRev - totalSoldCost) / totalRev * 100).toFixed(1) : 0;
      const insightsContainer = document.getElementById('analyticsInsights');
      if (insightsContainer) {
        insightsContainer.innerHTML = `
          <div style="margin-bottom:12px;">
            <div style="font-size:0.8rem; color:var(--muted); text-transform:uppercase;">Overall Recovery</div>
            <div style="font-size:1.2rem; font-weight:800; color:var(--text-strong);">${((totalSoldCost / totalPurchasedCost) * 100).toFixed(1) || 0}%</div>
            <div style="width:100%; height:6px; background:var(--bg-elevated); border-radius:10px; margin-top:6px; overflow:hidden;">
              <div style="width:${(totalSoldCost / totalPurchasedCost) * 100}%; height:100%; background:var(--ok);"></div>
            </div>
          </div>
          <p style="font-size:0.9rem; color:var(--muted-strong);">
            Based on current data, your average profit margin is <strong>${margin}%</strong>. 
            ${margin > 20 ? 'Your business has healthy margins.' : 'Margins are thin; consider reviewing purchase costs.'}
          </p>
        `;
      }

      // 4. Timelines
      renderSalesTimeline();
      renderPurchaseTimeline();
    }

    function renderSalesTimeline() {
      const tlBody = document.querySelector('#salesTimelineTable tbody');
      if (!tlBody) return;
      tlBody.innerHTML = '';
      const salesByDate = {};
      sales.forEach(s => {
        const dateKey = s.created_at.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
        if (!salesByDate[dateKey]) salesByDate[dateKey] = { sales: [], items: [] };
        salesByDate[dateKey].sales.push(s);
      });
      sale_items.forEach(item => {
        const sale = sales.find(s => s.id === item.sale_id);
        if (sale) {
          const dateKey = sale.created_at.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
          if (salesByDate[dateKey]) salesByDate[dateKey].items.push(item);
        }
      });
      const dateEntries = Object.entries(salesByDate).reverse();
      if (dateEntries.length === 0) {
        tlBody.innerHTML = '<tr><td colspan="6" class="text-center">No sales recorded yet.</td></tr>';
      } else {
        dateEntries.forEach(([date, data], index) => {
          const rev = data.sales.reduce((sum, s) => sum + s.total_amount, 0);
          const avg = rev / data.sales.length;
          const itemCounts = {};
          data.items.forEach(item => {
            if (!itemCounts[item.product_name]) itemCounts[item.product_name] = 0;
            itemCounts[item.product_name] += item.qty;
          });
          const productTags = Object.entries(itemCounts).sort((a, b) => b[1] - a[1])
            .map(([name, qty]) => `<span class="badge" style="background:var(--accent-subtle);color:var(--accent);margin:2px 4px 2px 0;display:inline-block">${name} <strong>${qty}</strong></span>`)
            .join('');
          const billsClass = `bills-group-${index}`;
          tlBody.innerHTML += `
            <tr onclick="toggleBills('${billsClass}')" style="cursor:pointer;">
              <td style="font-weight:500; color:var(--text-strong)">${date}</td>
              <td style="font-weight:600">${data.sales.length}</td>
              <td style="font-weight:600; color:var(--ok)">${formatCurrency(rev)}</td>
              <td style="color:var(--muted-strong)">${formatCurrency(avg)}</td>
              <td style="max-width:300px">${productTags || '-'}</td>
              <td>-</td>
            </tr>
          `;
          data.sales.forEach(s => {
            const customerName = s.customer_id ? getCustomer(s.customer_id).name : 'Walk-in';
            tlBody.innerHTML += `
              <tr class="bill-row ${billsClass}" style="display:none; background:var(--bg-hover); cursor:pointer;" onclick="viewBillReceipt('${s.id}')">
                <td style="padding-left:20px; font-weight:600; color:var(--accent);">📄 ${s.id}</td>
                <td>${customerName}</td>
                <td style="color:var(--ok)">${formatCurrency(s.total_amount)}</td>
                <td>-</td>
                <td>-</td>
                <td style="text-align:right;">
                  <button class="btn btn-sm btn-outline" style="color:var(--danger); border-color:rgba(239,68,68,0.3); font-size:0.7rem; padding:2px 8px;" onclick="event.stopPropagation(); deleteBill('${s.id}')">Delete</button>
                </td>
              </tr>
            `;
          });
        });
      }
    }

    function renderPurchaseTimeline() {
      const ptBody = document.querySelector('#purchaseTimelineTable tbody');
      if (!ptBody) return;
      ptBody.innerHTML = '';
      const purchasesByDate = {};
      stock_list.forEach(p => {
        const dateKey = p.date.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
        if (!purchasesByDate[dateKey]) purchasesByDate[dateKey] = { purchases: [], vendors: new Set() };
        purchasesByDate[dateKey].purchases.push(p);
        purchasesByDate[dateKey].vendors.add(p.vendor_name);
      });
      const purchaseDateEntries = Object.entries(purchasesByDate).reverse();
      if (purchaseDateEntries.length === 0) {
        ptBody.innerHTML = '<tr><td colspan="6" class="text-center">No purchases recorded yet.</td></tr>';
      } else {
        purchaseDateEntries.forEach(([date, data], index) => {
          const count = data.purchases.length;
          const amountPurchased = data.purchases.reduce((sum, p) => sum + p.amount, 0);
          const amountPaid = data.purchases.reduce((sum, p) => sum + p.paid, 0);
          const balanceDue = data.purchases.reduce((sum, p) => sum + p.balance, 0);
          const vendors = Array.from(data.vendors).join(', ');
          const billsClass = `p-bills-group-${index}`;
          ptBody.innerHTML += `
            <tr onclick="toggleBills('${billsClass}')" style="cursor:pointer;">
              <td style="font-weight:500; color:var(--text-strong)">${date}</td>
              <td style="font-weight:600">${count}</td>
              <td style="font-weight:600; color:var(--ok)">${formatCurrency(amountPurchased)}</td>
              <td style="color:var(--muted-strong)">${formatCurrency(amountPaid)}</td>
              <td style="color:var(--danger)">${formatCurrency(balanceDue)}</td>
              <td style="max-width:200px">${vendors}</td>
            </tr>
          `;
          data.purchases.forEach(p => {
            ptBody.innerHTML += `
              <tr class="bill-row ${billsClass}" style="display:none; background:var(--bg-hover);">
                <td style="padding-left:20px;">${p.stock_name}</td>
                <td>${p.vendor_name}</td>
                <td style="color:var(--ok)">${formatCurrency(p.amount)}</td>
                <td>${formatCurrency(p.paid)}</td>
                <td style="color:var(--danger)">${formatCurrency(p.balance)}</td>
                <td>-</td>
              </tr>
            `;
          });
        });
      }
    }

    /* =======================================================================
       8. STOCK INTELLIGENCE MODULE
       ======================================================================= */

    let activeStockIntelFilter = 'all';

    function openStockIntel(filter) {
      activeStockIntelFilter = filter || 'all';
      document.querySelectorAll('.view-section').forEach(el => el.classList.remove('active'));
      document.getElementById('stockintel').classList.add('active');
      updateSiFilterBtns();
      renderStockIntel();
    }

    function setStockIntelFilter(filter) {
      activeStockIntelFilter = filter;
      updateSiFilterBtns();
      renderStockIntel();
    }

    function updateSiFilterBtns() {
      document.querySelectorAll('#siFilters .cat-btn').forEach(btn => {
        btn.classList.remove('active');
        if (btn.textContent.toLowerCase().includes(activeStockIntelFilter) ||
          (activeStockIntelFilter === 'all' && btn.textContent === 'All')) {
          btn.classList.add('active');
        }
      });
    }

    function renderStockIntel() {
      const container = document.getElementById('siCardsContainer');
      const titles = { all: 'Stock Intelligence', high: '🔥 High Selling Products', low: '📉 Low Selling Products', old: '📦 Old Stock Items', critical: '🚨 Critically Low Stock' };
      document.getElementById('siTitle').textContent = titles[activeStockIntelFilter] || 'Stock Intelligence';

      // Build product analytics data
      const productSales = {};
      sale_items.forEach(item => {
        const key = item.product_id || item.product_name;
        if (!productSales[key]) productSales[key] = { qty: 0, revenue: 0, name: item.product_name };
        productSales[key].qty += item.qty;
        productSales[key].revenue += (item.qty * item.price);
      });

      // Build product analytics with batch data
      const analytics = [];
      const now = Date.now();
      const thirtyDays = 30 * 86400000;

      products.forEach(p => {
        const pBatches = batches.filter(b => b.product_id === p.id);
        const totalUnits = pBatches.reduce((s, b) => s + b.quantity + (productSales[p.id] ? productSales[p.id].qty : 0), 0) || pBatches.reduce((s, b) => s + b.quantity, 0);
        const stockLeft = pBatches.reduce((s, b) => s + b.quantity, 0);
        const salesData = productSales[p.id] || productSales[p.name] || { qty: 0, revenue: 0 };
        const soldUnits = salesData.qty;
        const revenue = salesData.revenue;
        const velocity = soldUnits / 30; // units per day (30-day avg)
        const daysLeft = velocity > 0 ? Math.round(stockLeft / velocity) : (stockLeft > 0 ? 999 : 0);
        const purchaseValue = pBatches.reduce((s, b) => s + (b.quantity * b.purchase_price), 0);
        const sellingValue = pBatches.reduce((s, b) => s + (b.quantity * b.selling_price), 0);
        const margin = purchaseValue > 0 ? Math.round(((sellingValue - purchaseValue) / purchaseValue) * 100) : 0;
        const maxBatchAge = pBatches.length > 0 ? Math.max(...pBatches.filter(b => b.created_at).map(b => Math.floor((now - b.created_at.getTime()) / 86400000))) : 0;
        const stockPercent = totalUnits > 0 ? Math.round((stockLeft / totalUnits) * 100) : 0;

        // Classification
        let tags = [];
        if (soldUnits > 10) tags.push('high');
        if (soldUnits <= 2 && soldUnits >= 0) tags.push('low');
        if (maxBatchAge >= 5) tags.push('old');
        if (stockLeft <= 5 && stockLeft > 0) tags.push('critical');
        if (stockLeft === 0) tags.push('critical');

        analytics.push({ product: p, totalUnits, stockLeft, soldUnits, revenue, velocity, daysLeft, margin, maxBatchAge, stockPercent, tags });
      });

      // Filter
      let filtered = analytics;
      if (activeStockIntelFilter === 'high') filtered = analytics.filter(a => a.tags.includes('high')).sort((a, b) => b.soldUnits - a.soldUnits);
      else if (activeStockIntelFilter === 'low') filtered = analytics.filter(a => a.tags.includes('low')).sort((a, b) => a.soldUnits - b.soldUnits);
      else if (activeStockIntelFilter === 'old') filtered = analytics.filter(a => a.tags.includes('old')).sort((a, b) => b.maxBatchAge - a.maxBatchAge);
      else if (activeStockIntelFilter === 'critical') filtered = analytics.filter(a => a.tags.includes('critical')).sort((a, b) => a.stockLeft - b.stockLeft);
      else filtered = analytics.sort((a, b) => b.revenue - a.revenue);

      if (filtered.length === 0) {
        container.innerHTML = '<div style="text-align:center; padding:60px; color:var(--muted);"><div style="font-size:2rem; margin-bottom:10px;">📦</div>No products match this filter</div>';
        return;
      }

      let html = '';
      filtered.forEach(a => {
        const p = a.product;
        const icons = ['🧵', '🧶', '👚', '👗', '✂️', '🪡'];
        const icon = icons[Math.abs(p.id.charCodeAt(p.id.length - 1)) % icons.length];

        // Badge
        let badgeHtml = '';
        if (a.tags.includes('high')) badgeHtml += '<span class="si-badge high">High selling</span> ';
        if (a.tags.includes('low')) badgeHtml += '<span class="si-badge low">Low selling</span> ';
        if (a.tags.includes('old')) badgeHtml += '<span class="si-badge old">Old stock</span> ';

        // Stock bar color
        let barColor = 'var(--ok)';
        if (a.stockPercent <= 10) barColor = 'var(--danger)';
        else if (a.stockPercent <= 30) barColor = 'var(--warn)';

        // Alert
        let alertHtml = '';
        if (a.stockLeft === 0) {
          alertHtml = '<div class="si-alert critical">⚙️ OUT OF STOCK — Reorder immediately.</div>';
        } else if (a.stockLeft <= 5 && a.velocity > 0) {
          alertHtml = `<div class="si-alert critical">⚙️ Stock critically low — only ${a.stockLeft} ${p.unit}. At ${a.velocity.toFixed(1)}/${p.unit} per day you will run out in ${a.daysLeft} days. Reorder immediately.</div>`;
        } else if (a.stockPercent <= 30 && a.velocity > 0) {
          alertHtml = `<div class="si-alert warning">⚠️ Stock running low — ${a.stockLeft} ${p.unit} remaining. Consider reordering soon.</div>`;
        } else if (a.tags.includes('high') && a.stockLeft > 5) {
          alertHtml = `<div class="si-alert good">✅ Strong seller with healthy stock levels.</div>`;
        }

        html += `
          <div class="si-card">
            <div class="si-header">
              <div class="si-product">
                <div class="si-icon" style="background: var(--accent-subtle);">${icon}</div>
                <div>
                  <div class="si-name">${p.name}</div>
                  <div class="si-meta">${p.id} · ${p.category}</div>
                </div>
              </div>
              <div>${badgeHtml}</div>
            </div>
            <div class="si-metrics">
              <div class="si-metric"><div class="si-metric-label">Sold (30d)</div><div class="si-metric-value" style="color:var(--ok)">${a.soldUnits}</div></div>
              <div class="si-metric"><div class="si-metric-label">Revenue</div><div class="si-metric-value">${formatCurrency(a.revenue)}</div></div>
              <div class="si-metric"><div class="si-metric-label">Velocity</div><div class="si-metric-value" style="color:var(--info)">${a.velocity.toFixed(1)}/${p.unit} per day</div></div>
            </div>
            <div class="si-metrics">
              <div class="si-metric"><div class="si-metric-label">Stock left</div><div class="si-metric-value">${a.stockLeft} ${p.unit}</div></div>
              <div class="si-metric"><div class="si-metric-label">Days left</div><div class="si-metric-value" style="color:${a.daysLeft <= 3 ? 'var(--danger)' : a.daysLeft <= 7 ? 'var(--warn)' : 'var(--text-strong)'}">${a.daysLeft === 999 ? '∞' : a.daysLeft + ' days'}</div></div>
              <div class="si-metric"><div class="si-metric-label">Margin</div><div class="si-metric-value" style="color:${a.margin >= 15 ? 'var(--ok)' : 'var(--warn)'}">${a.margin}%</div></div>
            </div>
            <div class="si-stock-bar">
              <div class="si-stock-label"><span>Stock level</span><span style="color:${barColor}">${a.stockPercent}%</span></div>
              <div class="si-bar-track"><div class="si-bar-fill" style="width:${a.stockPercent}%; background:${barColor}"></div></div>
            </div>
            ${alertHtml}
          </div>
        `;
      });

      container.innerHTML = html;
    }


    function openSalesSummaryDetail(period) {
      const today = new Date();
      const startOfDay = new Date(today.getFullYear(), today.getMonth(), today.getDate());
      const dayOfWeek = today.getDay() || 7;
      const startOfWeek = new Date(startOfDay);
      startOfWeek.setDate(startOfWeek.getDate() - (dayOfWeek - 1));
      const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);

      let startDate;
      let title;
      if (period === 'today') {
        startDate = startOfDay;
        title = "Today's Sales Bills";
      } else if (period === 'week') {
        startDate = startOfWeek;
        title = "This Week's Sales Bills";
      } else {
        startDate = startOfMonth;
        title = "This Month's Sales Bills";
      }

      document.getElementById('salesSummaryDetailTitle').innerText = title;
      const tbody = document.querySelector('#salesSummaryDetailTable tbody');
      tbody.innerHTML = '';

      const filteredSales = sales.filter(s => s.created_at >= startDate).sort((a, b) => b.created_at - a.created_at);
      
      if (filteredSales.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center">No sales bills found for this period.</td></tr>';
      } else {
        filteredSales.forEach(s => {
          const customerName = s.customer_id ? getCustomer(s.customer_id).name : 'Walk-in';
          const dateStr = s.created_at.toLocaleDateString('en-IN', { day: '2-digit', month: 'short' });
          tbody.innerHTML += `
            <tr style="cursor:pointer;" onclick="viewBillReceipt('${s.id}')">
              <td style="font-family:var(--mono); color:var(--accent); font-weight:600;">📄 ${s.id}</td>
              <td style="color:var(--muted);">${dateStr}</td>
              <td style="font-weight:500;">${customerName}</td>
              <td style="font-weight:600; color:var(--ok);">${formatCurrency(s.total_amount)}</td>
            </tr>
          `;
        });
      }

      openModal('salesSummaryDetailModal');
    }
