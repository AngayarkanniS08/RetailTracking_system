    /* =======================================================================
       5. INVENTORY MODULE
       ======================================================================= */

    function renderInventory() {
      // Compute stats
      let currentStockValue = 0;
      let stockSoldValue = 0;
      let totalBatches = 0;
      let lowStockCount = 0;

      // Current stock value = qty remaining × purchase price (cost basis)
      batches.forEach(b => {
        currentStockValue += b.quantity * b.purchase_price;
        totalBatches++;
        if (b.quantity === 0) lowStockCount++;
        else if (b.quantity <= 20) lowStockCount++;
      });

      // Stock sold value = qty sold × selling price (revenue from inventory)
      sale_items.forEach(item => {
        stockSoldValue += item.qty * item.price;
      });

      document.getElementById('inventoryStats').innerHTML = `
        <div class="stat-card">
          <div class="stat-label">Current Stock Value</div>
          <div class="stat-value" style="color:var(--info)">${formatCurrency(currentStockValue)}</div>
          <div style="font-size:0.75rem; color:var(--muted); margin-top:4px;">Based on purchase cost</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Stock Sold Value</div>
          <div class="stat-value" style="color:var(--ok)">${formatCurrency(stockSoldValue)}</div>
          <div style="font-size:0.75rem; color:var(--muted); margin-top:4px;">Total revenue from sales</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Total Batches</div>
          <div class="stat-value">${totalBatches}</div>
          <div style="font-size:0.75rem; color:var(--muted); margin-top:4px;">Across all products</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Low / Out of Stock</div>
          <div class="stat-value" style="color:${lowStockCount > 0 ? 'var(--warn)' : 'var(--ok)'}">${lowStockCount}</div>
          <div style="font-size:0.75rem; color:var(--muted); margin-top:4px;">Batches needing attention</div>
        </div>
      `;

      const tbody = document.querySelector('#inventoryTable tbody');
      tbody.innerHTML = '';
      const catFilter = document.getElementById('invCatFilter').value;
      const srch = document.getElementById('invSearch') ? document.getElementById('invSearch').value.toLowerCase() : '';

      batches.forEach(b => {
        const p = getProduct(b.product_id);
        if (!p) return;

        // Category filter
        if (catFilter && p.category !== catFilter) return;

        // Search filter
        const searchStr = `${b.id} ${p.name} ${b.vendor_name} ${b.purchase_price} ${b.selling_price} ${b.quantity}`.toLowerCase();
        if (srch && !searchStr.includes(srch)) return;

        const stockBadge = b.quantity > 20 ? `<span class="badge badge-ok">In Stock</span>` :
          (b.quantity > 0 ? `<span class="badge badge-warn">Low Stock</span>` : `<span class="badge badge-danger">Out of Stock</span>`);

        const gstText = p.gst ? ` <span style="font-size:0.75rem; color:var(--muted)">+${p.gst}% GST</span>` : '';

        tbody.innerHTML += `
          <tr>
            <td style="font-family: var(--mono); color: var(--muted-strong);">${b.id}</td>
            <td style="color: var(--muted);">${b.created_at.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' })}</td>
            <td style="font-weight: 500; color: var(--text-strong);">${p.name}</td>
            <td>${formatCurrency(b.purchase_price)}</td>
            <td>
              <div style="font-weight:600; color:var(--accent);">W: ${formatCurrency(b.selling_price)}</div>
              <div style="font-size:0.8rem; color:var(--warn);">R: ${formatCurrency(b.retail_price || (b.selling_price / b.quantity))}</div>
              ${gstText}
            </td>
            <td style="font-weight: 600;">${b.quantity} ${p.unit}</td>
            <td>${stockBadge}</td>
            <td style="display:flex; gap:6px; align-items:center;">
              <button class="btn-icon" onclick="editBatch('${b.id}')" title="Edit Batch" style="color:var(--accent); background:var(--bg-hover); border-radius:8px; padding:6px; cursor:pointer; border:none; display:flex; align-items:center; justify-content:center; transition:all 0.2s;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
              </button>
              <button class="btn btn-outline btn-sm" style="padding:2px 8px; font-size:0.75rem;"
                onclick="openRestockForBatch('${b.id}')" title="Add more stock for this product">+ Restock</button>
            </td>
          </tr>
        `;
      });
    }

    function openRestockForBatch(batchId) {
      const batch = batches.find(b => b.id === batchId);
      if (!batch) return;
      const product = getProduct(batch.product_id);
      if (!product) return;
      openModal('addStockEntryModal');
      // Pre-fill stock name and vendor from the existing batch
      const sel = document.getElementById('slStockName');
      if (sel) {
        for (let i = 0; i < sel.options.length; i++) {
          if (sel.options[i].value === product.name) { sel.selectedIndex = i; break; }
        }
      }
      document.getElementById('slVendorName').value = batch.vendor_name || '';
      if (typeof updateSlUnit === 'function') updateSlUnit();
    }

    function populateProductSelect() {
      const sel = document.getElementById('stockProduct');
      sel.innerHTML = '';
      products.forEach(p => {
        sel.innerHTML += `<option value="${p.id}">${p.name}</option>`;
      });
      calculateInventoryMath();
    }

    function setPricingMode(mode) {
      const segW = document.getElementById('segWholesale');
      const segR = document.getElementById('segRetail');
      const retailCol = document.getElementById('retailColumn');
      const pricingGrid = document.getElementById('pricingGrid');

      if (mode === 'retail') {
        segR.classList.add('active');
        segW.classList.remove('active');
        retailCol.style.display = 'block';
        pricingGrid.style.gridTemplateColumns = '1fr 1fr';
      } else {
        segW.classList.add('active');
        segR.classList.remove('active');
        retailCol.style.display = 'none';
        pricingGrid.style.gridTemplateColumns = '1fr';
      }
    }

    function calculateInventoryMath(calcMode = 'profit') {

      const spInput = document.getElementById('stockSP');
      const ppInput = document.getElementById('stockPP');
      const profitInput = document.getElementById('stockProfit');
      const qtyInput = document.getElementById('stockQty');
      const retailBaseInput = document.getElementById('retailBasePrice');

      const pp = parseFloat(ppInput.value) || 0;
      const qty = parseFloat(qtyInput.value) || 0;
      let sp = parseFloat(spInput.value) || 0;
      let profit = parseFloat(profitInput.value) || 0;

      // Update Retail Base Price (Auto)
      if (qty > 0) {
        retailBaseInput.value = (pp / qty).toFixed(2);
        calculateRetailMath('profit'); // Refresh retail math
      }

      // Bidirectional calculation
      if (calcMode === 'profit') {
        profit = sp > 0 ? (sp - pp) : 0;
        profitInput.value = profit > 0 ? profit.toFixed(2) : '';
      } else if (calcMode === 'sp') {
        sp = pp + profit;
        spInput.value = sp > 0 ? sp.toFixed(2) : '';
      }

      // GST Calculation
      const pid = document.getElementById('stockProduct').value;
      const product = getProduct(pid);
      const gstRate = product ? (parseFloat(product.gst) || 0) : 0;
      const outputGst = sp * (gstRate / 100);
      const finalCustomerBill = sp + outputGst;

      document.getElementById('invGstRateText').innerHTML = `GST (${gstRate}%): <strong>${formatCurrency(outputGst)}</strong>`;
      document.getElementById('invTotalText').innerText = `Total: ${formatCurrency(finalCustomerBill)}`;
    }

    function calculateRetailMath(calcMode = 'profit') {
      const rbInput = document.getElementById('retailBasePrice');
      const rsInput = document.getElementById('retailSP');
      const rpInput = document.getElementById('retailProfit');

      const rb = parseFloat(rbInput.value) || 0;
      let rs = parseFloat(rsInput.value) || 0;
      let rp = parseFloat(rpInput.value) || 0;

      if (calcMode === 'profit') {
        rp = rs > 0 ? (rs - rb) : 0;
        rpInput.value = rp > 0 ? rp.toFixed(2) : '';
      } else if (calcMode === 'sp') {
        rs = rb + rp;
        rsInput.value = rs > 0 ? rs.toFixed(2) : '';
      }

      const pid = document.getElementById('stockProduct').value;
      const product = getProduct(pid);
      const gstRate = product ? (parseFloat(product.gst) || 0) : 0;
      const outputGst = rs * (gstRate / 100);
      const finalCustomerBill = rs + outputGst;

      document.getElementById('retailGstRateText').innerHTML = `GST (${gstRate}%): <strong>${formatCurrency(outputGst)}</strong>`;
      document.getElementById('retailTotalText').innerText = `Total: ${formatCurrency(finalCustomerBill)}`;
    }

    function saveStock() {
      const pid = document.getElementById('stockProduct').value;
      const vname = document.getElementById('stockVendor').value;
      const pp = parseFloat(document.getElementById('stockPP').value);
      const sp = parseFloat(document.getElementById('stockSP').value);
      const retailSP = parseFloat(document.getElementById('retailSP').value);
      const qty = parseInt(document.getElementById('stockQty').value);
      const dateVal = document.getElementById('stockDate').value;

      if (!vname || !pp || !sp || !qty) return alert('Please fill all required fields (Wholesale prices & Qty)');

      batches.push({
        id: generateId('B'), product_id: pid, vendor_name: vname,
        purchase_price: pp, 
        selling_price: sp, // Wholesale SP
        retail_price: retailSP || (sp / qty), // Fallback to auto if empty
        quantity: qty,
        created_at: dateVal ? new Date(dateVal) : new Date()
      });

      closeModal('addStockModal');
      resetBatchModal();
      
      renderInventory();
      renderPOSItems();
      renderPOSCatFilters();
      renderLowStockBanner();
    }

    async function saveCategory() {
    const name = document.getElementById('pmCategoryName').value.trim();
    if (!name) {
        alert('Please enter a category name');
        return;
    }

    try {
        const response = await fetch('/api/add_category.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name })
        });
        const data = await response.json();

        if (data.success) {
            // Reload categories from server
            await loadCategoriesFromAPI();
            // Clear input and close modal
            document.getElementById('pmCategoryName').value = '';
            closeModal('addCategoryModal');
            // Refresh UI components that depend on categories
            populateCategorySelects();
            renderProductMaster();
            renderPOSCatFilters();
        } else {
            alert(data.error || 'Failed to add category');
        }
    } catch (err) {
        console.error(err);
        alert('Network error. Check if server is running.');
    }
}



    /* =======================================================================
       7. STOCK LIST MODULE
       ======================================================================= */

    function renderStockList() {
      const tbody = document.querySelector('#vendorSummaryTable tbody');
      tbody.innerHTML = '';

      const srch = document.getElementById('slSearch') ? document.getElementById('slSearch').value.toLowerCase() : '';

      // Group stock_list entries by vendor_name
      const vendorMap = {};
      stock_list.forEach(entry => {
        const key = entry.vendor_name;
        if (!vendorMap[key]) vendorMap[key] = [];
        vendorMap[key].push(entry);
      });

      let totalAmt = 0, totalPaid = 0, totalBal = 0;
      const vendorNames = Object.keys(vendorMap);

      vendorNames.forEach(vendorName => {
        if (srch && !vendorName.toLowerCase().includes(srch)) return;

        const entries = vendorMap[vendorName];
        const vTotal = entries.reduce((s, e) => s + e.amount, 0);
        const vPaid = entries.reduce((s, e) => s + e.paid, 0);
        const vBal = entries.reduce((s, e) => s + e.balance, 0);

        totalAmt += vTotal;
        totalPaid += vPaid;
        totalBal += vBal;

        const statusBadge = vBal <= 0
          ? '<span class="badge badge-ok">Cleared</span>'
          : '<span class="badge badge-danger">Pending</span>';

        const balColor = vBal > 0 ? 'var(--danger)' : 'var(--ok)';

        tbody.innerHTML += `
          <tr>
            <td style="font-weight: 600; color: var(--text-strong);">${vendorName}</td>
            <td style="text-align:center;">${entries.length}</td>
            <td>${formatCurrency(vTotal)}</td>
            <td style="color:var(--ok);">${formatCurrency(vPaid)}</td>
            <td style="font-weight:700; color:${balColor};">${formatCurrency(vBal)}</td>
            <td>${statusBadge}</td>
            <td style="display:flex; gap:6px; flex-wrap:wrap; align-items:center;">
              <button class="btn-icon" onclick="editVendorSummary('${vendorName.replace(/'/g, "\\'")}')" title="Edit Vendor" style="color:var(--accent); background:var(--bg-hover); border-radius:8px; padding:6px; cursor:pointer; border:none; display:flex; align-items:center; justify-content:center; transition:all 0.2s;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
              </button>
              <button class="btn btn-outline btn-sm" style="padding:3px 10px; font-size:0.78rem;" onclick="openVendorHistory('${vendorName.replace(/'/g, "\'")}')">View History</button>
              ${vBal > 0 ? `<button class="btn btn-sm" style="padding:3px 10px; font-size:0.78rem; background:var(--ok); color:#fff; border:none; border-radius:var(--radius-sm); cursor:pointer;" onclick="clearVendorBalance('${vendorName.replace(/'/g, "\'")}')">Clear Balance</button>` : ''}
            </td>
          </tr>
        `;
      });

      document.getElementById('slTotalVendors').innerText = vendorNames.length;
      document.getElementById('slTotalAmount').innerText = formatCurrency(totalAmt);
      document.getElementById('slTotalPaid').innerText = formatCurrency(totalPaid);
      document.getElementById('slTotalBalance').innerText = formatCurrency(totalBal);
    }

    function openVendorHistory(vendorName) {
      const entries = stock_list.filter(e => e.vendor_name === vendorName);
      const vTotal = entries.reduce((s, e) => s + e.amount, 0);
      const vPaid = entries.reduce((s, e) => s + e.paid, 0);
      const vBal = entries.reduce((s, e) => s + e.balance, 0);

      document.getElementById('vendorHistoryTitle').innerText = vendorName;
      document.getElementById('vendorHistorySubtitle').innerText = `${entries.length} purchase order(s) on record`;
      document.getElementById('vhTotalBilled').innerText = formatCurrency(vTotal);
      document.getElementById('vhTotalPaid').innerText = formatCurrency(vPaid);
      const balEl = document.getElementById('vhBalance');
      balEl.innerText = formatCurrency(vBal);
      balEl.style.color = vBal > 0 ? 'var(--danger)' : 'var(--ok)';

      // Render product analytics cards
      renderVendorProductAnalytics(vendorName);

      // New Purchase button
      const restockBtn = document.getElementById('vendorRestockBtn');
      restockBtn.onclick = () => {
        openModal('addStockEntryModal');
        document.getElementById('slVendorName').value = vendorName;
        if (typeof updateSlUnit === 'function') updateSlUnit();
      };

      // Build date-grouped accordion
      const container = document.getElementById('vendorHistoryBody');
      container.innerHTML = '';

      // Group entries by date string
      const grouped = {};
      entries.sort((a, b) => b.date - a.date).forEach(entry => {
        const key = entry.date.toLocaleDateString('en-IN', { day: '2-digit', month: 'long', year: 'numeric' });
        if (!grouped[key]) grouped[key] = [];
        grouped[key].push(entry);
      });

      let firstGroup = true;
      Object.entries(grouped).forEach(([dateStr, dayEntries]) => {
        const groupId = 'dg_' + dateStr.replace(/\s+/g, '_').replace(/,/g, '');
        const dayTotal = dayEntries.reduce((s, e) => s + e.amount, 0);
        const dayPaid = dayEntries.reduce((s, e) => s + e.paid, 0);
        const dayBal = dayEntries.reduce((s, e) => s + e.balance, 0);
        const isOpen = firstGroup; firstGroup = false;

        // Build purchase rows for this date
        let rows = '';
        dayEntries.forEach(entry => {
          let displayGst = entry.gst_rate;
          if (displayGst === undefined) {
            const mp = products.find(p => p.name.toLowerCase() === entry.stock_name.toLowerCase());
            displayGst = mp ? mp.gst : null;
          }
          const balColor = entry.balance > 0 ? 'var(--danger)' : 'var(--ok)';
          const statusBadge = entry.balance <= 0
            ? '<span class="badge badge-ok">Paid</span>'
            : '<span class="badge badge-danger">Pending</span>';

          rows += `<tr>
            <td style="font-family:var(--mono); color:var(--muted-strong); font-size:0.78rem;">${entry.id}</td>
            <td style="font-weight:600;">${entry.stock_name}</td>
            <td>${entry.qty_kg} kg</td>
            <td>${formatCurrency(entry.base_amount || entry.amount)}</td>
            <td style="color:var(--muted);">${displayGst ? displayGst + '%' : '-'}</td>
            <td style="font-weight:700;">${formatCurrency(entry.amount)}</td>
            <td style="color:var(--ok);">${formatCurrency(entry.paid)}</td>
            <td style="font-weight:700; color:${balColor};">${formatCurrency(entry.balance)}</td>
            <td>${statusBadge}</td>
            <td>${entry.balance > 0 ? `<button class="btn btn-sm" style="padding:2px 8px; font-size:0.75rem; background:var(--ok); color:#fff; border:none; border-radius:var(--radius-sm); cursor:pointer;" onclick="clearStockBalance('${entry.id}')">Clear</button>` : ''}</td>
          </tr>`;
        });

        container.innerHTML += `
          <div class="date-group-header ${isOpen ? 'open' : ''}" onclick="toggleDateGroup('${groupId}')">
            <div class="dg-date">📅 ${dateStr}</div>
            <div class="dg-meta">
              <span>${dayEntries.length} order(s)</span>
              <span>Total: <strong>${formatCurrency(dayTotal)}</strong></span>
              <span style="color:var(--ok)">Paid: ${formatCurrency(dayPaid)}</span>
              ${dayBal > 0 ? `<span style="color:var(--danger)">Due: ${formatCurrency(dayBal)}</span>` : '<span style="color:var(--ok)">✓ Cleared</span>'}
            </div>
            <span class="dg-arrow">▶</span>
          </div>
          <div class="date-group-body ${isOpen ? 'open' : ''}" id="${groupId}">
            <div class="table-container" style="margin-bottom:0;">
              <table style="font-size:0.85rem;">
                <thead><tr>
                  <th>ID</th><th>Stock Name</th><th>Qty</th><th>Base Amt</th><th>GST</th>
                  <th>Total Bill</th><th>Paid</th><th>Balance</th><th>Status</th><th>Action</th>
                </tr></thead>
                <tbody>${rows}</tbody>
              </table>
            </div>
          </div>`;
      });

      // Navigate to vendor history sub-page
      navigateTo('vendorhistory');
    }

    function toggleDateGroup(id) {
      const body = document.getElementById(id);
      const header = body.previousElementSibling;
      body.classList.toggle('open');
      header.classList.toggle('open');
    }

    function clearVendorBalance(vendorName) {
      if (confirm(`Mark ALL pending balances for ${vendorName} as fully paid?`)) {
        stock_list.filter(e => e.vendor_name === vendorName && e.balance > 0).forEach(e => {
          e.paid = e.amount;
          e.balance = 0;
        });
        renderStockList();
        openVendorHistory(vendorName); // Refresh sub-page
      }
    }

    function clearStockBalance(id) {
      if (confirm('Are you sure you want to mark this vendor balance as fully paid?')) {
        const entry = stock_list.find(e => e.id === id);
        if (entry) {
          entry.paid = entry.amount;
          entry.balance = 0;
          renderStockList();
        }
      }
    }

    function restockEntry(id) {
      const entry = stock_list.find(e => e.id === id);
      if (!entry) return;

      openModal('addStockEntryModal');
      document.getElementById('slStockName').value = entry.stock_name;
      document.getElementById('slVendorName').value = entry.vendor_name;

      updateSlUnit(); // Re-calculates UI labels and GST specific to this auto-filled item
    }

    function saveStockEntry() {
      const sel = document.getElementById('slStockName');
      const selected = sel.options[sel.selectedIndex];
      const stockName = sel.value.trim();
      const vendorName = document.getElementById('slVendorName').value.trim();
      const qty = parseFloat(document.getElementById('slQty').value) || 0;
      const baseAmount = parseFloat(document.getElementById('slAmount').value) || 0;
      const paid = parseFloat(document.getElementById('slPaid').value) || 0;

      if (!stockName || !vendorName || qty <= 0 || baseAmount <= 0) {
        return alert('Please fill all required fields (Stock Name, Vendor, Qty, Base Amount).');
      }

      const gstRate = parseFloat(selected ? selected.getAttribute('data-gst') : 0) || 0;
      const gstAmount = baseAmount * (gstRate / 100);
      const totalAmount = baseAmount + gstAmount;

      if (paid > totalAmount) {
        return alert('Amount Paid cannot exceed Total Bill Amount.');
      }

      stock_list.push({
        id: generateId('SL'),
        stock_name: stockName,
        vendor_name: vendorName,
        qty_kg: qty,
        amount: totalAmount, // For backward compatibility with rendering, we treat 'amount' as the total bill paid to vendor
        base_amount: baseAmount,
        gst_rate: gstRate,
        gst_amount: gstAmount,
        paid: paid,
        balance: totalAmount - paid,
        date: (() => { const d = document.getElementById('slPurchaseDate').value; return d ? new Date(d) : new Date(); })()
      });

      // Clear form
      document.getElementById('slStockName').value = '';
      document.getElementById('slVendorName').value = '';
      document.getElementById('slQty').value = '';
      document.getElementById('slAmount').value = '';
      document.getElementById('slPaid').value = '';
      document.getElementById('slPurchaseDate').value = new Date().toISOString().split('T')[0];
      document.getElementById('slGstRateText').innerText = 'GST (0%): ₹0.00';
      document.getElementById('slTotalText').innerText = 'Total Bill: ₹0.00';

      closeModal('addStockEntryModal');
      renderStockList();
    }


