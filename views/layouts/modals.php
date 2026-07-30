  <!-- MODALS -->

  <!-- Active Low Stock Alerts Modal -->
  <div class="modal-overlay" id="activeAlertsModal">
    <div class="modal-content" style="max-width: 520px; width: 95%; padding: 24px; border-radius: 16px; display: flex; flex-direction: column; gap: 20px;">
      <div class="modal-header" style="padding-bottom: 12px; border-bottom: 1px solid var(--border, #eaecf0); margin: 0;">
        <div class="modal-title" style="font-size: 18px; font-weight: 600; color: var(--text-strong, #101828);">⚠️ Active Low Stock Alerts</div>
        <button class="close-btn" onclick="closeModal('activeAlertsModal')">&times;</button>
      </div>
      <div id="activeAlertsModalList" style="display: flex; flex-direction: column; gap: 12px; margin: 0;">
        <!-- Loaded dynamically -->
      </div>
      <div class="modal-footer" style="margin-top: 8px;">
        <button class="btn btn-outline btn-block" onclick="closeModal('activeAlertsModal')">Close</button>
      </div>
    </div>
  </div>

  <!-- Low Stock Alert Modal -->
  <div class="modal-overlay" id="lowStockAlertModal">
    <div class="modal-content" style="max-width: 520px; width: 95%; padding: 24px; border-radius: 16px; display: flex; flex-direction: column; gap: 20px;">
      <div class="modal-header" style="padding-bottom: 12px; border-bottom: 1px solid var(--border, #eaecf0); margin: 0;">
        <div class="modal-title" style="font-size: 18px; font-weight: 600; color: var(--text-strong, #101828);">🔔 Set Low Stock Alert</div>
        <button class="close-btn" data-modal-close>&times;</button>
      </div>
      
      <div class="modal-body" style="display: flex; flex-direction: column; gap: 16px; padding: 0;">
        <div class="input-group" style="display: flex; flex-direction: column; gap: 6px;">
          <label class="input-label" style="font-size: 14px; font-weight: 500; color: var(--text-primary, #344054); margin: 0;">Select Product</label>
          <select id="alertProductSelect" class="input-field" style="height: 40px; border-radius: 8px; padding: 10px 14px; font-size: 14px; border: 1px solid var(--border, #d0d5dd); box-shadow: 0px 1px 2px rgba(16, 24, 40, 0.05); width: 100%; box-sizing: border-box;"></select>
        </div>
        
        <div style="display: flex; flex-direction: row; gap: 16px; align-items: flex-end;">
          <div class="input-group" style="flex: 1; display: flex; flex-direction: column; gap: 6px;">
            <label class="input-label" style="font-size: 14px; font-weight: 500; color: var(--text-primary, #344054); margin: 0;">Lead Time (days)</label>
            <input type="number" id="alertLeadTime" class="input-field" placeholder="e.g. 5" min="0" data-recalc style="height: 40px; border-radius: 8px; padding: 10px 14px; font-size: 14px; border: 1px solid var(--border, #d0d5dd); box-shadow: 0px 1px 2px rgba(16, 24, 40, 0.05); width: 100%; box-sizing: border-box;">
          </div>
          <div class="input-group" style="flex: 1; display: flex; flex-direction: column; gap: 6px;">
            <label class="input-label" style="font-size: 14px; font-weight: 500; color: var(--text-primary, #344054); margin: 0;">Daily Sale Qty</label>
            <input type="number" id="alertDailySale" class="input-field" placeholder="e.g. 3" min="0" data-recalc style="height: 40px; border-radius: 8px; padding: 10px 14px; font-size: 14px; border: 1px solid var(--border, #d0d5dd); box-shadow: 0px 1px 2px rgba(16, 24, 40, 0.05); width: 100%; box-sizing: border-box;">
          </div>
        </div>

        <div style="display: flex; flex-direction: row; gap: 16px; align-items: flex-end;">
          <div class="input-group" style="flex: 1; display: flex; flex-direction: column; gap: 6px;">
            <label class="input-label" style="font-size: 14px; font-weight: 500; color: var(--text-primary, #344054); margin: 0;">Emergency Stock</label>
            <input type="number" id="alertEmergencyStock" class="input-field" placeholder="e.g. 10" min="0" data-recalc style="height: 40px; border-radius: 8px; padding: 10px 14px; font-size: 14px; border: 1px solid var(--border, #d0d5dd); box-shadow: 0px 1px 2px rgba(16, 24, 40, 0.05); width: 100%; box-sizing: border-box;">
          </div>
          <div class="input-group" style="flex: 1; display: flex; flex-direction: column; gap: 6px;">
            <label class="input-label" style="font-size: 14px; font-weight: 500; color: var(--text-primary, #344054); margin: 0;">Reorder Point (Qty)</label>
            <input type="number" id="alertThreshold" class="input-field" placeholder="Calculated value" min="1" style="height: 40px; border-radius: 8px; padding: 10px 14px; font-size: 14px; border: 1px solid var(--border, #d0d5dd); box-shadow: 0px 1px 2px rgba(16, 24, 40, 0.05); width: 100%; box-sizing: border-box;">
          </div>
        </div>

        <div id="existingAlertsList" style="margin-top: 4px;"></div>
      </div>

      <div class="modal-footer" style="margin-top: 8px;">
        <button class="btn btn-primary btn-block" data-save-alert style="height: 40px; font-weight: 600; border-radius: 8px;">Set Alert</button>
      </div>
    </div>
  </div>


  <!-- Add Stock Modal -->
  <div class="modal-overlay" id="addStockModal">
    <div class="modal-content" style="max-width: 720px; width: 95%; max-height: 90vh; overflow: hidden; display: flex; flex-direction: column; padding: 0;">
      <div class="modal-header" style="padding: 1.5rem 2rem; border-bottom: 1px solid var(--border); background: var(--card);">
        <div class="modal-title" id="addStockModalTitle" style="font-size: 20px;">Add New Stock Batch</div>
        <button class="close-btn" data-modal-close style="width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: var(--bg-100); border: none; font-size: 1.25rem; color: var(--muted); cursor: pointer; line-height: 1;">&times;</button>
      </div>

      <div style="padding: 24px; overflow-y: auto; flex: 1; display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        <div class="input-group" style="grid-column: span 2;">
          <label class="input-label" style="font-weight: 500;">Select Product</label>
          <div class="combobox" id="stockProductCombobox" style="width: 100%; position: relative;">
            <input type="text" id="stockProductInput" class="input-field" placeholder="Click or type to select product..." autocomplete="off" style="padding-right: 32px;">
            <span style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); pointer-events: none; color: var(--muted); font-size: 0.75rem;">▼</span>
            <input type="hidden" id="stockProduct" value="">
            <div id="stockProductDropdown" class="combobox-dropdown"></div>
          </div>
        </div>

        <div class="segment-control" style="grid-column: span 2; display: flex; background: #f1f5f9; border-radius: 10px; padding: 4px;">
          <div class="segment-item active" id="segWholesale" data-segment="wholesale" style="flex: 1; padding: 10px 16px; text-align: center; border-radius: 8px; cursor: pointer; font-weight: 500; font-size: 14px; transition: all 0.15s; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.1); color: var(--text-strong);">📦 Wholesale Mode</div>
          <div class="segment-item" id="segRetail" data-segment="retail" style="flex: 1; padding: 10px 16px; text-align: center; border-radius: 8px; cursor: pointer; font-weight: 500; font-size: 14px; transition: all 0.15s; background: transparent; color: var(--muted);">✂️ Retail Mode</div>
        </div>

        <!-- Wholesale Pricing Section -->
        <div id="wholesalePricing" style="grid-column: span 2; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;">
          <div class="grid-header" style="font-size: 0.8rem; font-weight: 700; color: var(--accent); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 16px;">📦 Wholesale Pricing</div>
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
            <div class="input-group">
              <label class="input-label" style="font-weight: 500;">Base Purchase Price (₹)</label>
              <input type="number" id="stockPP" class="input-field" placeholder="0.00" data-calc-trigger="profit" data-calc-section="wholesale" style="padding: 12px; border-radius: 8px;">
            </div>
            <div class="input-group">
              <label class="input-label" style="font-weight: 500;">Wholesale Profit (₹)</label>
              <input type="number" id="stockProfit" class="input-field" placeholder="0.00" data-calc-trigger="sp" data-calc-section="wholesale" style="padding: 12px; border-radius: 8px;">
            </div>
            <div class="input-group" style="grid-column: span 2;">
              <label class="input-label" style="font-weight: 500;">Wholesale Selling Price (₹)</label>
              <input type="number" id="stockSP" class="input-field" placeholder="0.00" data-calc-trigger="profit" data-calc-section="wholesale" style="padding: 12px; border-radius: 8px;">
            </div>
          </div>
          <div id="invGstDisplay" style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #e2e8f0; font-size: 0.8rem; color: var(--muted);">
            <span id="invGstRateText">GST: ₹0.00</span> &middot;
            <strong id="invTotalText" style="color:var(--ok)">Total: ₹0.00</strong>
          </div>
        </div>

        <!-- Retail Pricing Section (hidden by default) -->
        <div id="retailPricing" style="grid-column: span 2; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; display: none;">
          <div class="grid-header" style="font-size: 0.8rem; font-weight: 700; color: var(--warn); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 16px;">✂️ Retail Pricing</div>
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
            <div class="input-group">
              <label class="input-label" style="font-weight: 500;">Unit Cost (₹)</label>
              <input type="number" id="retailBasePrice" class="input-field" placeholder="0.00" data-calc-trigger="profit" data-calc-section="retail" style="padding: 12px; border-radius: 8px;">
            </div>
            <div class="input-group">
              <label class="input-label" style="font-weight: 500;">Retail Profit / Unit (₹)</label>
              <input type="number" id="retailProfit" class="input-field" placeholder="0.00" data-calc-trigger="sp" data-calc-section="retail" style="padding: 12px; border-radius: 8px;">
            </div>
            <div class="input-group" style="grid-column: span 2;">
              <label class="input-label" style="font-weight: 500;">Retail Selling Price / Unit (₹)</label>
              <input type="number" id="retailSP" class="input-field" placeholder="0.00" data-calc-trigger="profit" data-calc-section="retail" style="padding: 12px; border-radius: 8px;">
            </div>
          </div>
          <div id="retailGstDisplay" style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #e2e8f0; font-size: 0.8rem; color: var(--muted);">
            <span id="retailGstRateText">GST: ₹0.00</span> &middot;
            <strong id="retailTotalText" style="color:var(--warn)">Total: ₹0.00</strong>
          </div>
        </div>

        <div class="input-group">
          <label class="input-label" style="font-weight: 500;">Vendor Name</label>
          <input type="text" id="stockVendor" class="input-field" placeholder="e.g. Metro Wholesale" style="padding: 12px; border-radius: 8px;">
        </div>
        <div class="input-group">
          <label class="input-label" style="font-weight: 500;">Batch ID/Number</label>
          <input type="text" id="stockBatchId" class="input-field" placeholder="e.g. BATCH-001" style="padding: 12px; border-radius: 8px;">
        </div>
        <div class="input-group">
          <label class="input-label" style="font-weight: 500;">Stock Quantity (Units)</label>
          <input type="number" id="stockQty" class="input-field" placeholder="0" style="padding: 12px; border-radius: 8px;">
        </div>
        <div class="input-group">
          <label class="input-label" style="font-weight: 500;">Date</label>
          <input type="date" id="stockDate" class="input-field" style="padding: 12px; border-radius: 8px;">
        </div>
      </div>

      <div style="padding: 16px 24px; border-top: 1px solid var(--border); background: var(--card); display: flex; gap: 12px; justify-content: flex-end;">
        <button class="btn btn-outline" data-modal-close style="padding: 10px 24px; border-radius: 8px;">Cancel</button>
        <button class="btn btn-primary" data-save-stock style="padding: 10px 24px; border-radius: 8px;">Save Batch Entry</button>
      </div>
    </div>
  </div>

<!-- Generic Alert Dialog -->
<div id="alertDialogModal" class="modal-overlay">
    <div class="modal-content" style="max-width: 420px;">
        <div class="modal-header">
            <div class="modal-title" id="alertDialogTitle">Alert</div>
            <button class="close-btn" onclick="closeModal('alertDialogModal')">&times;</button>
        </div>
        <div class="modal-body">
            <p id="alertDialogMessage"></p>
        </div>
        <div class="modal-footer" style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 1.5rem;">
            <button class="btn btn-primary" onclick="closeModal('alertDialogModal')">OK</button>
        </div>
    </div>
</div>

  <!-- Add Customer Modal -->
  <div class="modal-overlay" id="addCustomerModal">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-title">Add New Customer</div>
        <button class="close-btn" onclick="closeModal('addCustomerModal')">&times;</button>
      </div>
      <div class="input-group">
        <label class="input-label">Full Name <span style="color:var(--danger);">*</span></label>
        <input type="text" id="custName" class="input-field" placeholder="Customer Name">
      </div>
      <div class="input-group">
        <label class="input-label">Phone Number <span style="color:var(--danger);">*</span></label>
        <input type="text" id="custPhone" class="input-field" placeholder="10-digit number">
      </div>

      <div class="d-flex" style="display: flex; flex-direction: row; align-items: flex-start; gap: 20px; width: 100%;">
        <div class="input-group" style="flex: 1; margin-bottom: 0;">
          <label class="input-label">Credit Limit (₹) <span style="display: block; margin-top: 2px; color:var(--muted); font-weight:400;">(optional)</span></label>
          <input type="number" id="custCreditLimit" class="input-field" placeholder="0" min="0">
        </div>
        <div class="input-group" style="flex: 1; margin-bottom: 0;">
          <label class="input-label">Opening Balance (₹) <span style="display: block; margin-top: 2px; color:var(--muted); font-weight:400;">(optional)</span></label>
          <input type="number" id="custOpeningBalance" class="input-field" placeholder="0" min="0">
        </div>
      </div>
      <button class="btn btn-primary btn-block" onclick="saveCustomer()">Save Customer</button>
    </div>
  </div>

  <!-- Receive Payment Modal -->
  <div class="modal-overlay" id="paymentModal">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-title">Receive Payment</div>
        <button class="close-btn" onclick="closeModal('paymentModal')">&times;</button>
      </div>
      <div
        style="margin-bottom: 1.5rem; padding: 1rem; background: var(--bg-elevated); border-radius: var(--radius-md); border: 1px solid var(--border);">
        <div style="color: var(--muted); font-size: 0.85rem;">Customer</div>
        <div id="payCustName" style="font-weight: 600; color: var(--text-strong); font-size: 1.1rem;">-</div>
        <div style="display:flex; justify-content: space-between; margin-top: 10px;">
          <span style="color: var(--warn);">Outstanding: <strong id="payOutstanding">₹0</strong></span>
        </div>
      </div>
      <input type="hidden" id="payCustId">
      <div class="input-group">
        <label class="input-label">Amount Received (₹)</label>
        <input type="number" id="payAmount" class="input-field" placeholder="Enter amount" min="0" step="1" oninput="onPayAmountInput()">
        <div id="payLimitFeedback" style="font-size:0.78rem; margin-top:5px; min-height:16px;"></div>
      </div>
      <div class="input-group">
        <label class="input-label">Notes (optional)</label>
        <input type="text" id="payNotes" class="input-field" placeholder="e.g. Partial payment">
      </div>
      <button class="btn btn-primary btn-block" onclick="processPayment()">Record Payment</button>
    </div>
  </div>

  <!-- Customer Bills Modal -->
  <div class="modal-overlay" id="customerBillsModal">
    <div class="modal-content" style="max-width: 780px; width: 92%;">
      <div class="modal-header">
        <div>
          <div class="modal-title" id="custBillsModalTitle">Customer Billing History</div>
          <div id="custBillsSubtitle" style="font-size: 0.85rem; color: var(--muted); margin-top: 2px;">View all billing invoices and payment transactions</div>
        </div>
        <button class="close-btn" onclick="closeModal('customerBillsModal')">&times;</button>
      </div>
      <div id="custBillsModalBody" style="max-height: 420px; overflow-y: auto; margin-top: 1rem;">
        <div style="padding: 2rem; text-align: center; color: var(--muted);">Loading billing history...</div>
      </div>
      <div class="modal-footer" style="display: flex; justify-content: flex-end; margin-top: 1.5rem; border-top: 1px solid var(--border); padding-top: 1rem;">
        <button class="btn btn-outline" onclick="closeModal('customerBillsModal')">Close</button>
      </div>
    </div>
  </div>

  <!-- Credit Return Modal -->
  <div class="modal-overlay" id="creditReturnModal">
    <div class="modal-content" style="max-width: 480px;">
      <div class="modal-header">
        <div>
          <div class="modal-title">Record Credit Return</div>
          <div style="font-size: 0.8rem; color: var(--muted); margin-top: 2px;">Reduce customer outstanding for returned goods</div>
        </div>
        <button class="close-btn" onclick="closeModal('creditReturnModal')">&times;</button>
      </div>

      <!-- Customer Info Banner -->
      <div style="margin: 1rem 0; padding: 0.9rem 1rem; background: rgba(239,68,68,0.07); border-radius: var(--radius-md); border: 1px solid rgba(239,68,68,0.25);">
        <div style="font-size: 0.78rem; color: var(--muted); margin-bottom: 2px;">Customer</div>
        <div id="returnCustName" style="font-weight: 700; color: var(--text-strong); font-size: 1.05rem;">-</div>
        <div style="margin-top: 8px; font-size: 0.82rem;">
          Current Outstanding: <strong id="returnCustBalance" style="color: var(--danger, #ef4444);">-</strong>
        </div>
      </div>

      <input type="hidden" id="returnCustId">

      <div class="input-group">
        <label class="input-label">Select Invoice / Bill Reference <span style="color:var(--danger);">*</span></label>
        <select id="returnInvoiceSelect" class="input-field" onchange="window.onReturnInvoiceSelectChange(this)">
          <option value="">Loading customer invoices...</option>
        </select>
        <div id="manualInvoiceGroup" style="display:none; margin-top:8px;">
          <input type="text" id="returnInvoiceManual" class="input-field" placeholder="Enter manual invoice number or reference">
        </div>
        <input type="hidden" id="returnInvoiceRef">
        <div id="returnInvoiceInfo" style="font-size: 0.75rem; color: var(--muted); margin-top: 5px; min-height: 16px;"></div>
      </div>

      <div class="input-group">
        <label class="input-label">Return Amount (&#8377;) <span style="color:var(--danger);">*</span></label>
        <input type="number" id="returnAmount" class="input-field" placeholder="0.00" min="0" step="0.01">
      </div>

      <div class="input-group">
        <label class="input-label">Reason / Notes (optional)</label>
        <input type="text" id="returnNotes" class="input-field" placeholder="e.g. Damaged goods, wrong item">
      </div>

      <div style="display: flex; gap: 10px; margin-top: 0.5rem;">
        <button class="btn btn-outline btn-block" onclick="closeModal('creditReturnModal')">Cancel</button>
        <button id="processReturnBtn" class="btn btn-block" onclick="processReturn()"
                style="background: var(--danger, #ef4444); color: #fff; border: none; font-weight: 600;">
          Process Return
        </button>
      </div>
    </div>
  </div>

  <!-- Add Stock Entry Modal -->

  <div class="modal-overlay" id="addStockEntryModal">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-title">Add Stock Purchase Entry</div>
        <button class="close-btn" onclick="closeModal('addStockEntryModal')">&times;</button>
      </div>
      <div class="input-group">
        <label class="input-label">Stock Name</label>
        <select id="slStockName" class="input-field">
          <!-- Populated from products -->
        </select>
      </div>
      <div class="input-group">
        <label class="input-label">Vendor Name</label>
        <input type="text" id="slVendorName" class="input-field" placeholder="e.g. Erode Textile Market">
      </div>
      <div class="input-group">
        <label class="input-label">Contact Number</label>
        <input type="text" id="slVendorPhone" class="input-field" placeholder="e.g. 9876543210">
      </div>
      <div class="d-flex">
        <div class="input-group" style="flex:1;">
          <label class="input-label" id="slQtyLabel">Quantity</label>
          <input type="number" id="slQty" class="input-field" placeholder="0" min="0">
        </div>
        <div class="input-group" style="flex:1;">
          <label class="input-label">Base Amount (₹)</label>
          <input type="number" id="slAmount" class="input-field" placeholder="0.00" min="0" oninput="calculatePurchaseTotal()">
        </div>
      </div>
      <div class="input-group">
        <label>GST Rate (%)</label>
        <input type="number" id="purchaseGstRate" class="input-field" step="0.01" value="0" oninput="calculatePurchaseTotal()">
    </div>
      <div class="d-flex">
        <div class="input-group" style="flex:1;">
          <label class="input-label">Amount Paid (₹)</label>
          <input type="number" id="slPaid" class="input-field" placeholder="0.00" min="0">
        </div>
        <div class="input-group" style="flex:1;">
          <label class="input-label">Purchase Date</label>
          <input type="date" id="slPurchaseDate" class="input-field">
        </div>
      </div>
      <button class="btn btn-primary btn-block" id="savePurchaseBtn" onclick="saveStockEntry()">Save Entry</button>
    </div>
  </div>

  <!-- Vendor Payment Modal -->
  <div class="modal-overlay" id="vendorPaymentModal">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-title">Pay Vendor</div>
        <button class="close-btn" onclick="closeModal('vendorPaymentModal')">&times;</button>
      </div>
      <input type="hidden" id="vpPurchaseId">
      <input type="hidden" id="vpVendorId">
      <div class="input-group">
        <label class="input-label">Vendor Name</label>
        <input type="text" id="vpVendorName" class="input-field" readonly>
      </div>
      <div class="input-group">
        <label class="input-label">Amount Paying now</label>
        <input type="number" id="slAmountPaying" class="input-field" placeholder="e.g. 3000" min="0">
      </div>
      <div id="slBalancedisplay"
        style="margin-top:-10px; margin-bottom:15px; font-size: 0.8rem; color: var(--muted); display:flex; justify-content:space-between; padding: 0 5px;">
        <span id="slBalanceText">Balance After Payment: ₹0.00</span>
      </div>
      <div class="input-group" style="flex:1;">
        <label class="input-label">Payment Date</label>
        <input type="date" id="vpPaymentDate" class="input-field">
      </div>
      <button class="btn btn-primary btn-block" onclick="submitVendorPayment()">Record Payment</button>
    </div>
  </div>

  <!-- Quick Purchase Modal -->
  <div class="modal-overlay" id="quickPurchaseModal">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-title">Quick Purchase — Existing Vendor</div>
        <button class="close-btn" onclick="closeModal('quickPurchaseModal')">&times;</button>
      </div>
      <div class="input-group">
        <label class="input-label">Vendor Name</label>
        <input type="text" id="qpVendorName" class="input-field" readonly>
      </div>
      <div class="input-group">
        <label class="input-label">Contact Number</label>
        <input type="text" id="qpVendorPhone" class="input-field" readonly>
      </div>
      <div class="input-group">
        <label class="input-label">Product</label>
        <select id="qpStockName" class="input-field">
          <option value="">-- Select Product --</option>
        </select>
      </div>
      <div class="d-flex">
        <div class="input-group" style="flex:1;">
          <label class="input-label">Quantity</label>
          <input type="number" id="qpQty" class="input-field" placeholder="0" min="0">
        </div>
        <div class="input-group" style="flex:1;">
          <label class="input-label">Unit Price (₹)</label>
          <input type="number" id="qpUnitPrice" class="input-field" placeholder="0.00" min="0" oninput="calculateQpTotal()">
        </div>
      </div>
      <div class="d-flex">
        <div class="input-group" style="flex:1;">
          <label class="input-label">Base Amount (₹)</label>
          <input type="number" id="qpBaseAmount" class="input-field" placeholder="0.00" min="0" readonly>
        </div>
        <div class="input-group" style="flex:1;">
          <label class="input-label">GST (%)</label>
          <input type="number" id="qpGstRate" class="input-field" step="0.01" value="0" oninput="calculateQpTotal()">
        </div>
      </div>
      <div class="d-flex">
        <div class="input-group" style="flex:1;">
          <label class="input-label">Amount Paid (₹)</label>
          <input type="number" id="qpPaid" class="input-field" placeholder="0.00" min="0">
        </div>
        <div class="input-group" style="flex:1;">
          <label class="input-label">Purchase Date</label>
          <input type="date" id="qpPurchaseDate" class="input-field">
        </div>
      </div>
      <button class="btn btn-primary btn-block" id="saveQpBtn" onclick="saveQuickPurchase()">Save Purchase</button>
    </div>
  </div>

  <!-- Add Product Modal -->
  <div class="modal-overlay" id="addProductModal">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-title" id="addProductModalTitle">Add New Product</div>
        <button class="close-btn" onclick="resetProductModal(); closeModal('addProductModal')">&times;</button>
      </div>
      <div class="input-group">
        <label class="input-label">Product Name</label>
        <input type="text" id="pmProductName" class="input-field" placeholder="e.g. Silk Fabric">
      </div>
      <div class="d-flex">
        <div class="input-group" style="flex:1;">
          <label class="input-label">HSN Code</label>
          <input type="text" id="pmProductHsn" class="input-field" placeholder="e.g. 5208">
          <div id="pmHsnError" class="form-error" style="display:none; font-size:0.75rem; color:var(--danger); margin-top:4px;">HSN must be 4, 6 or 8 digits</div>
        </div>
        <div class="input-group" style="flex:1;">
          <label class="input-label">GST (%)</label>
          <input type="number" id="pmProductGst" class="input-field" placeholder="0" min="0" max="100">
        </div>
      </div>
      <div class="d-flex" style="gap:10px;">
        <div class="input-group" style="flex:1;">
          <label class="input-label">Category</label>
          <div class="combobox" id="categoryCombobox">
            <input type="text" id="pmProductCategoryInput" class="input-field" 
                  placeholder="Type to search category..." autocomplete="off">
            <input type="hidden" id="pmProductCategoryId" value="">
            <div id="categoryDropdown" class="combobox-dropdown"></div>
          </div>
        </div>
        <div class="input-group" style="flex:1;">
          <label class="input-label">Subcategory</label>
          <div class="combobox" id="subcategoryCombobox">
              <input type="text" id="pmProductSubcategoryInput" class="input-field" 
                    placeholder="Type to search subcategory..." autocomplete="off">
              <input type="hidden" id="pmProductSubcategoryId" value="">
              <div id="subcategoryDropdown" class="combobox-dropdown"></div>
          </div>
        </div>
      </div>
      <div class="input-group">
        <label class="input-label">Unit</label>
        <select id="pmProductUnit" class="input-field">
          <option value="mtr">Meter (mtr)</option>
          <option value="pcs">Pieces (pcs)</option>
          <option value="kg">Kilogram (kg)</option>
          <option value="bundle">Bundle</option>
          <option value="pkt">Packet (pkt)</option>
          <option value="roll">Roll</option>
          <option value="box">Box</option>
          <option value="set">Set</option>
          <option value="pair">Pair</option>
          <option value="spool">Spool</option>
        </select>
      </div>
      <button class="btn btn-primary btn-block" id="addProductModalBtn">Save Product</button>
    </div>
  </div>

  <!-- Add Category / Subcategory Modal -->
  <div class="modal-overlay" id="addCategoryModal">
    <div class="modal-content" style="max-width: 520px; width: 95%; padding: 28px; border-radius: 16px; display: flex; flex-direction: column; gap: 24px; background: var(--card, #ffffff); border: 1px solid var(--border, #e2e8f0); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);">
      
      <!-- Header -->
      <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border, #f1f5f9); padding-bottom: 14px; margin: 0;">
        <div class="modal-title" style="font-size: 1.15rem; font-weight: 700; color: var(--text-strong, #0f172a);">Manage Categories & Subcategories</div>
        <button class="close-btn" onclick="closeModal('addCategoryModal')" style="background: transparent; border: none; font-size: 1.25rem; cursor: pointer; color: var(--muted, #64748b); line-height: 1;">&times;</button>
      </div>
      
      <div style="display: flex; flex-direction: column; gap: 20px; width: 100%; align-items: stretch;">
        
        <!-- Section 1: Add Category -->
        <div style="background: var(--bg-hover, #f8fafc); border: 1px solid var(--border, #e2e8f0); border-radius: 12px; padding: 20px; display: flex; flex-direction: column; gap: 14px; width: 100%; box-sizing: border-box;">
          <div style="font-size: 0.95rem; font-weight: 700; color: var(--text-strong, #0f172a); display: flex; align-items: center; gap: 8px;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--primary, #2563eb);">
              <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
            </svg>
            Add Category
          </div>
          
          <div class="input-group" style="display: flex; flex-direction: column; gap: 6px; width: 100%;">
            <label class="input-label" style="font-size: 14px; font-weight: 500; color: var(--text-primary, #344054); margin: 0;">Category Name</label>
            <input type="text" id="pmCategoryName" class="input-field" placeholder="e.g. Lace Work" style="height: 42px; border-radius: 8px; padding: 10px 14px; font-size: 14px; border: 1px solid var(--border, #cbd5e1); background: var(--card, #ffffff); box-shadow: 0px 1px 2px rgba(16, 24, 40, 0.05); width: 100%; box-sizing: border-box;">
          </div>
          
          <div style="display: flex; justify-content: flex-end; margin-top: 4px; width: 100%;">
            <button class="btn btn-primary" onclick="saveCategory()" style="padding: 10px 20px; font-size: 0.875rem; font-weight: 600; border-radius: 8px;">Save Category</button>
          </div>
        </div>

        <!-- Section 2: Add Subcategory -->
        <div style="background: var(--bg-hover, #f8fafc); border: 1px solid var(--border, #e2e8f0); border-radius: 12px; padding: 20px; display: flex; flex-direction: column; gap: 14px; width: 100%; box-sizing: border-box;">
          <div style="font-size: 0.95rem; font-weight: 700; color: var(--text-strong, #0f172a); display: flex; align-items: center; gap: 8px;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--primary, #2563eb);">
              <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
              <line x1="7" y1="7" x2="7.01" y2="7"></line>
            </svg>
            Add Subcategory
          </div>
          
          <div class="input-group" style="display: flex; flex-direction: column; gap: 6px; width: 100%;">
            <label class="input-label" style="font-size: 14px; font-weight: 500; color: var(--text-primary, #344054); margin: 0;">Select Parent Category</label>
            <select id="pmSubCatParent" class="input-field" style="height: 42px; border-radius: 8px; padding: 10px 14px; font-size: 14px; border: 1px solid var(--border, #cbd5e1); background: var(--card, #ffffff); box-shadow: 0px 1px 2px rgba(16, 24, 40, 0.05); width: 100%; box-sizing: border-box;"></select>
          </div>

          <div class="input-group" style="display: flex; flex-direction: column; gap: 6px; width: 100%;">
            <label class="input-label" style="font-size: 14px; font-weight: 500; color: var(--text-primary, #344054); margin: 0;">Subcategory Name</label>
            <input type="text" id="pmSubCategoryName" class="input-field" placeholder="e.g. Fancy Lace" style="height: 42px; border-radius: 8px; padding: 10px 14px; font-size: 14px; border: 1px solid var(--border, #cbd5e1); background: var(--card, #ffffff); box-shadow: 0px 1px 2px rgba(16, 24, 40, 0.05); width: 100%; box-sizing: border-box;">
          </div>
          
          <div style="display: flex; justify-content: flex-end; margin-top: 4px; width: 100%;">
            <button class="btn btn-primary" onclick="saveSubcategory()" style="padding: 10px 20px; font-size: 0.875rem; font-weight: 600; border-radius: 8px;">Save Subcategory</button>
          </div>
        </div>

      </div>
    </div>
  </div>

  <!-- Bill Receipt Modal -->
  <div class="modal-overlay" id="billReceiptModal">
    <div class="modal-content" style="max-width: 600px; max-height: 90vh; overflow-y: auto;">
      <div class="modal-header">
        <div class="modal-title">Bill Receipt</div>
        <button class="close-btn" onclick="closeModal('billReceiptModal')">&times;</button>
      </div>
      <div id="billReceiptContent"
        style="font-family: monospace; background: #fff; color: #000; padding: 1.5rem; border-radius: 8px; font-size: 13px; line-height: 1.6; white-space: pre-wrap;">
        <!-- Bill content rendered here -->
      </div>
      <button class="btn btn-primary btn-block" style="margin-top: 1rem;" onclick="printBillReceipt()">Print</button>
    </div>
  </div>

  <!-- Sales Summary Detail Modal -->
  <div class="modal-overlay" id="salesSummaryDetailModal">
    <div class="modal-content" style="max-width: 800px; max-height: 90vh; overflow-y: auto;">
      <div class="modal-header">
        <div class="modal-title" id="salesSummaryDetailTitle">Sales Summary Details</div>
        <button class="close-btn" onclick="closeModal('salesSummaryDetailModal')">&times;</button>
      </div>
      <div class="table-container">
        <table id="salesSummaryDetailTable">
          <thead>
            <tr>
              <th>Bill ID</th>
              <th>Date</th>
              <th>Customer</th>
              <th>Amount</th>
            </tr>
          </thead>
          <tbody>
            <!-- Summary details rendered here -->
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <!-- Checkout Confirmation Modal - Two Panel Full Page -->
  <div class="modal-overlay" id="checkoutConfirmModal" style="padding:0; align-items:stretch; justify-content:flex-start; z-index:2000;">
    <div style="margin-left:250px; width:calc(100% - 250px); height:100vh; display:flex; flex-direction:column; background:#0e1015; overflow:hidden;">
      <!-- Top bar -->
      <div
        style="display:flex; align-items:center; justify-content:space-between; padding:1rem 2rem; background:#13151d; border-bottom:2px solid #2a2d3a; flex-shrink:0;">
        <div style="display:flex; align-items:center; gap:12px;">
          <span style="font-size:1.8rem;">🧾</span>
          <div>
            <div style="font-size:1.2rem; font-weight:800; color:#f4f4f5; letter-spacing:-0.5px;">Confirm Order</div>
            <div style="font-size:0.78rem; color:#a0a0a8;">Review items &amp; billing details before printing</div>
          </div>
        </div>
        <button class="close-btn" onclick="closeModal('checkoutConfirmModal')"
          style="font-size:1.4rem; width:38px; height:38px;">&times;</button>
      </div>

      <!-- Two panel body -->
      <div style="flex:1; display:flex; overflow:hidden;">

        <!-- LEFT: Product List -->
        <div
          style="flex:1.4; overflow-y:auto; padding:1.5rem 2rem; border-right:2px solid #2a2d3a; background:#13151d;">
          <div
            style="font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:#a0a0a8; margin-bottom:1rem;">
            Items in Cart</div>
          <div id="checkoutItemsPanel">
            <!-- Populated by JS -->
          </div>
        </div>

        <!-- RIGHT: Billing Summary -->
        <div
          style="width:380px; min-width:340px; display:flex; flex-direction:column; background:#0e1015; overflow-y:auto;">
          <div id="checkoutSummaryPanel" style="flex:1; padding:1.5rem;">
            <!-- Populated by JS -->
          </div>
          <!-- Confirm button pinned at bottom -->
          <div style="padding:1.2rem 1.5rem; border-top:2px solid #2a2d3a; background:#13151d; flex-shrink:0;">
            <button class="btn btn-primary btn-block"
              style="font-size:1.1rem; font-weight:800; padding:1rem; border-radius:12px;" onclick="confirmCheckout()">✅
              Confirm &amp; Print Bill</button>
            <button class="btn btn-outline btn-block" style="margin-top:0.6rem; font-size:0.9rem; color:#a0a0a8;"
              onclick="closeModal('checkoutConfirmModal')">← Back to Cart</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Return Items Modal -->
  <div class="modal-overlay" id="returnItemsModal">
    <div class="modal-content" style="max-width:700px; width:95%; max-height:90vh; overflow:hidden; display:flex; flex-direction:column;">
      <div class="modal-header">
        <div class="modal-title">Return Items — <span id="returnInvoiceNumber">-</span></div>
        <button class="close-btn" onclick="closeModal('returnItemsModal')">&times;</button>
      </div>
      <div style="padding:0 2rem 1rem 2rem; border-bottom:1px solid var(--border);">
        <div style="display:flex; justify-content:space-between; font-size:0.85rem;">
          <span style="color:var(--muted);">Customer: <strong id="returnCustomerName" style="color:var(--text-strong);">-</strong></span>
          <span style="color:var(--muted);">Date: <strong id="returnDate" style="color:var(--text-strong);">-</strong></span>
        </div>
      </div>
      <input type="hidden" id="returnInvoiceId">
      <div style="overflow-y:auto; flex:1; padding:1rem 2rem;">
        <table style="width:100%; border-collapse:collapse; font-size:0.85rem;">
          <thead>
            <tr style="border-bottom:2px solid var(--border);">
              <th style="text-align:left; padding:6px 4px;">Product</th>
              <th style="text-align:center; padding:6px 4px;">Sold</th>
              <th style="text-align:center; padding:6px 4px;">Returned</th>
              <th style="text-align:center; padding:6px 4px;">Return Qty</th>
              <th style="text-align:right; padding:6px 4px;">Refund (₹)</th>
              <th style="text-align:center; padding:6px 4px; width:30px;"></th>
            </tr>
          </thead>
          <tbody id="returnItemsBody">
          </tbody>
        </table>
        <div class="input-group" style="margin-top:1rem;">
          <label class="input-label">Reason <span style="color:var(--danger);">*</span></label>
          <div style="display:flex; gap:4px; flex-wrap:wrap; margin-bottom:6px;">
            <button type="button" class="btn btn-sm" style="font-size:0.7rem;padding:2px 8px;" onclick="document.getElementById('returnReason').value='Wrong size'">Wrong size</button>
            <button type="button" class="btn btn-sm" style="font-size:0.7rem;padding:2px 8px;" onclick="document.getElementById('returnReason').value='Damaged'">Damaged</button>
            <button type="button" class="btn btn-sm" style="font-size:0.7rem;padding:2px 8px;" onclick="document.getElementById('returnReason').value='Quality issue'">Quality issue</button>
            <button type="button" class="btn btn-sm" style="font-size:0.7rem;padding:2px 8px;" onclick="document.getElementById('returnReason').value='Wrong item'">Wrong item</button>
            <button type="button" class="btn btn-sm" style="font-size:0.7rem;padding:2px 8px;" onclick="document.getElementById('returnReason').value='Changed mind'">Changed mind</button>
          </div>
          <input type="text" id="returnReason" class="input-field" placeholder="e.g. Damaged / Wrong size" required>
        </div>
      </div>
      <div style="padding:1.5rem 2rem; border-top:1px solid var(--border); display:flex; gap:10px; justify-content:flex-end;">
        <button class="btn btn-outline" onclick="closeModal('returnItemsModal')">Cancel</button>
        <button class="btn btn-primary" onclick="submitReturn()">Process Return</button>
      </div>
    </div>
  </div>

  <!-- Delete Bill Confirmation Modal -->
  <div class="modal-overlay" id="deleteBillModal">
    <div class="modal-content" style="max-width:420px; text-align:center;">
      <div style="font-size:2.5rem; margin-bottom:0.5rem;">🗑️</div>
      <h3 style="color:var(--text-strong); margin-bottom:0.5rem;">Delete This Bill?</h3>
      <p id="deleteBillMessage" style="color:var(--muted); font-size:0.9rem; margin-bottom:1.5rem;">The purchased items
        will be restored to stock.</p>
      <div style="display:flex; gap:1rem;">
        <button class="btn btn-outline btn-block" onclick="closeModal('deleteBillModal')">Cancel</button>
        <button class="btn btn-block" id="deleteBillConfirmBtn"
          style="background:var(--danger); color:white; border:none;" onclick="executeBillDelete()">Yes, Delete</button>
      </div>
    </div>
  </div>

  <!-- Restock Modal -->
  <div class="modal-overlay" id="modalOverlay" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <div class="modal-content" style="max-width: 420px;">

      <div class="modal-header">
        <div class="modal-title" id="modalTitle">
          <i class="ti ti-package"></i>
          Place restock order
        </div>
        <button class="close-btn" onclick="closeModal('modalOverlay')" aria-label="Close modal">&times;</button>
      </div>

      <div class="stock-row">
        <div class="stock-pill">
          <span class="pill-label">Current stock</span>
          <span class="pill-value" id="restockCurrentStock">-</span>
        </div>
        <div class="stock-pill">
          <span class="pill-label">Max stock</span>
          <span class="pill-value" id="restockMaxStock">-</span>
        </div>
        <div class="stock-pill">
          <span class="pill-label">Deficit</span>
          <span class="pill-value highlight" id="restockDeficit">-</span>
        </div>
      </div>

      <div class="field-group">
        <div class="label-row">
          <label class="field-label" for="orderQty" id="restockQtyLabel">
            Order quantity (units)<span class="required">*</span>
          </label>
          <span class="autofill-badge">
            <i class="ti ti-wand"></i> Auto-filled
          </span>
        </div>
        <div class="input-wrapper">
          <input type="number" id="orderQty" min="1" />
          <span class="input-unit" id="restockUnitSuffix">units</span>
        </div>
        <p class="helper-text" id="restockHelperText">
          <i class="ti ti-info-circle"></i>
          Suggested based on your maximum stock limit. You can adjust this amount before confirming.
        </p>
      </div>

      <hr class="divider" />

      <div class="modal-actions" style="display: flex; gap: 8px; justify-content: flex-end;">
        <button class="btn btn-outline" onclick="closeModal('modalOverlay')">Cancel</button>
        <button class="btn btn-primary" onclick="confirmRestockOrder()" style="display: inline-flex; align-items: center; gap: 6px;">
          <i class="ti ti-check"></i> Confirm order
        </button>
      </div>

    </div>
  </div>

<!-- Delete Product Confirmation Modal -->
<div id="deleteProductModal" class="modal-overlay">
    <div class="modal-content" style="max-width: 420px; border-radius: 12px; padding: 20px 24px; background: var(--card, #ffffff); border: 1px solid var(--border, #e2e8f0); box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1);">
        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border, #f1f5f9); padding-bottom: 10px; margin-bottom: 14px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <div class="modal-title" style="font-size: 1.05rem; font-weight: 700; color: var(--text-strong, #0f172a);">Delete Product</div>
                <span style="font-size: 0.65rem; font-weight: 700; text-transform: uppercase; background: rgba(239, 68, 68, 0.1); color: var(--danger, #ef4444); padding: 2px 7px; border-radius: 12px; letter-spacing: 0.04em;">Irreversible</span>
            </div>
            <button class="close-btn" onclick="closeModal('deleteProductModal')" style="background: transparent; border: none; font-size: 1.25rem; cursor: pointer; color: var(--muted, #64748b); line-height: 1;">&times;</button>
        </div>
        
        <div class="modal-body" id="deleteProductModalBody" style="padding: 0;">
            <!-- Compact Product Preview Box with Warning Badge -->
            <div style="background: var(--bg-hover, #f8fafc); border: 1px solid var(--border, #e2e8f0); border-radius: 8px; padding: 10px 14px; margin-bottom: 12px; display: flex; align-items: center; gap: 12px;">
                <div style="width: 38px; height: 38px; border-radius: 50%; background: rgba(239, 68, 68, 0.1); color: var(--danger, #ef4444); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    </svg>
                </div>
                <div style="flex: 1; min-width: 0;">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <span style="font-size: 0.7rem; font-weight: 700; color: var(--muted, #64748b); text-transform: uppercase;" id="deleteProductId">PRODUCT ID</span>
                        <span style="font-size: 0.7rem; font-weight: 600; padding: 2px 6px; border-radius: 4px; background: var(--card, #ffffff); border: 1px solid var(--border, #e2e8f0); color: var(--text-strong, #334155);" id="deleteProductCategory">Category</span>
                    </div>
                    <div style="font-size: 0.9rem; font-weight: 700; color: var(--text-strong, #0f172a); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 2px;" id="deleteProductName">Product Name</div>
                </div>
            </div>
            
            <p style="font-size: 0.85rem; font-weight: 600; text-align: center; color: var(--text-strong, #1f2937); margin: 0; line-height: 1.45;">
                Are you sure you want to delete this product? Inventory stock records will be permanently removed.
            </p>
        </div>
        
        <div class="modal-footer" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 16px;">
            <button class="btn btn-outline" onclick="closeModal('deleteProductModal')" style="padding: 8px 14px; font-size: 0.875rem; font-weight: 600; border-radius: 6px;">Cancel</button>
            <button class="btn btn-danger" id="confirmDeleteBtn" style="padding: 8px 14px; font-size: 0.875rem; font-weight: 600; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="3 6 5 6 21 6"></polyline>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                </svg>
                Delete Product
            </button>
        </div>
    </div>
</div>

  <!--Vendor page-->
<!-- Edit Purchase Modal -->
<div id="editPurchaseModal" class="modal-overlay">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <div class="modal-title">Edit Purchase</div>
            <button class="close-btn" onclick="closeModal('editPurchaseModal')">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="editPurchaseId">
            
            <div class="input-group">
                <label>Purchase Date</label>
                <input type="date" id="editPurchaseDate" class="input-field">
            </div>
            <div class="input-group">
                <label>Base Amount (₹)</label>
                <input type="number" id="editBaseAmount" class="input-field" step="0.01">
            </div>
            <div class="input-group">
                <label>Amount Paid (₹)</label>
                <input type="number" id="editAmountPaid" class="input-field" step="0.01">
            </div>
            <!-- ── Items Section ── -->
            <div style="margin-top: 1.5rem; border-top: 1px solid var(--border); padding-top: 1rem;">
                <label style="font-weight: 600; display: block; margin-bottom: 0.5rem;">Items</label>
                <div id="editItemsContainer">
                    <!-- Items will be rendered here by JS -->
                </div>
                <button type="button" class="btn btn-outline btn-sm" onclick="addEditItemRow()">+ Add Item</button>
            </div>
        </div>
        <div class="modal-footer" style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 1.5rem;">
        </div>
        <div class="modal-footer" style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 1.5rem;">
            <button class="btn btn-outline" onclick="closeModal('editPurchaseModal')">Cancel</button>
            <button class="btn btn-primary" id="saveEditPurchaseBtn" onclick="saveEditPurchase()">Update Purchase</button>
        </div>
    </div>
</div>