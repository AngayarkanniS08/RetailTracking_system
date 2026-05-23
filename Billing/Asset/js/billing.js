    /* =======================================================================
       4. BILLING / POS MODULE
       ======================================================================= */

    function renderPOSCatFilters() {
      const container = document.getElementById('posCatFilters');
      const allCount = batches.filter(b => b.quantity > 0).length;
      let html = `<button class="cat-btn ${activePosCategory === '' ? 'active' : ''}" onclick="setPosCategory('')">All<span class="product-count">${allCount}</span></button>`;

      categories.forEach(cat => {
        const catProducts = products.filter(p => p.category === cat).map(p => p.id);
        const count = batches.filter(b => catProducts.includes(b.product_id) && b.quantity > 0).length;
        if (count > 0) {
          html += `<button class="cat-btn ${activePosCategory === cat ? 'active' : ''}" onclick="setPosCategory('${cat}')">${cat}<span class="product-count">${count}</span></button>`;
        }
      });
      container.innerHTML = html;
    }

    function setPosCategory(cat) {
      activePosCategory = cat;
      renderPOSCatFilters();
      renderPOSItems();
    }

    function renderPOSItems() {
      const term = document.getElementById('posSearch').value.toLowerCase();
      const grid = document.getElementById('posItemsGrid');
      grid.innerHTML = '';

      let tableHtml = `
        <table class="pos-table">
          <thead>
            <tr>
              <th>Product Details</th>
              <th>Category</th>
              <th>Price</th>
              <th>Stock</th>
              <th style="text-align:right">Action</th>
            </tr>
          </thead>
          <tbody>
      `;

      let hasItems = false;
      batches.forEach(b => {
        const p = getProduct(b.product_id);
        if (!p) return;

        // Category filter
        if (activePosCategory && p.category !== activePosCategory) return;

        const searchStr = `${p.name} ${b.id} ${b.vendor_name} ${p.category}`.toLowerCase();
        if (term && !searchStr.includes(term)) return;

        // Skip zero stock in POS unless specifically searching
        if (b.quantity <= 0 && !term) return;

        hasItems = true;
        const stockColor = b.quantity > 10 ? 'var(--ok)' : (b.quantity > 0 ? 'var(--warn)' : 'var(--danger)');

        tableHtml += `
          <tr onclick="addToCart('${b.id}')">
            <td>
              <div class="t-name">${p.name}</div>
              <div style="font-size: 0.75rem; color: var(--muted);">Batch: ${b.id} | ${b.vendor_name}</div>
            </td>
            <td><span class="badge" style="font-size: 0.7rem; padding: 2px 8px; background: var(--bg-hover);">${p.category}</span></td>
            <td class="t-price">${formatCurrency(b.selling_price)}</td>
            <td class="t-stock" style="color: ${stockColor}">${b.quantity} ${p.unit}</td>
            <td style="text-align:right">
              <button class="btn btn-primary btn-sm" style="padding: 4px 12px; font-size: 0.75rem;">+ Add</button>
            </td>
          </tr>
        `;
      });

      tableHtml += '</tbody></table>';

      if (!hasItems) {
        grid.innerHTML = `
          <div style="text-align:center; padding: 60px 20px; background: var(--bg-card); border-radius: var(--radius-md); border: 1px dashed var(--border);">
            <div style="font-size: 2rem; margin-bottom: 10px;">🔍</div>
            <div style="color: var(--text-strong); font-weight: 600;">No products found</div>
            <div style="color: var(--muted); font-size: 0.85rem; margin-top: 4px;">Try adjusting your search or category filters</div>
          </div>
        `;
      } else {
        grid.innerHTML = tableHtml;
      }
    }

    function addToCart(batchId) {
      const batch = batches.find(b => b.id === batchId);
      if (!batch || batch.quantity <= 0) return alert('Out of stock!');

      const existing = cart.find(c => c.batch_id === batchId);
      if (existing) {
        if (existing.qty < batch.quantity) existing.qty++;
        else alert('Cannot exceed available stock.');
      } else {
        cart.push({
          batch_id: batchId,
          product_id: batch.product_id,
          qty: 1,
          price: batch.selling_price,
          max_qty: batch.quantity,
          product_name: getProduct(batch.product_id).name,
          rate_type: 'wholesale'
        });
      }
      renderCart();
    }

    function updateCartQty(batchId, delta) {
      const item = cart.find(c => c.batch_id === batchId);
      if (!item) return;

      item.qty += delta;
      if (item.qty <= 0) {
        cart = cart.filter(c => c.batch_id !== batchId);
      } else if (item.qty > item.max_qty) {
        item.qty = item.max_qty;
      }
      renderCart();
    }

    function renderCart() {
      const container = document.getElementById('cartItemsContainer');
      if (cart.length === 0) {
        container.innerHTML = `<div class="text-center" style="color:var(--muted); margin-top: 50px;">Cart is empty. Select items to bill.</div>`;
      } else {
        let html = `
          <table class="cart-table">
            <thead>
              <tr>
                <th>Item</th>
                <th>Qty</th>
                <th style="text-align:right">Total</th>
              </tr>
            </thead>
            <tbody>
        `;

        let itemCount = 0;
        cart.forEach((c, idx) => {
          itemCount++;
          const product = getProduct(c.product_id);
          const unit = product ? product.unit : '';
          const itemDisc = c.discount || 0;
          const lineTotal = (c.price * c.qty) - itemDisc;

          html += `
            <tr>
              <td>
                <span class="cart-name" title="${c.product_name}">${c.product_name}</span>
                <div style="display:flex; align-items:center; gap:5px; margin-top:2px;">
                  <span class="cart-details">${formatCurrency(c.price)}/${unit}</span>
                  <button class="btn btn-outline" style="padding:0px 4px; font-size:0.6rem; border-radius:4px; height:18px; line-height:1; min-width:unset; background:${c.rate_type === 'wholesale' ? 'var(--accent)' : 'transparent'}; color:${c.rate_type === 'wholesale' ? '#fff' : 'var(--accent)'}; border-color:var(--accent);" onclick="toggleCartRate(${idx})">${c.rate_type === 'wholesale' ? '📦 Bulk' : '✂️ Loose'}</button>
                  ${c.rate_type === 'retail' ? `
                    <div style="display:flex; align-items:center; gap:3px;">
                      <span style="font-size:0.7rem; color:var(--muted)">₹</span>
                      <input type="number" class="qty-input" style="width:50px; font-size:0.75rem; padding:1px 4px; height:18px;" value="${c.price}" oninput="setCartItemPrice(${idx}, this.value)">
                    </div>
                  ` : ''}
                </div>

                ${itemDisc > 0 ? `<div style="font-size:0.7rem; color:var(--ok);">Disc: -${formatCurrency(itemDisc)}</div>` : ''}
              </td>
              <td>
                <div class="ci-actions">
                  <button class="qty-btn" onclick="updateCartQty('${c.batch_id}', -1)">-</button>
                  <span style="font-weight:600; width:15px; text-align:center; font-size:0.8rem;">${c.qty}</span>
                  <button class="qty-btn" onclick="updateCartQty('${c.batch_id}', 1)">+</button>
                </div>
                <input type="number" placeholder="Disc ₹" min="0" value="${itemDisc || ''}" 
                  class="qty-input" style="margin-top:4px;"
                  oninput="setItemDiscount(${idx}, this.value)">
              </td>
              <td style="text-align:right; font-weight:600; color:var(--text-strong);" id="lineTotal-${idx}">
                ${formatCurrency(lineTotal)}
              </td>
            </tr>
          `;
        });

        html += `</tbody></table>`;
        container.innerHTML = html;
      }
      calculateCart();
    }

    function toggleCartRate(idx) {
      const item = cart[idx];
      if (!item) return;
      const batch = batches.find(b => b.id === item.batch_id);
      if (!batch) return;

      if (item.rate_type === 'wholesale') {
        item.rate_type = 'retail';
        item.price = batch.retail_price || (batch.selling_price / batch.quantity);
      } else {
        item.rate_type = 'wholesale';
        item.price = batch.selling_price;
      }
      renderCart();
    }

    function setItemDiscount(idx, val) {
      const disc = parseFloat(val) || 0;
      const item = cart[idx];
      if (!item) return;
      const maxLine = item.price * item.qty;
      item.discount = Math.min(disc, maxLine); // Can't discount more than line total
      calculateCart();
    }

    function setCartItemPrice(idx, val) {
      const price = parseFloat(val) || 0;
      const item = cart[idx];
      if (!item) return;
      item.price = price;
      calculateCart();

      // Update line total display instantly
      const lineTotalEl = document.getElementById(`lineTotal-${idx}`);
      if (lineTotalEl) {
        const total = (item.price * item.qty) - (item.discount || 0);
        lineTotalEl.innerText = formatCurrency(total);
      }
    }



    function calculateCart() {
      let grossSubtotal = 0;
      let totalGst = 0;
      let totalItemDiscount = 0;

      const isGst = document.getElementById('enableGstToggle') ? document.getElementById('enableGstToggle').checked : true;
      const billDiscount = parseFloat(document.getElementById('cartDiscountInput') ? document.getElementById('cartDiscountInput').value : 0) || 0;

      cart.forEach(c => {
        const product = getProduct(c.product_id);
        const itemBaseTotal = c.price * c.qty;
        const itemDisc = c.discount || 0;
        totalItemDiscount += itemDisc;
        const afterItemDisc = itemBaseTotal - itemDisc;
        grossSubtotal += itemBaseTotal;

        if (isGst && product && product.gst) {
          totalGst += afterItemDisc * (parseFloat(product.gst) / 100);
        }
      });

      const netSubtotal = grossSubtotal - totalItemDiscount;
      const total = Math.max(0, netSubtotal - billDiscount + totalGst);

      // Main POS display
      if (document.getElementById('cartSubtotal')) document.getElementById('cartSubtotal').innerText = formatCurrency(netSubtotal);
      if (document.getElementById('cartGst')) document.getElementById('cartGst').innerText = totalGst > 0 ? formatCurrency(totalGst) : "₹0.00";
      if (document.getElementById('cartTotal')) document.getElementById('cartTotal').innerText = formatCurrency(total);

      // Auto-fill amount paid if no customer is selected
      const custId = document.getElementById('billCustomerSelect') ? document.getElementById('billCustomerSelect').value : null;
      if (!custId && document.getElementById('amountPaidInput')) {
        document.getElementById('amountPaidInput').value = total.toFixed(2);
      }

      return { 
        grossSubtotal, 
        netSubtotal, 
        gst: totalGst, 
        itemDiscount: totalItemDiscount, 
        discount: billDiscount, 
        total, 
        isGst 
      };
    }

    function processCheckout() {
      console.log("Process Checkout Button Clicked");
      try {
        if (!cart || cart.length === 0) {
          console.warn("Cart is empty");
          return alert('Cart is empty!');
        }

        console.log("Calculating cart...");
        const calc = calculateCart();
        const total = calc.total;

        const amountPaidEl = document.getElementById('amountPaidInput');
        const customerSelectEl = document.getElementById('billCustomerSelect');

        if (!amountPaidEl || !customerSelectEl) {
          console.error("Missing DOM elements:", { amountPaidEl, customerSelectEl });
          throw new Error("Billing system internal error: Missing input fields.");
        }

        let amountPaid = parseFloat(amountPaidEl.value) || 0;
        const customerId = customerSelectEl.value;

        console.log("Validation check:", { customerId, amountPaid, total });
        if (!customerId && amountPaid < total) {
          return alert('Walk-in customers must pay in full. For credit, please select a registered customer.');
        }

        // ---- Populate LEFT panel: product list ----
        const itemsPanel = document.getElementById('checkoutItemsPanel');
        const summaryPanel = document.getElementById('checkoutSummaryPanel');
        if (!itemsPanel || !summaryPanel) throw new Error('Checkout panels not found in DOM.');

        let itemsHtml = '';
        let itemCount = 0;
          cart.forEach((item, idx) => {
            const product = getProduct(item.product_id);
            const unit = product ? product.unit : '';
            const productName = product ? product.name : item.product_id;
            const itemDisc = item.discount || 0;
            const unitPrice = item.price || 0;
            const lineGross = item.qty * unitPrice;
            const lineTotal = lineGross - itemDisc;
            itemCount++;

            itemsHtml += `
              <div class="checkout-item-card" data-idx="${idx}" style="background:var(--card); border:1px solid var(--border); border-radius:var(--radius-lg); padding:1.25rem; margin-bottom:15px; box-shadow:var(--shadow-md);">
                <!-- Header -->
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                  <div style="display:flex; gap:12px; align-items:center;">
                    <div style="width:40px; height:40px; border-radius:8px; background:var(--bg-100); display:flex; align-items:center; justify-content:center; font-size:1.2rem;">🏷️</div>
                    <div>
                      <div style="font-weight:700; font-size:1.1rem; color:var(--text-strong);">${productName}</div>
                      <div style="font-size:0.8rem; color:var(--muted-strong);">Base: ₹${(product ? (parseFloat(product.selling_price) || 0) : 0).toFixed(2)} / ${unit}</div>
                    </div>
                  </div>
                  
                  <!-- MODE TOGGLE -->
                  <div style="display:flex; align-items:center; gap:10px; background:var(--bg-100); padding:6px 12px; border-radius:30px; border:1px solid var(--border);">
                    <span style="font-size:0.75rem; font-weight:700; color:${item.rate_type !== 'retail' ? 'var(--text-strong)' : 'var(--muted-strong)'};">WHOLESALE</span>
                    <label class="mode-switch">
                      <input type="checkbox" onchange="checkoutToggleRate(${idx})" ${item.rate_type === 'retail' ? 'checked' : ''}>
                      <span class="mode-slider"></span>
                    </label>
                    <span style="font-size:0.75rem; font-weight:700; color:${item.rate_type === 'retail' ? 'var(--accent)' : 'var(--muted-strong)'};">RETAIL</span>
                  </div>
                </div>

                <!-- Input Fields Grid -->
                <div style="display:grid; grid-template-columns: 1.2fr 1.2fr 1fr auto; gap:15px; align-items:flex-end; padding-top:10px; border-top:1px solid var(--border);">
                  <!-- Quantity Input -->
                  <div style="display:flex; flex-direction:column; gap:6px;">
                    <label style="font-size:0.7rem; font-weight:800; color:var(--muted-strong); text-transform:uppercase;">Quantity (${unit})</label>
                    <input type="number" step="0.01" value="${item.qty}" inputmode="decimal"
                      style="background:var(--bg-100); border:1px solid var(--border); border-radius:8px; padding:10px; color:var(--text-strong); font-weight:800; font-size:1.1rem; text-align:center; width:100%;"
                      onfocus="this.select()"
                      oninput="checkoutSetQty(${idx}, this.value)">
                  </div>

                  <!-- Unit Price Input -->
                  <div style="display:flex; flex-direction:column; gap:6px;">
                    <label style="font-size:0.7rem; font-weight:800; color:var(--muted-strong); text-transform:uppercase;">Selling Price</label>
                    <div style="display:flex; align-items:center; background:var(--bg-100); border:1px solid ${item.rate_type === 'retail' ? 'var(--accent)' : 'var(--border)'}; border-radius:8px; padding:2px 10px; height:46px;">
                      <span style="color:${item.rate_type === 'retail' ? 'var(--accent)' : 'var(--muted)'}; font-weight:800; margin-right:5px;">₹</span>
                      <input type="number" step="0.01" value="${item.price}" inputmode="decimal"
                        style="flex:1; border:none; background:transparent; color:var(--text-strong); font-weight:800; font-size:1.1rem; text-align:right;"
                        onfocus="this.select()"
                        oninput="checkoutSetPrice(${idx}, this.value)"
                        ${item.rate_type !== 'retail' ? 'readonly' : ''}>
                    </div>
                  </div>

                  <!-- Discount -->
                  <div style="display:flex; flex-direction:column; gap:6px;">
                    <label style="font-size:0.7rem; font-weight:800; color:var(--muted-strong); text-transform:uppercase;">Discount</label>
                    <div style="display:flex; align-items:center; background:var(--bg-100); border:1px solid var(--border); border-radius:8px; padding:2px 10px; height:46px;">
                      <span style="color:#10b981; font-weight:800; margin-right:5px;">₹</span>
                      <input type="number" value="${itemDisc || ''}" placeholder="0" inputmode="decimal"
                        style="flex:1; border:none; background:transparent; color:#10b981; font-weight:800; font-size:1.1rem; text-align:right;"
                        onfocus="this.select()"
                        oninput="checkoutSetItemDiscount(${idx}, this.value)">
                    </div>
                  </div>

                  <!-- Line Total Display -->
                  <div style="text-align:right; min-width:120px;">
                    <div style="font-size:0.7rem; font-weight:800; color:var(--muted-strong); text-transform:uppercase; margin-bottom:5px;">Line Total</div>
                    <div id="checkoutLineTotal-${idx}" style="font-size:1.5rem; font-weight:900; color:var(--text-strong);">${formatCurrency(lineTotal)}</div>
                  </div>
                </div>
              </div>
            `;
          });
        itemsPanel.innerHTML = itemsHtml || '<div style="color:#8888a0; text-align:center; padding:2rem;">No items in cart.</div>';

        // ---- Populate RIGHT panel: billing summary ----
        const customerName = customerId ? getCustomer(customerId).name : 'Walk-in Customer';
        const customerPhone = customerId ? getCustomer(customerId).phone : '—';
        const dateStr = new Date().toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
        const timeStr = new Date().toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' });

        summaryPanel.innerHTML = `
          <!-- Customer Card -->
          <div style="background:#1e2233; border:1.5px solid #ef4444; border-radius:12px; padding:1rem 1.2rem; margin-bottom:1.2rem;">
            <div style="font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:#ef4444; margin-bottom:6px;">Customer</div>
            <div style="font-weight:800; font-size:1.1rem; color:#f4f4f5;">${customerName}</div>
            <div style="font-size:0.82rem; color:#8888a0; margin-top:2px;">${customerPhone !== '\u2014' ? '\ud83d\udcde ' + customerPhone : 'Walk-in (No account)'}</div>
          </div>

          <!-- Date & Items count -->
          <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:1.2rem;">
            <div style="background:#1a1d27; border:1px solid #2a2d3a; border-radius:10px; padding:0.9rem; text-align:center;">
              <div style="font-size:0.7rem; color:#8888a0; text-transform:uppercase; margin-bottom:4px;">Date</div>
              <div style="font-weight:700; font-size:0.92rem; color:#f4f4f5;">${dateStr}</div>
              <div style="font-size:0.72rem; color:#8888a0;">${timeStr}</div>
            </div>
            <div style="background:#1a1d27; border:1px solid #2a2d3a; border-radius:10px; padding:0.9rem; text-align:center;">
              <div style="font-size:0.7rem; color:#8888a0; text-transform:uppercase; margin-bottom:4px;">Items</div>
              <div style="font-weight:700; font-size:1.4rem; color:#ef4444;">${itemCount}</div>
              <div style="font-size:0.72rem; color:#8888a0;">products</div>
            </div>
          </div>

            <!-- Bill Breakdown -->
            <div style="background:#1a1d27; border-radius:12px; border:1px solid #2a2d3a; padding:1rem; margin-bottom:1rem;">
              <div style="font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:#8888a0; margin-bottom:10px; border-bottom:1px solid #2a2d3a; padding-bottom:8px;">Bill Breakdown</div>

              <div style="display:flex; justify-content:space-between; margin-bottom:8px; font-size:0.9rem;">
                <span style="color:#a0a0b0;">Gross Total</span>
                <span style="font-weight:600; color:#f4f4f5;">${formatCurrency(calc.grossSubtotal)}</span>
              </div>
              
              <div style="display:flex; justify-content:space-between; margin-bottom:8px; font-size:0.9rem;">
                <span style="color:#10b981;">Item Discounts</span>
                <span style="color:#10b981; font-weight:600;">-${formatCurrency(calc.itemDiscount)}</span>
              </div>

              <div style="display:flex; justify-content:space-between; margin-bottom:12px; font-size:0.95rem; font-weight:700; color:#f4f4f5; padding:8px 0; border-top:1px dashed #2a2d3a; border-bottom:1px dashed #2a2d3a;">
                <span>Subtotal (Net)</span>
                <span>${formatCurrency(calc.netSubtotal)}</span>
              </div>

              <div style="display:flex; justify-content:space-between; margin-top:12px; margin-bottom:10px; font-size:0.9rem; align-items:center;">
                <span style="color:#ef4444; font-weight:600;">Bill Discount</span>
                <div style="display:flex; align-items:center; gap:10px;">
                  <input type="number" value="${calc.discount || ''}" placeholder="₹"
                    oninput="checkoutSetBillDiscount(this.value)"
                    style="width:75px; height:32px; background:#252836; border:1px solid #ef4444; color:#ef4444; border-radius:8px; padding:4px 10px; font-size:0.95rem; font-weight:800; text-align:right;">
                  <span style="color:#ef4444; font-weight:700;">-${formatCurrency(calc.discount)}</span>
                </div>
              </div>

              <div style="display:flex; justify-content:space-between; margin-bottom:8px; font-size:0.9rem;">
                <span style="color:#a0a0b0;">GST (Tax 5%)</span>
                <span style="font-weight:600; color:#f4f4f5;">${formatCurrency(calc.gst)}</span>
              </div>

              <div style="display:flex; justify-content:space-between; margin-top:12px; padding-top:12px; border-top:2px solid #2a2d3a;">
                <span style="font-size:1.1rem; font-weight:800; color:#f4f4f5;">Grand Total</span>
                <span style="font-size:1.4rem; font-weight:900; color:#ef4444;">${formatCurrency(total)}</span>
              </div>
            </div>

          <!-- Payment Status -->
          <div style="display:flex; justify-content:space-between; padding:12px 14px; background:rgba(16,185,129,0.15); border-radius:10px; margin-bottom:${(total - amountPaid) > 0 ? '8px' : '0'}; border:1px solid rgba(16,185,129,0.3);">
            <span style="color:#10b981; font-weight:600;">💵 Amount Received</span>
            <span style="color:#10b981; font-weight:800; font-size:1rem;">${formatCurrency(amountPaid)}</span>
          </div>
          ${(total - amountPaid) > 0 ? `
          <div style="display:flex; justify-content:space-between; padding:12px 14px; background:rgba(239,68,68,0.15); border-radius:10px; border:1px solid rgba(239,68,68,0.3);">
            <span style="color:#ef4444; font-weight:600;">📋 Balance Due (Credit)</span>
            <span style="color:#ef4444; font-weight:800; font-size:1rem;">${formatCurrency(total - amountPaid)}</span>
          </div>` : `
          <div style="display:flex; justify-content:space-between; padding:12px 14px; background:rgba(16,185,129,0.1); border-radius:10px; border:1px solid rgba(16,185,129,0.25);">
            <span style="color:#10b981; font-weight:600;">✅ Fully Paid</span>
            <span style="color:#10b981; font-weight:700;">No balance</span>
          </div>`}
        `;

        itemsPanel.innerHTML = itemsHtml;
        openModal('checkoutConfirmModal');
      } catch (err) {
        console.error("FATAL processCheckout Error:", err);
        alert('Critical Error: ' + err.message);
      }
    }

    function checkoutUpdateQty(idx, delta) {
      const item = cart[idx];
      if (!item) return;
      const batch = batches.find(b => b.id === item.batch_id);
      const maxQty = batch ? batch.quantity : 999;
      
      const newQty = item.qty + delta;
      if (newQty <= 0) {
        if (confirm('Remove item from cart?')) {
          cart.splice(idx, 1);
        } else {
          return;
        }
      } else if (newQty > maxQty) {
        alert('Not enough stock in this batch!');
        return;
      } else {
        item.qty = newQty;
      }
      
      renderCart(); 
      processCheckout(); 
    }

    function checkoutSetItemDiscount(idx, val) {
      const disc = parseFloat(val) || 0;
      const item = cart[idx];
      if (!item) return;
      item.discount = disc;
      
      updateCheckoutTotals();
    }

    function checkoutSetBillDiscount(val) {
      const discInput = document.getElementById('cartDiscountInput');
      if (discInput) {
        discInput.value = val;
      }
      updateCheckoutTotals();
    }

    function checkoutSetQty(idx, val) {
      const qty = parseFloat(val) || 0;
      if (cart[idx]) {
        cart[idx].qty = qty;
        updateCheckoutTotals(); 
      }
    }

    function checkoutSetPrice(idx, val) {
      const price = parseFloat(val) || 0;
      if (cart[idx]) {
        cart[idx].price = price;
        updateCheckoutTotals();
      }
    }

    function updateCheckoutTotals() {
      const calc = calculateCart();
      
      // Update individual line totals
      cart.forEach((item, idx) => {
        const el = document.getElementById(`checkoutLineTotal-${idx}`);
        if (el) {
          const lineTotal = (item.price * item.qty) - (item.discount || 0);
          el.innerText = formatCurrency(lineTotal);
        }
      });

      // Refresh the right summary panel (it's safe to re-render this as it has fewer inputs)
      // Actually, let's just update the specific total fields in the summary if they exist
      const summaryPanel = document.getElementById('checkoutSummaryPanel');
      if (summaryPanel) {
         // To keep it smooth, we only update the inner spans of the summary if possible
         // For now, re-rendering summary is okay as focus is usually on left panel inputs
         processCheckoutSummary(calc);
      }
    }

    function processCheckoutSummary(calc) {
       const total = calc.total;
       const summaryPanel = document.getElementById('checkoutSummaryPanel');
       const customerSelectEl = document.getElementById('billCustomerSelect');
       const customerId = customerSelectEl ? customerSelectEl.value : null;
       const amountPaidInput = document.getElementById('amountPaidInput');
       const amountPaid = parseFloat(amountPaidInput ? amountPaidInput.value : 0) || 0;

       const customerName = customerId ? getCustomer(customerId).name : 'Walk-in Customer';
       const customerPhone = customerId ? getCustomer(customerId).phone : '—';
       const dateStr = new Date().toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
       const timeStr = new Date().toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' });

       summaryPanel.innerHTML = `
          <!-- Customer Card -->
          <div style="background:#1e2233; border:1.5px solid #ef4444; border-radius:12px; padding:1rem 1.2rem; margin-bottom:1.2rem;">
            <div style="font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:#ef4444; margin-bottom:6px;">Customer</div>
            <div style="font-weight:800; font-size:1.1rem; color:#f4f4f5;">${customerName}</div>
            <div style="font-size:0.82rem; color:#8888a0; margin-top:2px;">${customerPhone !== '\u2014' ? '\ud83d\udcde ' + customerPhone : 'Walk-in (No account)'}</div>
          </div>

          <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:1.2rem;">
            <div style="background:#1a1d27; border:1px solid #2a2d3a; border-radius:10px; padding:0.9rem; text-align:center;">
              <div style="font-size:0.7rem; color:#8888a0; text-transform:uppercase; margin-bottom:4px;">Date</div>
              <div style="font-weight:700; font-size:0.92rem; color:#f4f4f5;">${dateStr}</div>
              <div style="font-size:0.72rem; color:#8888a0;">${timeStr}</div>
            </div>
            <div style="background:#1a1d27; border:1px solid #2a2d3a; border-radius:10px; padding:0.9rem; text-align:center;">
              <div style="font-size:0.7rem; color:#8888a0; text-transform:uppercase; margin-bottom:4px;">Items</div>
              <div style="font-weight:700; font-size:1.4rem; color:#ef4444;">${cart.length}</div>
              <div style="font-size:0.72rem; color:#8888a0;">products</div>
            </div>
          </div>

          <div style="background:#1a1d27; border-radius:12px; border:1px solid #2a2d3a; padding:1rem; margin-bottom:1rem;">
              <div style="font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:#8888a0; margin-bottom:10px; border-bottom:1px solid #2a2d3a; padding-bottom:8px;">Bill Breakdown</div>
              <div style="display:flex; justify-content:space-between; margin-bottom:8px; font-size:0.9rem;">
                <span style="color:#a0a0b0;">Gross Total</span>
                <span style="font-weight:600; color:#f4f4f5;">${formatCurrency(calc.grossSubtotal)}</span>
              </div>
              <div style="display:flex; justify-content:space-between; margin-bottom:8px; font-size:0.9rem;">
                <span style="color:#10b981;">Item Discounts</span>
                <span style="color:#10b981; font-weight:600;">-${formatCurrency(calc.itemDiscount)}</span>
              </div>
              <div style="display:flex; justify-content:space-between; margin-bottom:12px; font-size:0.95rem; font-weight:700; color:#f4f4f5; padding:8px 0; border-top:1px dashed #2a2d3a; border-bottom:1px dashed #2a2d3a;">
                <span>Subtotal (Net)</span>
                <span>${formatCurrency(calc.netSubtotal)}</span>
              </div>
              <div style="display:flex; justify-content:space-between; margin-top:12px; margin-bottom:10px; font-size:0.9rem; align-items:center;">
                <span style="color:#ef4444; font-weight:600;">Bill Discount</span>
                <div style="display:flex; align-items:center; gap:10px;">
                  <input type="number" value="${calc.discount || ''}" placeholder="₹"
                    oninput="checkoutSetBillDiscount(this.value)"
                    style="width:75px; height:32px; background:#252836; border:1px solid #ef4444; color:#ef4444; border-radius:8px; padding:4px 10px; font-size:0.95rem; font-weight:800; text-align:right;">
                  <span style="color:#ef4444; font-weight:700;">-${formatCurrency(calc.discount)}</span>
                </div>
              </div>
              <div style="display:flex; justify-content:space-between; margin-bottom:8px; font-size:0.9rem;">
                <span style="color:#a0a0b0;">GST (Tax 5%)</span>
                <span style="font-weight:600; color:#f4f4f5;">${formatCurrency(calc.gst)}</span>
              </div>
              <div style="display:flex; justify-content:space-between; margin-top:12px; padding-top:12px; border-top:2px solid #2a2d3a;">
                <span style="font-size:1.1rem; font-weight:800; color:#f4f4f5;">Grand Total</span>
                <span style="font-size:1.4rem; font-weight:900; color:#ef4444;">${formatCurrency(total)}</span>
              </div>
          </div>

          <div style="display:flex; justify-content:space-between; padding:12px 14px; background:rgba(16,185,129,0.15); border-radius:10px; border:1px solid rgba(16,185,129,0.3);">
            <span style="color:#10b981; font-weight:600;">💵 Amount Received</span>
            <span style="color:#10b981; font-weight:800; font-size:1rem;">${formatCurrency(amountPaid)}</span>
          </div>
          ${(total - amountPaid) > 0 ? `
          <div style="display:flex; justify-content:space-between; padding:12px 14px; background:rgba(239,68,68,0.15); border-radius:10px; border:1px solid rgba(239,68,68,0.3); margin-top:8px;">
            <span style="color:#ef4444; font-weight:600;">📋 Balance Due (Credit)</span>
            <span style="color:#ef4444; font-weight:800; font-size:1rem;">${formatCurrency(total - amountPaid)}</span>
          </div>` : ''}
       `;
    }

    function checkoutToggleRate(idx) {
      const item = cart[idx];
      if (!item) return;
      
      // Find the specific batch this item belongs to
      const batch = batches.find(b => b.id === item.batch_id);
      
      if (item.rate_type === 'retail') {
        item.rate_type = 'wholesale';
        item.price = batch ? (batch.selling_price || 0) : (item.price || 0);
      } else {
        item.rate_type = 'retail';
        // Use retail_price if available, otherwise derive it
        if (batch && batch.retail_price) {
          item.price = batch.retail_price;
        } else if (batch && batch.quantity > 0) {
          item.price = batch.selling_price / batch.quantity;
        } else {
          item.price = item.price || 0;
        }
      }
      processCheckout();
    }

    function confirmCheckout() {
      const calc = calculateCart();
      const total = calc.total;
      let amountPaid = parseFloat(document.getElementById('amountPaidInput').value) || 0;
      const customerId = document.getElementById('billCustomerSelect').value;

      // 1. Create Sale Record
      const saleId = generateId('INV');
      const newSale = {
        id: saleId,
        total_amount: total,
        gst_enabled: calc.isGst,
        customer_id: customerId || null,
        created_at: new Date()
      };
      sales.push(newSale);

      // 2. Deduct Stock & Create Sale Items
      cart.forEach(item => {
        sale_items.push({ id: generateId('SI'), sale_id: saleId, batch_id: item.batch_id, product_id: item.product_id, product_name: item.product_name, quantity: item.qty, qty: item.qty, price: item.price });
        const batch = batches.find(b => b.id === item.batch_id);
        if (batch) batch.quantity -= item.qty;
      });

      // 3. Handle Credit Ledger if customer selected
      if (customerId) {
        let balance = total - amountPaid;
        credit_ledger.push({
          id: generateId('L'),
          customer_id: customerId,
          sale_id: saleId,
          amount_billed: total,
          amount_paid: amountPaid,
          balance: balance,
          created_at: new Date()
        });
      }

      // 4. Print Thermal Bill
      printThermalBill(saleId, cart, calc, amountPaid, customerId);

      // 5. Reset POS
      cart = [];
      document.getElementById('amountPaidInput').value = '';
      document.getElementById('cartDiscountInput').value = '';
      document.getElementById('billCustomerSelect').value = '';

      closeModal('checkoutConfirmModal');
      renderCart();
      renderPOSItems();
      renderLowStockBanner();
      renderReports();

      // Flash success message
      alert('Bill Generated Successfully!');
    }

    function printThermalBill(invoiceId, items, calc, paid, custId) {
      const shopName = "PUDEERA FASHION";
      const address = "Shop no 9, 1st floor, Vallioor busstand";
      const dateStr = new Date().toLocaleString();
      const customer = custId ? getCustomer(custId).name : "Walk-in";
      const billType = calc.isGst ? "Tax Invoice (GST 18%)" : "Estimate / Non-GST Bill";

      let receipt = `
========================================
         ${shopName}
         ${address}
========================================
Invoice: ${invoiceId}
Date: ${dateStr}
Customer: ${customer}
Type: ${billType}
----------------------------------------
Item                 Qty  Price   Total
----------------------------------------\n`;

      items.forEach(item => {
        const product = getProduct(item.product_id);
        const unit = product ? product.unit : '';
        const qtyDisplay = unit ? `${item.qty} ${unit}` : item.qty.toString();
        const itemDisc = item.discount || 0;
        const lineGross = item.qty * item.price;
        const lineNet = lineGross - itemDisc;
        let name = item.product_name.substring(0, 18).padEnd(20, ' ');
        let q = qtyDisplay.padEnd(8, ' ');
        let p = item.price.toFixed(2).padStart(6, ' ');
        let t = lineGross.toFixed(2).padStart(7, ' ');
        receipt += `${name}${q}${p}${t}\n`;
        if (itemDisc > 0) {
          receipt += `  Item Discount:          -${formatCurrency(itemDisc).padStart(10, ' ')}\n`;
          receipt += `  After Disc:              ${formatCurrency(lineNet).padStart(10, ' ')}\n`;
        }
      });

      receipt += `----------------------------------------\n`;
      receipt += `Subtotal:                  ${formatCurrency(calc.subtotal).padStart(12, ' ')}\n`;
      if (calc.discount > 0) {
        receipt += `Bill Discount:            -${formatCurrency(calc.discount).padStart(12, ' ')}\n`;
      }
      if (calc.isGst) {
        receipt += `GST:                       ${formatCurrency(calc.gst).padStart(12, ' ')}\n`;
      }
      receipt += `GRAND TOTAL:               ${formatCurrency(calc.total).padStart(12, ' ')}\n`;
      receipt += `========================================\n`;
      receipt += `Amount Paid:               ${formatCurrency(paid).padStart(12, ' ')}\n`;
      if (custId) {
        let bal = calc.total - paid;
        receipt += `Bill Balance:              ${formatCurrency(bal).padStart(12, ' ')}\n`;
      }
      receipt += `\n       Thank you for shopping!      \n`;
      receipt += `========================================\n`;

      // Open in new window for printing
      const printWindow = window.open('', '_blank', 'width=450,height=600');
      if (!printWindow) {
        alert('Pop-up blocked! Please allow pop-ups for this site to print the bill.');
        return;
      }
      printWindow.document.write(`
        <html><head><title>Print Receipt - ${invoiceId}</title>
        <style>
          body { font-family: 'Courier New', Courier, monospace; margin: 0; padding: 20px; width: 350px; font-size: 13px; line-height: 1.4; }
          pre { white-space: pre-wrap; margin:0; }
        </style>
        </head><body>
        <pre>${receipt}</pre>
        <script>
          window.onload = function() {
            window.print();
            window.onafterprint = function(){ window.close(); };
          };
        <\/script>
        </body></html>
      `);
      printWindow.document.close();
    }

    function populateCustomerSelect() {
      const sel = document.getElementById('billCustomerSelect');
      sel.innerHTML = '<option value="">-- Walk-in Customer (No Credit) --</option>';
      customers.forEach(c => {
        sel.innerHTML += `<option value="${c.id}">${c.name} (${c.phone})</option>`;
      });
    }


