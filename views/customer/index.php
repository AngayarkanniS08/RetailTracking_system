<!-- 
  FEATURE DOCUMENTATION: Credit (Kadan)
  - Customer Credit Management: Tracking of outstanding customer balances and historic payments.
  - Registration: "+ Add Customer" modal for onboarding new clients with credit accounts.
  - Metrics: Displays outstanding balances, total purchases, and total payments per customer.
-->
        <section id="credit_kadan" class="view-section">
          <div class="card-header">
            <span>Customer Credit (Kadan)</span>
            <button class="btn btn-outline btn-sm" onclick="openModal('addCustomerModal')">+ Add Customer</button>
          </div>
          <div class="input-group" style="margin-bottom:1rem;">
            <input type="text" id="creditSearch" class="input-field" placeholder="Search customers by name or phone..."
                   oninput="onCreditSearchInput()">
          </div>
          <div class="card-panel">
            <div class="table-container">
              <table id="creditTable">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Date</th>
                    <th>Customer Name</th>
                    <th>Phone</th>
                    <th>Total Purchases</th>
                    <th>Total Paid</th>
                    <th>Outstanding Balance</th>
                    <th>Bills Cleared</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <!-- Rendered via JS -->
                </tbody>
              </table>
            </div>
          </div>
        </section>
