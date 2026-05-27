  <!-- MODALS -->

  <!-- Low Stock Alert Modal -->
  <div class="modal-overlay" id="lowStockAlertModal">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-title">🔔 Set Low Stock Alert</div>
        <button class="close-btn" onclick="closeModal('lowStockAlertModal')">&times;</button>
      </div>
      <div class="input-group">
        <label class="input-label">Select Product</label>
        <select id="alertProductSelect" class="input-field"></select>
      </div>
      <div class="input-group">
        <label class="input-label">Alert when stock falls below</label>
        <input type="number" id="alertThreshold" class="input-field" placeholder="e.g. 10" min="1">
      </div>
      <div id="existingAlertsList" style="margin-bottom: 1rem;"></div>
      <button class="btn btn-primary btn-block" onclick="saveLowStockAlert()">Set Alert</button>
    </div>
  </div>

  <!-- Add Stock Modal -->
  <div class="modal-overlay" id="addStockModal">
    <div class="modal-content" style="max-width: 720px; width: 95%; max-height: 90vh; overflow: hidden; display: flex; flex-direction: column; padding: 0;">
      <div class="modal-header" style="padding: 1.5rem 2rem; margin-bottom: 0; border-bottom: 1px solid var(--border); background: var(--card); z-index: 10;">
        <div class="modal-title" id="addStockModalTitle">Add New Stock Batch</div>
        <button class="close-btn" onclick="closeModal('addStockModal')">&times;</button>
      </div>
      <div style="padding: 2rem; overflow-y: auto; flex: 1;">
        <div class="input-group">
        <label class="input-label">Select Product</label>
        <select id="stockProduct" class="input-field" onchange="calculateInventoryMath()"></select>
      </div>
      <div class="input-group">
        <label class="input-label">Vendor Name</label>
        <input type="text" id="stockVendor" class="input-field" placeholder="e.g. Metro Wholesale">
      </div>
      <div class="segment-control" style="margin-bottom: 20px;">
        <div class="segment-item active" id="segWholesale" onclick="setPricingMode('wholesale')">📦 Wholesale Mode</div>
        <div class="segment-item" id="segRetail" onclick="setPricingMode('retail')">✂️ Retail Mode</div>
      </div>
      
      <div class="pricing-container" id="pricingGrid" style="display:grid; grid-template-columns: 1fr; gap: 15px; margin-bottom: 15px;">

        <!-- Wholesale Column -->
        <div style="background: var(--bg-100); padding: 12px; border-radius: 8px; border: 1px solid var(--bg-hover);">
          <div style="font-size: 0.75rem; font-weight: 700; color: var(--accent); text-transform: uppercase; margin-bottom: 10px; display:flex; align-items:center; gap:5px;">
            📦 Wholesale (Full Unit)
          </div>
          <div class="input-group">
            <label class="input-label">Base Purchase Price</label>
            <input type="number" id="stockPP" class="input-field" placeholder="0.00" oninput="calculateInventoryMath('profit')">
          </div>
          <div class="input-group">
            <label class="input-label">Wholesale Profit (₹)</label>
            <input type="number" id="stockProfit" class="input-field" placeholder="0.00" oninput="calculateInventoryMath('sp')">
          </div>
          <div class="input-group">
            <label class="input-label">Wholesale Selling P.</label>
            <input type="number" id="stockSP" class="input-field" placeholder="0.00" oninput="calculateInventoryMath('profit')">
          </div>
          <div id="invGstDisplay" style="font-size: 0.7rem; color: var(--muted); margin-top:5px;">
            <span id="invGstRateText">GST (0%): ₹0.00</span><br>
            <strong id="invTotalText" style="color:var(--ok)">Total: ₹0.00</strong>
          </div>
        </div>

        <!-- Retail Column -->
        <div id="retailColumn" style="background: var(--bg-100); padding: 12px; border-radius: 8px; border: 1px solid var(--bg-hover); display: none;">

          <div style="font-size: 0.75rem; font-weight: 700; color: var(--warn); text-transform: uppercase; margin-bottom: 10px; display:flex; align-items:center; gap:5px;">
            ✂️ Retail (Loose/Per Unit)
          </div>
          <div class="input-group">
            <label class="input-label">Unit Cost (Auto)</label>
            <input type="number" id="retailBasePrice" class="input-field" placeholder="0.00" oninput="calculateRetailMath('profit')">
          </div>
          <div class="input-group">
            <label class="input-label">Retail Profit / Unit (₹)</label>
            <input type="number" id="retailProfit" class="input-field" placeholder="0.00" oninput="calculateRetailMath('sp')">
          </div>
          <div class="input-group">
            <label class="input-label">Retail Selling P. / Unit</label>
            <input type="number" id="retailSP" class="input-field" placeholder="0.00" oninput="calculateRetailMath('profit')">
          </div>
          <div id="retailGstDisplay" style="font-size: 0.7rem; color: var(--muted); margin-top:5px;">
            <span id="retailGstRateText">GST (0%): ₹0.00</span><br>
            <strong id="retailTotalText" style="color:var(--warn)">Total: ₹0.00</strong>
          </div>
        </div>
      </div>

      <div class="input-group">
        <label class="input-label">Stock Quantity (Total Units)</label>
        <input type="number" id="stockQty" class="input-field" placeholder="0" oninput="calculateInventoryMath('profit')">
      </div>

      <div class="input-group">
        <label class="input-label">Date</label>
        <input type="date" id="stockDate" class="input-field">
      </div>
      <div style="padding: 0 2rem 2rem 2rem; margin-top: auto; border-top: 1px solid var(--border); background: var(--card); display: flex; gap: 10px;">
        <button class="btn btn-outline btn-block" onclick="closeModal('addStockModal')" style="margin-top: 1.5rem;">Cancel</button>
        <button class="btn btn-primary btn-block" id="addStockModalBtn" onclick="saveStock()" style="margin-top: 1.5rem;">Save Batch Entry</button>
      </div>
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
        <label class="input-label">Full Name</label>
        <input type="text" id="custName" class="input-field" placeholder="Customer Name">
      </div>
      <div class="input-group">
        <label class="input-label">Phone Number</label>
        <input type="text" id="custPhone" class="input-field" placeholder="10-digit number">
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
        <input type="number" id="payAmount" class="input-field" placeholder="Enter amount">
      </div>
      <button class="btn btn-primary btn-block" onclick="processPayment()">Record Payment</button>
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
        <select id="slStockName" class="input-field" onchange="updateSlUnit()">
          <!-- Populated from products -->
        </select>
      </div>
      <div class="input-group">
        <label class="input-label">Vendor Name</label>
        <input type="text" id="slVendorName" class="input-field" placeholder="e.g. Erode Textile Market">
      </div>
      <div class="d-flex">
        <div class="input-group" style="flex:1;">
          <label class="input-label" id="slQtyLabel">Quantity</label>
          <input type="number" id="slQty" class="input-field" placeholder="0" min="0">
        </div>
        <div class="input-group" style="flex:1;">
          <label class="input-label">Base Amount (₹)</label>
          <input type="number" id="slAmount" class="input-field" placeholder="0.00" min="0" oninput="calculateSlGst()">
        </div>
      </div>
      <div id="slGstDisplay"
        style="margin-top:-10px; margin-bottom:15px; font-size: 0.8rem; color: var(--muted); display:flex; justify-content:space-between; padding: 0 5px;">
        <span id="slGstRateText">GST (0%): ₹0.00</span>
        <strong id="slTotalText" style="color:var(--text-strong)">Total Bill: ₹0.00</strong>
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
      <button class="btn btn-primary btn-block" onclick="saveStockEntry()">Save Entry</button>
    </div>
  </div>

  <!-- Add Product Modal -->
  <div class="modal-overlay" id="addProductModal">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-title" id="addProductModalTitle">Add New Product</div>
        <button class="close-btn" onclick="closeModal('addProductModal')">&times;</button>
      </div>
      <div class="input-group">
        <label class="input-label">Product Name</label>
        <input type="text" id="pmProductName" class="input-field" placeholder="e.g. Silk Fabric">
      </div>
      <div class="d-flex">
        <div class="input-group" style="flex:1;">
          <label class="input-label">HSN Code</label>
          <input type="text" id="pmProductHsn" class="input-field" placeholder="e.g. 5208">
        </div>
        <div class="input-group" style="flex:1;">
          <label class="input-label">GST (%)</label>
          <input type="number" id="pmProductGst" class="input-field" placeholder="0" min="0" max="100">
        </div>
      </div>
      <div class="d-flex" style="gap:10px;">
        <div class="input-group" style="flex:1;">
          <label class="input-label">Category</label>
          <select id="pmProductCategory" class="input-field" onchange="onCategoryChange(this.value)"></select>
        </div>
        <div class="input-group" style="flex:1;">
          <label class="input-label">Subcategory</label>
          <select id="pmProductSubcategory" class="input-field">
            <option value="">No Subcategory</option>
          </select>
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
      <button class="btn btn-primary btn-block" id="addProductModalBtn" onclick="saveProduct()">Save Product</button>
    </div>
  </div>

  <!-- Add Category Modal -->
  <div class="modal-overlay" id="addCategoryModal">
    <div class="modal-content" style="max-width: 500px;">
      <div class="modal-header">
        <div class="modal-title">Manage Categories & Subcategories</div>
        <button class="close-btn" onclick="closeModal('addCategoryModal')">&times;</button>
      </div>
      
      <div style="margin-bottom: 20px; border-bottom: 1px solid var(--border); padding-bottom: 15px;">
        <div style="font-weight: 600; margin-bottom: 10px; font-size: 0.9rem; color: var(--accent);">Add Category</div>
        <div class="input-group">
          <input type="text" id="pmCategoryName" class="input-field" placeholder="e.g. Lace Work">
        </div>
        <button class="btn btn-primary btn-sm" onclick="saveCategory()">Save Category</button>
      </div>

      <div>
        <div style="font-weight: 600; margin-bottom: 10px; font-size: 0.9rem; color: var(--accent);">Add Subcategory</div>
        
        <div class="input-group">
          <label class="input-label">Select Category</label>
          <select id="pmSubCatParent" class="input-field"></select>
        </div>
        <div class="input-group">
          <label class="input-label">Subcategory Name</label>
          <input type="text" id="pmSubCategoryName" class="input-field" placeholder="e.g. Fancy Lace">
        </div>
        <button class="btn btn-primary btn-sm" onclick="saveSubcategory()">Save Subcategory</button>
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

