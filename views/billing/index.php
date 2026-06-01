<!-- 
  FEATURE DOCUMENTATION: Billing (POS)
  - Point of Sale Interface: Main checkout screen with item search and barcode scanning capabilities.
  - Billing Features: Adding items to a billing cart, adjusting custom pricing, checkout validation (amount paid vs. total).
  - Receipt Generation: Receipt printing logic.
-->
        <section id="billing_pos" class="view-section">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.2rem;">
            <h2 style="margin: 0; font-size: 1.4rem; font-weight: 700; color: var(--text-strong);">Billing (POS)</h2>
          </div>
          <div id="lowStockBanner" class="low-stock-banner">
            <div class="low-stock-banner-title">🚨 Low Stock Alert</div>
            <div id="lowStockBannerItems"></div>
          </div>
          <div class="pos-grid">
            <!-- Left: Products -->
            <div class="products-panel">
              <div class="pos-search-area" style="align-items: center;">
                <button class="layout-toggle-btn" id="sidebarToggle" onclick="toggleSidebar()"
                  title="Toggle Sidebar (Full Width View)">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="9" y1="3" x2="9" y2="21"></line>
                  </svg>
                </button>
                <input type="text" id="posSearch" class="input-field" style="flex:1"
                  placeholder="Search by Product Name, ID, or Batch..." onkeyup="renderPOSItems()">
                <button class="layout-toggle-btn" id="cartToggle" onclick="toggleCart()" title="Collapse/Expand Cart">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    id="cartToggleIcon">
                    <polyline points="13 17 18 12 13 7"></polyline>
                    <polyline points="6 17 11 12 6 7"></polyline>
                  </svg>
                </button>
              </div>
              <div class="cat-filters" id="posCatFilters">
                <!-- Category filter buttons rendered by JS -->
              </div>
              <div class="item-grid" id="posItemsGrid">
                <!-- Items rendered via JS -->
              </div>
            </div>

            <!-- Right: Cart -->
            <div class="cart-panel">
              <div class="cart-items" id="cartItemsContainer">
                <!-- Cart items rendered via JS -->
                <div class="text-center" style="color:var(--muted); margin-top: 50px;">Cart is empty. Select items to
                  bill.</div>
              </div>
              <div class="cart-summary">
                <div
                  style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 12px; border-bottom: 1px dotted var(--border); padding-bottom: 12px;">
                  <label
                    style="font-size: 0.85rem; font-weight: 500; color: var(--text-strong); display:flex; align-items:center; gap: 8px; cursor:pointer;"
                    title="Toggle whether this is a Pakka Bill (Tax Invoice) or Kachha Bill (Estimate)">
                    <input type="checkbox" id="enableGstToggle" checked onchange="calculateCart()">
                    Generate Tax Invoice (Apply GST)
                  </label>
                </div>
                <div class="summary-row">
                  <span>Subtotal</span>
                  <span id="cartSubtotal">₹0.00</span>
                </div>
                <div class="summary-row" style="align-items:center;">
                  <span>Discount (₹)</span>
                  <input type="number" id="cartDiscountInput" class="input-field" placeholder="0" min="0"
                    style="width:100px; padding:4px 8px; text-align:right; font-size:0.85rem;"
                    oninput="calculateCart()">
                </div>
                <div class="summary-row">
                  <span>GST Amount</span>
                  <span id="cartGst">₹0.00</span>
                </div>
                <div class="summary-total">
                  <span>Total</span>
                  <span id="cartTotal">₹0.00</span>
                </div>

                <div class="mt-1">
                  <select id="billCustomerSelect" class="input-field" style="margin-bottom: 10px;">
                    <option value="">-- Walk-in Customer (No Credit) --</option>
                  </select>
                  <div class="d-flex">
                    <input type="number" id="amountPaidInput" class="input-field" placeholder="Amount Paid (₹)"
                      style="flex: 1;">
                    <button class="btn btn-primary" onclick="processCheckout()" style="flex: 1;">Checkout &
                      Print</button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>
