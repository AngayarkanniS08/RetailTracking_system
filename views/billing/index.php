<!-- BILLING SECTION -->
        <section id="billing_pos" class="view-section">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h2 style="margin: 0; font-size: 1.4rem; font-weight: 700; color: var(--text-strong);">Billing (POS)</h2>
          </div>

          <!-- Low Stock Banner -->
          <div id="lowStockBanner" class="low-stock-banner">
            <div class="low-stock-banner-title">🚨 Low Stock Alert</div>
            <div id="lowStockBannerItems"></div>
          </div>

          <!-- Search -->
          <div class="pos-search-area" style="align-items: center; margin-bottom: 0.8rem; position: relative;">
            <input type="text" id="posSearch" class="input-field" style="flex:1"
              placeholder="Search by Product Name, ID, or Batch..." onkeyup="onPOSSearchKeyup(event)">
            <div id="posSearchDropdown" class="pos-search-dropdown" style="display:none;"></div>
          </div>

          <!-- Data Grid with empty rows -->
          <div class="billing-grid-wrap">
            <table class="billing-grid" id="billingGrid">
              <thead>
                <tr>
                  <th style="width:100px;">Batch No</th>
                  <th style="width:160px;">Particulars</th>
                  <th style="width:80px; text-align:right;">
                    Price <span id="priceModeLabel" class="price-mode-badge">W</span>
                  </th>
                  <th style="width:70px; text-align:right;">Discount</th>
                  <th style="width:60px;">Unit</th>
                  <th style="width:70px; text-align:right;">Qty</th>
                  <th style="width:70px; text-align:right;">GST (%)</th>
                  <th style="width:90px; text-align:right;">Amount</th>
                </tr>
              </thead>
              <tbody>
                <tr data-batch-id=""><td class="cell-active" contenteditable="true"></td><td contenteditable="true"></td><td style="text-align:right" contenteditable="true"></td><td style="text-align:right" contenteditable="true"></td><td contenteditable="true"></td><td style="text-align:right" contenteditable="true"></td><td style="text-align:right" contenteditable="true"></td><td style="text-align:right" contenteditable="true"></td></tr>
                <tr data-batch-id=""><td contenteditable="true"></td><td contenteditable="true"></td><td style="text-align:right" contenteditable="true"></td><td style="text-align:right" contenteditable="true"></td><td contenteditable="true"></td><td style="text-align:right" contenteditable="true"></td><td style="text-align:right" contenteditable="true"></td><td style="text-align:right" contenteditable="true"></td></tr>
                <tr data-batch-id=""><td contenteditable="true"></td><td contenteditable="true"></td><td style="text-align:right" contenteditable="true"></td><td style="text-align:right" contenteditable="true"></td><td contenteditable="true"></td><td style="text-align:right" contenteditable="true"></td><td style="text-align:right" contenteditable="true"></td><td style="text-align:right" contenteditable="true"></td></tr>
                <tr data-batch-id=""><td contenteditable="true"></td><td contenteditable="true"></td><td style="text-align:right" contenteditable="true"></td><td style="text-align:right" contenteditable="true"></td><td contenteditable="true"></td><td style="text-align:right" contenteditable="true"></td><td style="text-align:right" contenteditable="true"></td><td style="text-align:right" contenteditable="true"></td></tr>
                <tr data-batch-id=""><td contenteditable="true"></td><td contenteditable="true"></td><td style="text-align:right" contenteditable="true"></td><td style="text-align:right" contenteditable="true"></td><td contenteditable="true"></td><td style="text-align:right" contenteditable="true"></td><td style="text-align:right" contenteditable="true"></td><td style="text-align:right" contenteditable="true"></td></tr>
                <tr data-batch-id=""><td contenteditable="true"></td><td contenteditable="true"></td><td style="text-align:right" contenteditable="true"></td><td style="text-align:right" contenteditable="true"></td><td contenteditable="true"></td><td style="text-align:right" contenteditable="true"></td><td style="text-align:right" contenteditable="true"></td><td style="text-align:right" contenteditable="true"></td></tr>
                <tr data-batch-id=""><td contenteditable="true"></td><td contenteditable="true"></td><td style="text-align:right" contenteditable="true"></td><td style="text-align:right" contenteditable="true"></td><td contenteditable="true"></td><td style="text-align:right" contenteditable="true"></td><td style="text-align:right" contenteditable="true"></td><td style="text-align:right" contenteditable="true"></td></tr>
                <tr data-batch-id=""><td contenteditable="true"></td><td contenteditable="true"></td><td style="text-align:right" contenteditable="true"></td><td style="text-align:right" contenteditable="true"></td><td contenteditable="true"></td><td style="text-align:right" contenteditable="true"></td><td style="text-align:right" contenteditable="true"></td><td style="text-align:right" contenteditable="true"></td></tr>
              </tbody>
            </table>
          </div>

          <!-- Cart Summary (bottom bar) -->
          <div class="billing-bottom-bar">
            <div class="bbb-left">
              <label class="bbb-gst-toggle">
                <input type="checkbox" id="enableGstToggle" checked onchange="calculateCart()">
                Generate Tax Invoice (Apply GST)
              </label>
              <span class="bbb-sep">|</span>
              <span>Sale: <strong id="cartSubtotal">₹0.00</strong></span>
              <span class="bbb-sep">|</span>
              <span>GST: <strong id="cartGst">₹0.00</strong></span>
              <span class="bbb-sep">|</span>
              <span>Discount (₹): <input type="number" id="cartDiscountInput" class="input-field" placeholder="0" min="0" style="width:80px; padding:3px 6px; text-align:right; font-size:0.85rem;" oninput="calculateCart()"></span>
            </div>
            <div class="bbb-right">
              <span style="font-size:1.2rem; font-weight:700;">Total: <span id="cartTotal">₹0.00</span></span>
              <select id="billCustomerSelect" class="input-field" style="width:auto; min-width:160px;">
                <option value="">-- Walk-in Customer --</option>
              </select>
              <input type="number" id="amountPaidInput" class="input-field" placeholder="Amount (₹)" style="width:110px;">
              <button class="btn btn-primary" onclick="processCheckout()">Checkout & Print</button>
            </div>
          </div>

          <!-- Delete Row Confirmation Modal -->
          <div class="modal-overlay" id="deleteRowModal">
            <div class="modal-content" style="max-width: 400px;">
              <div class="modal-header">
                <div class="modal-title">Delete Line Item</div>
                <button class="close-btn" onclick="closeDeleteConfirm()">&times;</button>
              </div>
              <div style="padding: 1rem 0;">
                <p>Remove <strong id="deleteRowItemName"></strong> from this invoice?</p>
              </div>
              <div style="display:flex; gap:8px; justify-content:flex-end;">
                <button class="btn btn-outline" onclick="closeDeleteConfirm()">Cancel</button>
                <button class="btn btn-danger" onclick="confirmDeleteRow()">Delete</button>
              </div>
            </div>
          </div>
        </section>