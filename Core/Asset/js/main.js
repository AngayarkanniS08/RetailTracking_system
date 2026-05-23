    /* =======================================================================
       1. MOCK DATABASE (In-Memory State)
       =====================================================
    /* =======================================================================
       2. UI INTERACTIONS & THEME
       ======================================================================= */

    function setTheme(mode) {
      document.documentElement.setAttribute('data-theme-mode', mode);
      document.querySelectorAll('.theme-btn').forEach(btn => btn.classList.remove('active'));
      event.currentTarget.classList.add('active');
    }


    function toggleSidebar() {
      const db = document.getElementById('dashboardView');
      const btn = document.getElementById('sidebarToggle');
      db.classList.toggle('sidebar-hidden');
      btn.classList.toggle('active');
    }

    function toggleBillingMaximize() {
      const db = document.getElementById('dashboardView');
      const btn = document.getElementById('billingMaximizeBtn');
      db.classList.toggle('billing-maximized');
      btn.classList.toggle('active');

      if (db.classList.contains('billing-maximized')) {
        btn.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 14h6v6"></path><path d="M20 10h-6V4"></path><path d="M14 10l7-7"></path><path d="M10 14l-7 7"></path></svg>';
        btn.title = "Exit Fullscreen";
      } else {
        btn.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h6v6"></path><path d="M9 21H3v-6"></path><path d="M21 3l-7 7"></path><path d="M3 21l7-7"></path></svg>';
        btn.title = "Fullscreen Billing";
      }
    }

    function toggleCart() {
      const grid = document.querySelector('#billing .pos-grid');
      const btn = document.getElementById('cartToggle');
      const icon = document.getElementById('cartToggleIcon');
      grid.classList.toggle('cart-collapsed');

      if (grid.classList.contains('cart-collapsed')) {
        icon.innerHTML = '<polyline points="11 17 6 12 11 7"></polyline><polyline points="18 17 13 12 18 7"></polyline>';
        btn.title = "Show Cart";
      } else {
        icon.innerHTML = '<polyline points="13 17 18 12 13 7"></polyline><polyline points="6 17 11 12 6 7"></polyline>';
        btn.title = "Collapse Cart";
      }
    }

    function navigateTo(tabId) {
      document.querySelectorAll('.view-section').forEach(el => el.classList.remove('active'));
      document.getElementById(tabId).classList.add('active');
      if (tabId === 'inventory') renderInventory();
      if (tabId === 'credit_kadan') renderCredit();
      if (tabId === 'vendor_list') renderStockList();
      if (tabId === 'product_master') renderProductMaster();
      if (tabId === 'dashboard') renderReports();
      if (tabId === 'day_to_day_selling') renderAnalytics();
      if (tabId === 'stockintel') renderStockIntel();
    }

    function switchTab(tabId) {
      document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
      if (typeof event !== 'undefined' && event && event.currentTarget) {
        event.currentTarget.classList.add('active');
      }
      navigateTo(tabId);
    }

    function openModal(id) { document.getElementById(id).classList.add('active'); }
    function closeModal(id) { document.getElementById(id).classList.remove('active'); }

    window.onclick = function(event) {
      if (event.target.classList.contains('modal-overlay')) {
        event.target.classList.remove('active');
      }
    };

    async function loadCategoriesFromAPI() {
    try {
        const res = await fetch('/api/categories.php');
        const data = await res.json();
        if (!data.error) {
            window.categories = data;   // store globally
        } else {
            console.error('Failed to load categories', data.error);
        }
    } catch (err) {
        console.error('Network error loading categories', err);
    }
}

    /* =======================================================================
       3. INITIALIZATION & HELPERS
       ======================================================================= */

    // Track active category filter for POS
    let activePosCategory = '';
    let activePmCategory = '';

    async function initApp() {
      await loadCategoriesFromAPI();
      populateCustomerSelect();
      populateProductSelect();
      populateCategorySelects();
      populateStockListProductSelect();
      renderPOSCatFilters();
      renderPOSItems();
      renderReports(); // Dashboard is the default landing page
      renderLowStockBanner(); // Check low stock on load
      // Auto-fill today's date in the Add Stock modal
      document.getElementById('stockDate').value = new Date().toISOString().split('T')[0];
    }

    function populateCategorySelects() {
      // TODO: Populate categories from backend API
    }

    function onCategoryChange(catName) {
      // TODO: Populate subcategories from backend API based on catName
    }

    function populateStockListProductSelect() {
      const sel = document.getElementById('slStockName');
      sel.innerHTML = '<option value="" data-unit="" data-gst="0">-- Select Product --</option>';
      products.forEach(p => {
        sel.innerHTML += `<option value="${p.name}" data-unit="${p.unit}" data-gst="${p.gst || 0}">${p.name} (${p.category})</option>`;
      });
      updateSlUnit();
    }

    function updateSlUnit() {
      const sel = document.getElementById('slStockName');
      const selected = sel.options[sel.selectedIndex];
      const unit = selected ? selected.getAttribute('data-unit') : '';
      document.getElementById('slQtyLabel').innerText = unit ? `Quantity (${unit})` : 'Quantity';

      calculateSlGst();
    }

    function calculateSlGst() {
      const sel = document.getElementById('slStockName');
      const selected = sel.options[sel.selectedIndex];
      const gstRate = parseFloat(selected ? selected.getAttribute('data-gst') : 0) || 0;

      const baseAmount = parseFloat(document.getElementById('slAmount').value) || 0;
      const gstAmount = baseAmount * (gstRate / 100);
      const totalAmount = baseAmount + gstAmount;

      document.getElementById('slGstRateText').innerText = `GST (${gstRate}%): ${formatCurrency(gstAmount)}`;
      document.getElementById('slTotalText').innerText = `Total Bill: ${formatCurrency(totalAmount)}`;
    }

    function toggleBills(billsClass) {
      const rows = document.querySelectorAll(`.${billsClass}`);
      rows.forEach(row => {
        row.style.display = row.style.display === 'none' ? 'table-row' : 'none';
      });
    }

    function viewBillReceipt(billId) {
      const sale = sales.find(s => s.id === billId);
      if (!sale) return;

      const items = sale_items.filter(item => item.sale_id === billId);
      const customerName = sale.customer_id ? getCustomer(sale.customer_id).name : 'Walk-in';
      const dateTime = sale.created_at.toLocaleString('en-IN');

      // Calculate totals
      let subtotal = 0;
      let itemRows = '';

      items.forEach(item => {
        const total = item.qty * item.price;
        subtotal += total;
        const product = products.find(p => p.name === item.product_name);
        const unit = product ? product.unit : 'pcs';
        const qtyDisplay = `${item.qty} ${unit}`.padEnd(10);
        itemRows += `${item.product_name.padEnd(18)} ${qtyDisplay} ${formatCurrency(item.price).padStart(10)} ${formatCurrency(total).padStart(12)}\n`;
      });

      // Assume 18% GST for now (can vary by product)
      const gstRate = 18;
      const gstAmount = subtotal * (gstRate / 100);
      const grandTotal = subtotal + gstAmount;

      // Check credit balance for this bill
      const creditEntry = credit_ledger.find(l => l.sale_id === billId);
      const amtPaid = creditEntry ? creditEntry.amount_paid : (sale.amount_paid || grandTotal);
      const billBalance = grandTotal - amtPaid;

      let receipt = `========================================
        PUDHEERA FASHIONS
        Shop no 9, 1st floor, Vallioor
        busstand
========================================
Invoice: ${billId}
Date: ${dateTime}
Customer: ${customerName}
Type: Tax Invoice (GST ${gstRate}%)
----------------------------------------
Item              Qty        Price      Total
----------------------------------------
${itemRows}----------------------------------------
Subtotal:                           ${formatCurrency(subtotal)}
GST (${gstRate}%):                        ${formatCurrency(gstAmount)}
GRAND TOTAL:                        ${formatCurrency(grandTotal)}
========================================
Amount Paid:                        ${formatCurrency(amtPaid)}`;

      if (billBalance > 0) {
        receipt += `\nBalance Due:                        ${formatCurrency(billBalance)}`;
      }

      receipt += `\n\n         Thank you for shopping!
========================================`;

      document.getElementById('billReceiptContent').innerText = receipt;
      openModal('billReceiptModal');
    }

    let _pendingDeleteSaleId = null;

    function deleteBill(saleId) {
      console.log("Delete Bill Request:", saleId);
      _pendingDeleteSaleId = saleId;
      const msg = document.getElementById('deleteBillMessage');
      if (msg) msg.innerText = `Delete bill ${saleId}? Purchased items will be restored to stock.`;
      openModal('deleteBillModal');
    }

    function executeBillDelete() {
      const saleId = _pendingDeleteSaleId;
      closeModal('deleteBillModal');
      if (!saleId) return;
      _pendingDeleteSaleId = null;

      try {
        const sale = sales.find(s => s.id === saleId);
        if (!sale) {
          return alert('Bill not found: ' + saleId);
        }

        // 1. Restore Stock
        const items = sale_items.filter(item => item.sale_id === saleId);
        items.forEach(item => {
          const batch = batches.find(b => b.id === item.batch_id);
          if (batch) batch.quantity += item.qty;
        });

        // 2. Remove from collections
        sales = sales.filter(s => s.id !== saleId);
        sale_items = sale_items.filter(item => item.sale_id !== saleId);
        credit_ledger = credit_ledger.filter(l => l.sale_id !== saleId);

        // 3. Re-render
        renderReports();
        renderPOSItems();
        renderLowStockBanner();
        renderCredit();

        alert(`Bill ${saleId} deleted. Stock restored.`);
      } catch (err) {
        console.error("FATAL deleteBill error:", err);
        alert('Error: ' + err.message);
      }
    }

    function printBillReceipt() {
      const content = document.getElementById('billReceiptContent').innerText;
      const printWindow = window.open('about:blank', '', 'height=600,width=800');
      printWindow.document.write(`<pre style="font-family:monospace; font-size:12px; line-height:1.6; margin:20px; white-space:pre-wrap;">${content}</pre>`);
      printWindow.document.close();
      setTimeout(() => printWindow.print(), 250);
    }

    function getProduct(pid) { return products.find(p => p.id === pid); }
    function getCustomer(cid) { return customers.find(c => c.id === cid) || { name: 'Walk-in', id: null }; }
    function generateId(prefix) { return prefix + Math.floor(Math.random() * 100000); }

    function formatCurrency(val) { return '₹' + parseFloat(val).toFixed(2); }

    function resetProductModal() {
      const form = document.querySelector('#addProductModal form');
      if (form) form.reset();
      // Also reset any custom preview or state if needed
      if (document.getElementById('pmProductCategory')) {
        populateCategorySelects();
      }
    }

    // Auto-init if dashboard is present
    document.addEventListener('DOMContentLoaded', () => {
      if (document.getElementById('dashboardView')) {
        initApp();
      }
    });

    /* =======================================================================
       LOW STOCK ALERTS SYSTEM
       ======================================================================= */
    function populateAlertProductSelect() {
      const sel = document.getElementById('alertProductSelect');
      if (!sel) return;
      sel.innerHTML = '<option value="">-- Select Product --</option>';
      products.forEach(p => {
        const totalStock = batches.filter(b => b.product_id === p.id).reduce((s, b) => s + b.quantity, 0);
        const existing = low_stock_alerts[p.id];
        sel.innerHTML += `<option value="${p.id}">${p.name} (Stock: ${totalStock} ${p.unit})${existing ? ' ✓ Alert at ' + existing : ''}</option>`;
      });
      renderExistingAlerts();
    }

    function renderExistingAlerts() {
      const container = document.getElementById('existingAlertsList');
      if (!container) return;
      const entries = Object.entries(low_stock_alerts);
      if (entries.length === 0) {
        container.innerHTML = '<div style="font-size:0.8rem; color:var(--muted); padding:8px 0;">No alerts set yet.</div>';
        return;
      }
      let html = '<div style="font-size:0.8rem; font-weight:600; color:var(--text-strong); margin-bottom:6px;">Active Alerts:</div>';
      entries.forEach(([pid, threshold]) => {
        const p = products.find(x => x.id === pid);
        if (!p) return;
        const stock = batches.filter(b => b.product_id === pid).reduce((s, b) => s + b.quantity, 0);
        const isBelowStr = stock <= threshold ? ' — ⚠️ BELOW!' : '';
        html += `<div style="display:flex; justify-content:space-between; align-items:center; padding:6px 10px; background:var(--bg-elevated); border-radius:var(--radius-sm); margin-bottom:4px; font-size:0.82rem;">
          <span><strong>${p.name}</strong> — Alert below <strong>${threshold} ${p.unit}</strong> (Current: ${stock})${isBelowStr}</span>
          <button class="btn btn-sm" style="padding:2px 8px; font-size:0.7rem; color:var(--danger); border-color:var(--danger);" onclick="removeLowStockAlert('${pid}')">Remove</button>
        </div>`;
      });
      container.innerHTML = html;
    }

    function saveLowStockAlert() {
      const pid = document.getElementById('alertProductSelect').value;
      const threshold = parseInt(document.getElementById('alertThreshold').value);
      if (!pid) return alert('Please select a product');
      if (!threshold || threshold < 1) return alert('Please enter a valid threshold');
      
      low_stock_alerts[pid] = threshold;
      document.getElementById('alertThreshold').value = '';
      renderExistingAlerts();
      populateAlertProductSelect();
      renderLowStockBanner();
    }

    function removeLowStockAlert(pid) {
      delete low_stock_alerts[pid];
      renderExistingAlerts();
      populateAlertProductSelect();
      renderLowStockBanner();
    }

    function renderLowStockBanner() {
      const banner = document.getElementById('lowStockBanner');
      const itemsDiv = document.getElementById('lowStockBannerItems');
      if (!banner || !itemsDiv) return;
      
      const alerts = [];
      Object.entries(low_stock_alerts).forEach(([pid, threshold]) => {
        const p = products.find(x => x.id === pid);
        if (!p) return;
        const stock = batches.filter(b => b.product_id === pid).reduce((s, b) => s + b.quantity, 0);
        alerts.push({ product: p, stock, threshold, isCritical: stock <= threshold });
      });

      if (alerts.length === 0) {
        banner.classList.remove('visible');
        return;
      }

      banner.classList.add('visible');
      itemsDiv.innerHTML = alerts.map(a => {
        const color = a.isCritical ? 'var(--danger)' : 'var(--warn)';
        const icon = a.isCritical ? '🚨' : '⚠️';
        const label = a.isCritical
          ? `${a.product.name}: <strong>${a.stock} ${a.product.unit}</strong> left — BELOW min (${a.threshold})`
          : `${a.product.name}: ${a.stock} ${a.product.unit} (alert set at ${a.threshold})`;
        return `<span class="low-stock-item" style="background:${a.isCritical ? 'rgba(220,38,38,0.15)' : 'rgba(180,83,9,0.12)'}; border-color:${color}; color:${color};">${icon} ${label}</span>`;
      }).join('');
    }

    // Helper to open modal and populate alerts
    function openLowStockAlertModal() {
      populateAlertProductSelect();
      openModal('lowStockAlertModal');
    }

    // Open alert modal pre-selected for a specific product name
    function openAlertForProduct(productName) {
      const product = products.find(p => p.name === productName);
      if (!product) return;
      openLowStockAlertModal();
      const sel = document.getElementById('alertProductSelect');
      if (sel) {
        sel.value = product.id;
        renderExistingAlerts();
      }
    }
