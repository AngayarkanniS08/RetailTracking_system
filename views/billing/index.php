<section id="billing_pos" class="view-section active">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
    <h2 style="margin: 0; font-size: 1.4rem; font-weight: 700; color: var(--text-strong);">Billing (POS)</h2>
  </div>

  <div id="lowStockBanner" class="low-stock-banner">
    <div class="low-stock-banner-title">🚨 Low Stock Alert</div>
    <div id="lowStockBannerItems"></div>
  </div>

  <div class="pos-search-area" style="display: flex; align-items: center; gap: 12px; margin-bottom: 0.8rem; position: relative;">
    <div style="flex: 1; position: relative;">
      <input type="text" id="posSearch" class="input-field" style="width: 100%;"
        placeholder="Search by Product Name, ID, or Batch..." onkeydown="onPOSSearchKeydown(event)" onkeyup="onPOSSearchKeyup(event)">
      <div id="posSearchDropdown" class="pos-search-dropdown"></div>
    </div>
    <div class="bbb-left" style="width: auto; flex-shrink: 0; display: flex; align-items: center; gap: 12px;">
      <label class="bbb-gst-toggle">
        <input type="checkbox" id="enableGstToggle" checked onchange="onGstToggle(this.checked)">
        Generate Tax Invoice (Apply GST)
      </label>
      <span>Sale: <strong id="cartSubtotal">₹0.00</strong></span>
      <span>GST: <strong id="cartGst">₹0.00</strong></span>
    </div>
  </div>

  <div class="billing-grid-wrap">
    <table class="billing-grid" id="billingGrid">
      <thead>
        <tr>
          <th style="width:100px;">Batch No</th>
          <th style="width:160px;">Particulars</th>
          <th style="width:80px;text-align:right;" data-field="price">Price</th>
          <th style="width:70px;text-align:right;" data-field="discount">Discount</th>
          <th style="width:60px;" data-field="unit">Unit</th>
          <th style="width:70px;text-align:right;" data-field="qty">Qty</th>
          <th style="width:70px;text-align:right;" data-field="gst">GST (%)</th>
          <th style="width:90px;text-align:right;" data-field="amount">Amount</th>
        </tr>
      </thead>
      <tbody>
        <?php for ($i = 0; $i < 13; $i++): ?>
        <tr data-batch-id="" data-product-id="" data-index="">
          <td data-field="batch" contenteditable="false"></td>
          <td data-field="name" contenteditable="false"></td>
          <td data-field="price" contenteditable="false" style="text-align:right"></td>
          <td data-field="discount" contenteditable="true" style="text-align:right"
              oninput="onCellDiscount(this)" onblur="onCellDiscountBlur(this)"></td>
          <td data-field="unit" contenteditable="false"></td>
          <td data-field="qty" contenteditable="true" style="text-align:right"
              oninput="onCellQty(this)" onblur="onCellQtyBlur(this)"></td>
          <td data-field="gst" contenteditable="false" style="text-align:right"></td>
          <td data-field="amount" contenteditable="false" style="text-align:right"></td>
        </tr>
        <?php endfor; ?>
      </tbody>
    </table>
  </div>

  <div class="billing-bottom-bar">
    <div class="bbb-right">
      <span style="font-size:1.2rem;font-weight:700;">Total: <span id="cartTotal">₹0.00</span></span>
      <span class="bbb-discount-wrap">
        <span class="bbb-label">Discount</span>
        <input type="number" id="cartDiscountInput" class="bbb-input bbb-input-discount" placeholder="0" min="0" oninput="onBillDiscount(this.value)">
      </span>
      <div class="customer-search-combobox">
        <input type="text" id="customerSearchInput" class="input-field" placeholder="Customer name or phone..."
               autocomplete="off" onkeyup="onCustomerSearchKeyup(event)" onfocus="onCustomerSearchFocus()" onclick="onCustomerSearchFocus()">
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
      <span class="bbb-payment-mode">
        <span class="bbb-label">Mode</span>
        <select id="paymentModeSelect" class="bbb-input" onchange="onPaymentMode(this.value)">
          <option value="cash">Cash</option>
          <option value="card">Card</option>
          <option value="upi">UPI</option>
          <option value="credit">Credit</option>
        </select>
      </span>
      <button class="btn btn-primary" onclick="window.checkout()">Checkout & Print</button>
    </div>
  </div>

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
