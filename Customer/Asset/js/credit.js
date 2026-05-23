    function getCustomerLedgerTotals(custId) {
      let totalBilled = 0, totalPaid = 0, billsCleared = 0, totalBills = 0;
      credit_ledger.filter(l => l.customer_id === custId).forEach(l => {
        totalBilled += l.amount_billed;
        totalPaid += l.amount_paid;
        if (l.amount_billed > 0) {
          totalBills++;
          if (l.balance <= 0) billsCleared++;
        }
      });
      return { totalBilled, totalPaid, balance: totalBilled - totalPaid, billsCleared, totalBills };
    }

    function renderCredit() {
      const tbody = document.querySelector('#creditTable tbody');
      tbody.innerHTML = '';

      customers.forEach(c => {
        const totals = getCustomerLedgerTotals(c.id);
        const balColor = totals.balance > 0 ? 'var(--danger)' : 'var(--ok)';
        const entries = credit_ledger.filter(l => l.customer_id === c.id);
        const firstDate = entries.length > 0 ?
          entries.reduce((min, l) => l.created_at < min ? l.created_at : min, entries[0].created_at)
            .toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }) : '-';
        const billsClass = `credit-bills-${c.id}`;

        tbody.innerHTML += `
          <tr>
            <td style="font-family: var(--mono);">${c.id}</td>
            <td style="color: var(--muted); font-size:0.85rem;">${firstDate}</td>
            <td style="font-weight: 500; color: var(--text-strong);">${c.name}</td>
            <td>${c.phone}</td>
            <td>${formatCurrency(totals.totalBilled)}</td>
            <td>${formatCurrency(totals.totalPaid)}</td>
            <td style="font-weight: 700; color: ${balColor}">${formatCurrency(totals.balance)}</td>
            <td>
              <span class="badge ${totals.billsCleared === totals.totalBills && totals.totalBills > 0 ? 'badge-ok' : 'badge-warn'}">
                ${totals.billsCleared} / ${totals.totalBills}
              </span>
            </td>
            <td style="display:flex; gap:8px; align-items:center;">
              ${entries.length > 0 ? `<button class="btn btn-outline btn-sm" onclick="toggleBills('${billsClass}')" style="font-weight:600; min-width:90px;">🔍 Bills</button>` : ''}
              ${totals.balance > 0 ? `<button class="btn btn-sm btn-primary" onclick="openPaymentModal('${c.id}')" style="min-width:70px;">Settle</button>` : `<span class="badge badge-ok">Cleared</span>`}
            </td>
          </tr>
        `;

        // Add expandable bill detail rows (hidden by default)
        entries.sort((a, b) => b.created_at - a.created_at).forEach(entry => {
          const dateStr = entry.created_at.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
          const timeStr = entry.created_at.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' });
          const isPay = entry.amount_billed === 0;
          const typeLabel = isPay
            ? '<span style="color:var(--ok); font-weight:600;">💰 Payment</span>'
            : `<span style="color:var(--accent);">🧾 ${entry.sale_id || 'Invoice'}</span>`;
          const entryBal = entry.balance;
          const balBadge = entryBal <= 0
            ? '<span class="badge badge-ok" style="font-size:0.7rem;">Cleared</span>'
            : `<span class="badge badge-danger" style="font-size:0.7rem;">₹${Math.abs(entryBal).toFixed(0)} due</span>`;

          // View button — for invoices OR payments
          let viewBtn = '';
          if (isPay) {
            viewBtn = `<button class="btn btn-outline btn-sm" style="padding:1px 6px; font-size:0.7rem;" onclick="viewPaymentReceipt('${entry.id}')">View</button>`;
          } else if (entry.sale_id) {
            viewBtn = `<button class="btn btn-outline btn-sm" style="padding:1px 6px; font-size:0.7rem;" onclick="viewBillReceipt('${entry.sale_id}')">View</button>`;
          }

          tbody.innerHTML += `
            <tr class="${billsClass}" style="display:none; background: var(--bg-100);">
              <td colspan="2" style="font-size:0.8rem; color:var(--muted); padding-left:24px;">${dateStr} ${timeStr}</td>
              <td style="font-size:0.85rem;">${typeLabel}</td>
              <td></td>
              <td style="font-size:0.85rem;">${isPay ? '-' : formatCurrency(entry.amount_billed)}</td>
              <td style="font-size:0.85rem; color:var(--ok);">${formatCurrency(entry.amount_paid)}</td>
              <td style="font-size:0.85rem;">${balBadge}</td>
              <td></td>
              <td>${viewBtn}</td>
            </tr>
          `;
        });
      });
    }

    function saveCustomer() {
      const name = document.getElementById('custName').value;
      const phone = document.getElementById('custPhone').value;
      if (!name || !phone) return alert('Fill all fields');

      customers.push({ id: generateId('C'), name, phone });
      closeModal('addCustomerModal');
      document.getElementById('custName').value = '';
      document.getElementById('custPhone').value = '';
      renderCredit();
      populateCustomerSelect();
    }

    function openPaymentModal(custId) {
      const cust = getCustomer(custId);
      const totals = getCustomerLedgerTotals(custId);
      document.getElementById('payCustName').innerText = cust.name;
      document.getElementById('payOutstanding').innerText = formatCurrency(totals.balance);
      document.getElementById('payCustId').value = custId;
      document.getElementById('payAmount').value = totals.balance;
      openModal('paymentModal');
    }

    function processPayment() {
      const custId = document.getElementById('payCustId').value;
      const amt = parseFloat(document.getElementById('payAmount').value);
      if (!amt || amt <= 0) return alert('Invalid amount');

      const totals = getCustomerLedgerTotals(custId);
      if (amt > totals.balance) return alert('Amount exceeds outstanding balance');

      // Add a payment-only record (amount_billed = 0)
      const payId = generateId('PAY');
      credit_ledger.push({
        id: payId, customer_id: custId, sale_id: null,
        amount_billed: 0, amount_paid: amt, balance: -amt, created_at: new Date()
      });

      const newBalance = totals.balance - amt;
      printPaymentReceipt(payId, custId, amt, newBalance);

      closeModal('paymentModal');
      renderCredit();
    }

    function printPaymentReceipt(receiptId, custId, amountPaid, newBalance) {
      const shopName = "PUDEERA FASHION";
      const address = "Shop no 9, 1st floor, Vallioor busstand";
      const dateStr = new Date().toLocaleString();
      const customer = getCustomer(custId).name;

      let receipt = `========================================
         ${shopName}
         ${address}
========================================
Receipt No: ${receiptId}
Date: ${dateStr}
Customer Name: ${customer}
Description: CREDIT BALANCE PAYMENT
----------------------------------------

AMOUNT PAID:             Rs. ${amountPaid.toFixed(2)}
----------------------------------------
Remaining Debt Balance:  Rs. ${newBalance.toFixed(2)}

========================================
     Thank You For Your Payment!
========================================`;

      const printWindow = window.open('', '_blank', 'width=400,height=600');
      printWindow.document.write(`
        <html><head><title>Payment Receipt</title></head>
        <body style="font-family: monospace; white-space: pre-wrap; padding: 20px;">
${receipt}
        </body>
        <script>window.print(); window.onafterprint = function(){ window.close(); }<\/script>
        </html>
      `);
      printWindow.document.close();
    }

    function viewPaymentReceipt(ledgerId) {
      const entry = credit_ledger.find(l => l.id === ledgerId);
      if (!entry) return;

      const cust = getCustomer(entry.customer_id);
      const custName = cust ? cust.name : 'Unknown';
      const dateTime = entry.created_at.toLocaleString('en-IN');

      // Calculate balance at the time of this payment
      const allEntries = credit_ledger.filter(l => l.customer_id === entry.customer_id);
      let runningBilled = 0, runningPaid = 0;
      allEntries.sort((a, b) => a.created_at - b.created_at).forEach(l => {
        runningBilled += l.amount_billed;
        runningPaid += l.amount_paid;
        if (l.id === ledgerId) return;
      });
      const balAfter = runningBilled - runningPaid;

      const receipt = `========================================
        PUDEERA FASHION
        Shop no 9, 1st floor, Vallioor
        busstand
========================================
Receipt No: ${entry.id}
Date: ${dateTime}
Customer: ${custName}
Type: CREDIT BALANCE SETTLEMENT
========================================

Amount Cleared:              ${formatCurrency(entry.amount_paid)}

----------------------------------------
Remaining Balance:           ${formatCurrency(balAfter > 0 ? balAfter : 0)}
----------------------------------------
${balAfter <= 0 ? '\n    *** ALL DUES CLEARED ***\n' : ''}
========================================
     Thank You For Your Payment!
========================================`;

      document.getElementById('billReceiptContent').innerText = receipt;
      openModal('billReceiptModal');
    }


