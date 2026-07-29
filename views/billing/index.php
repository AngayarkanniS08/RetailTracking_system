<!-- BILLING SECTION -->
        <section id="billing_pos" class="view-section active">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h2 style="margin: 0; font-size: 1.4rem; font-weight: 700; color: var(--text-strong);">Billing (POS)</h2>
          </div>

          <!-- Low Stock Banner -->
          <div id="lowStockBanner" class="low-stock-banner">
            <div class="low-stock-banner-title">🚨 Low Stock Alert</div>
            <div id="lowStockBannerItems"></div>
          </div>

          <!-- Search & Billing Options Bar -->
          <div class="pos-search-area" style="display: flex; align-items: center; gap: 12px; margin-bottom: 0.8rem; position: relative;">
            <div style="flex: 1; position: relative;">
              <input type="text" id="posSearch" class="input-field" style="width: 100%;"
                placeholder="Search by Product Name, ID, or Batch..." onkeyup="onPOSSearchKeyup(event)">
              <div id="posSearchDropdown" class="pos-search-dropdown" style="display:none;"></div>
            </div>

            <!-- Billing Info (Relocated next to Search Field) -->
            <div class="bbb-left" style="width: auto; flex-shrink: 0; display: flex; align-items: center; gap: 12px;">
              <label class="bbb-gst-toggle">
                <input type="checkbox" id="enableGstToggle" checked onchange="calculateCart(true)">
                Generate Tax Invoice (Apply GST)
              </label>
              <span>Sale: <strong id="cartSubtotal">₹0.00</strong></span>
              <span>GST: <strong id="cartGst">₹0.00</strong></span>
            </div>
          </div>

          <!-- Data Grid with empty rows -->
          <div class="billing-grid-wrap">
            <table class="billing-grid" id="billingGrid">
              <thead>
                <tr>
                  <th style="width:100px;">Batch No</th>
                  <th style="width:160px;">Particulars</th>
                  <th style="width:80px; text-align:right;">
                    Price
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
                <!-- 5 Additional Extended Entry Rows -->
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
            <div class="bbb-right">
              <span style="font-size:1.2rem; font-weight:700;">Total: <span id="cartTotal">₹0.00</span></span>
              <span class="bbb-discount-wrap">
                <span class="bbb-label">Discount</span>
                <input type="number" id="cartDiscountInput" class="bbb-input bbb-input-discount" placeholder="0" min="0" oninput="calculateCart()">
              </span>
              <div class="customer-search-combobox">
                <input type="text" id="customerSearchInput" class="input-field" placeholder="Customer name or phone..."
                       autocomplete="off" onkeyup="onCustomerSearchKeyup(event)" onfocus="onCustomerSearchKeyup({key:''})">
                <input type="hidden" id="billCustomerId" value="">
                <div id="customerSearchDropdown" class="customer-search-dropdown"></div>
              </div>
              <button class="btn btn-add-customer" onclick="openModal('addCustomerModal')" title="Add New Customer">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                  <line x1="12" y1="5" x2="12" y2="19"></line>
                  <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                <span>Add Customer</span>
              </button>
              <span class="bbb-paid-wrap">
                <span class="bbb-label">Paying</span>
                <input type="number" id="amountPaidInput" class="bbb-input bbb-input-paid" placeholder="₹ 0">
              </span>
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
              <div class="modal-body">
                <p>Remove <strong id="deleteRowItemName"></strong> from this invoice?</p>
              </div>
              <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeDeleteConfirm()">Cancel</button>
                <button class="btn btn-danger" onclick="confirmDeleteRow()">Delete</button>
              </div>
            </div>
          </div>
        </section>

<script type="module" src="/public/assets/js/pages/billing.js?v=<?= time(); ?>"></script>