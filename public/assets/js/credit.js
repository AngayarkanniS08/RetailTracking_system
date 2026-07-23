/* credit.js — Customer Credit (Kadan) Module */

window.creditCustomers = [];
window.creditLedgerCache = {};
let _creditSearchTimer = null;
let _creditPage = 1;
let _creditTotalPages = 1;
const _creditPerPage = 10;
let _creditSearchTerm = '';

async function loadCreditPage(search, page) {
    const section = document.getElementById('credit_kadan');
    if (!section || !section.classList.contains('active')) return;

    page = page || _creditPage;
    _creditPage = page;
    _creditSearchTerm = search || '';

    try {
        let url = `/api/customers?page=${page}&limit=${_creditPerPage}`;
        if (search) url += '&search=' + encodeURIComponent(search);
        const data = await window.apiRequest(url);
        if (data && !data.error) {
            window.creditCustomers = Array.isArray(data) ? data : (data.data || []);
            _creditTotalPages = data.pagination?.total_pages || 1;
            renderCreditTable();
            renderCreditPagination(data.pagination);
        }
    } catch (e) {
        console.error('Failed to load credit page:', e);
    }
}

function onCreditSearchInput() {
    clearTimeout(_creditSearchTimer);
    const input = document.getElementById('creditSearch');
    const term = input ? input.value.trim() : '';
    _creditSearchTimer = setTimeout(function() {
        loadCreditPage(term || null, 1);
    }, 300);
}

function renderCreditTable() {
    const tbody = document.querySelector('#creditTable tbody');
    if (!tbody) return;
    tbody.innerHTML = '';

    if (window.creditCustomers.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;color:var(--muted);padding:2rem;">No customers found. Add one to get started.</td></tr>';
        return;
    }

    window.creditCustomers.forEach(c => {
        const bal = parseFloat(c.balance || 0);
        const balColor = bal > 0 ? 'var(--danger)' : (bal < 0 ? 'var(--accent)' : 'var(--ok)');
        const balDisplay = bal < 0 ? `Adv: ${formatCurrency(Math.abs(bal))}` : formatCurrency(bal);
        const firstDate = c.created_at ? new Date(c.created_at).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }) : '-';
        const billsClass = `credit-bills-${c.id}`.replace(/[^a-zA-Z0-9_-]/g, '_');
        const totalBills = c.total_bills || 0;
        const billsCleared = c.bills_cleared || 0;

        let actionHtml;
        if (bal > 0) {
            actionHtml = `<button class="btn btn-sm btn-primary" onclick="openPaymentModal('${c.id}')" style="min-width:70px;">Settle</button>`;
        } else if (bal < 0) {
            actionHtml = `<span class="badge badge-warn">Advance ${formatCurrency(Math.abs(bal))}</span>`;
        } else {
            actionHtml = `<span class="badge badge-ok">Cleared</span>`;
        }

        tbody.innerHTML += `
          <tr>
            <td style="font-family: var(--mono); font-size:0.8rem;">${c.id ? c.id.substring(0, 8) : '-'}</td>
            <td style="color: var(--muted); font-size:0.85rem;">${firstDate}</td>
            <td style="font-weight: 500; color: var(--text-strong);">${escHtml(c.name)}</td>
            <td>${escHtml(c.phone)}</td>
            <td>${formatCurrency(c.total_purchases || 0)}</td>
            <td>${formatCurrency(c.total_paid || 0)}</td>
            <td style="font-weight: 700; color: ${balColor}">${balDisplay}</td>
            <td>
              <span class="badge ${billsCleared === totalBills && totalBills > 0 ? 'badge-ok' : 'badge-warn'}">
                ${billsCleared} / ${totalBills}
              </span>
            </td>
            <td>
              <div style="display:flex; gap:8px; align-items:center;">
                ${totalBills > 0 ? `<button class="btn btn-outline btn-sm" onclick="toggleBills('${billsClass}','${c.id}')" style="font-weight:600; min-width:90px;">Bills</button>` : ''}
                ${actionHtml}
              </div>
            </td>
          </tr>
        `;
    });
}

function renderCreditPagination(pagination) {
    const container = document.getElementById('creditPaginationControls');
    if (!container) return;

    if (!pagination || pagination.total_pages <= 1) {
        container.style.display = 'none';
        return;
    }
    container.style.display = 'flex';
    container.innerHTML = `
        <button class="pagination-btn" id="creditPrevPageBtn" ${!pagination.has_prev ? 'disabled' : ''}>← Previous</button>
        <span class="pagination-info">Page ${pagination.current_page} of ${pagination.total_pages}</span>
        <button class="pagination-btn" id="creditNextPageBtn" ${!pagination.has_next ? 'disabled' : ''}>Next →</button>
    `;

    document.getElementById('creditPrevPageBtn')?.addEventListener('click', () => {
        if (pagination.has_prev) loadCreditPage(_creditSearchTerm, pagination.current_page - 1);
    });
    document.getElementById('creditNextPageBtn')?.addEventListener('click', () => {
        if (pagination.has_next) loadCreditPage(_creditSearchTerm, pagination.current_page + 1);
    });
}

async function toggleBills(className, custId) {
    const existingRows = document.querySelectorAll(`tr.${CSS.escape(className)}`);
    if (existingRows.length > 0) {
        existingRows.forEach(r => r.style.display = r.style.display === 'none' ? '' : 'none');
        return;
    }

    if (!window.creditLedgerCache[custId]) {
        try {
            const data = await window.apiRequest(`/api/customers/${custId}/ledger?limit=100`);
            window.creditLedgerCache[custId] = data.entries || [];
        } catch (e) {
            console.error('Failed to load ledger:', e);
            return;
        }
    }

    const entries = window.creditLedgerCache[custId];
    const tbody = document.querySelector('#creditTable tbody');

    // Find the customer row (the one with the Bills button for this className)
    let customerRow = null;
    for (let i = 0; i < tbody.rows.length; i++) {
        const btn = tbody.rows[i].querySelector('.btn');
        if (btn && btn.getAttribute('onclick') && btn.getAttribute('onclick').includes(className)) {
            customerRow = tbody.rows[i];
            break;
        }
    }
    if (!customerRow) return;

    // Group ledger entries — invoice + its payment share one row; returns, credit notes, standalone payments each get their own row
    const grouped = {};
    entries.forEach(entry => {
        // Keep cancelled invoices visible — they get a (Cancelled) badge below
        if (entry.invoice_status === 'deleted') return;

        const isStandalone = entry.entry_type === 'return' || entry.entry_type === 'credit_note';
        const key = isStandalone ? entry.id : (entry.invoice_id || entry.id);
        if (!grouped[key]) {
            grouped[key] = {
                invoice_id: entry.invoice_id,
                entry_type: entry.entry_type,
                debit: 0,
                credit: 0,
                balance: entry.balance,
                created_at: entry.created_at,
                notes: entry.notes,
                invoice_status: entry.invoice_status,
                id: entry.id,
                _isPay: entry.invoice_id ? false : (entry.entry_type === 'payment' || (entry.debit === 0 && entry.credit > 0))
            };
        }
        grouped[key].debit = Math.round((grouped[key].debit + (entry.debit || 0)) * 100) / 100;
        grouped[key].credit = Math.round((grouped[key].credit + (entry.credit || 0)) * 100) / 100;
    });

    const mergedEntries = Object.values(grouped);
    mergedEntries.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));

    let ledgerHtml = '';
    mergedEntries.forEach(entry => {
        const dateStr = entry.created_at
            ? new Date(entry.created_at).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' })
            : '-';
        const timeStr = entry.created_at
            ? new Date(entry.created_at).toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' })
            : '';
        const isPay = entry._isPay && !entry.invoice_id;
        const isCancelled = entry.invoice_status === 'cancelled';
        const statusBadge = isCancelled ? ' <span style="color:var(--danger);font-size:0.65rem;">(Cancelled)</span>' : '';
        const isReturn = entry.entry_type === 'return';
        const cnReturn = isReturn || entry.entry_type === 'credit_note';

        let typeLabel, subtitle;
        if (isPay) {
            typeLabel = '<span style="color:var(--ok); font-weight:600;">Payment</span>';
            subtitle = '';
        } else if (isReturn) {
            typeLabel = '<span style="color:#e67e22; font-weight:600;">Item Return</span>';
            var reason = entry.notes || '';
            reason = reason.replace(/^Return on invoice\s+\S+:\s*/i, '');
            subtitle = '<div style="font-size:0.7rem;color:var(--muted);margin-top:1px;">' + escHtml(reason) + '</div>';
        } else if (entry.entry_type === 'opening') {
            typeLabel = '<span style="color:var(--accent);">Opening</span>';
            subtitle = '';
        } else {
            typeLabel = `<span style="color:var(--accent);">${entry.notes || 'Invoice'}${statusBadge}</span>`;
            subtitle = '';
        }

        const bal = entry.balance;
        const balBadge = bal <= 0
            ? '<span class="badge badge-ok" style="font-size:0.7rem;">Cleared</span>'
            : `<span class="badge badge-danger" style="font-size:0.7rem;">${formatCurrency(bal)} due</span>`;

        let viewBtn = '';
        if (isPay) {
            viewBtn = `<button class="btn btn-outline btn-sm" style="padding:1px 6px; font-size:0.7rem;" onclick="viewPaymentReceipt('${entry.id}')">View</button>`;
        } else if (entry.invoice_id && entry.invoice_status !== 'cancelled') {
            if (isReturn) {
                viewBtn = '';
            } else {
                viewBtn = `<button class="btn btn-outline btn-sm" style="padding:1px 6px; font-size:0.7rem;" onclick="viewInvoice('${entry.invoice_id}')">View</button>`;
            }
        }

        ledgerHtml += `
          <tr class="${CSS.escape(className)}" style="display:none; background: var(--bg-100);">
            <td colspan="2" style="font-size:0.8rem; color:var(--muted); padding-left:24px;">${dateStr} ${timeStr}</td>
            <td style="font-size:0.85rem;">${typeLabel}${subtitle}</td>
            <td></td>
            <td style="font-size:0.85rem;">${isPay || isReturn ? '-' : formatCurrency(entry.debit)}</td>
            <td style="font-size:0.85rem; color:var(--ok);">${entry.credit > 0 ? formatCurrency(entry.credit) : '-'}</td>
            <td style="font-size:0.85rem;">${balBadge}</td>
            <td></td>
            <td>${viewBtn}</td>
          </tr>
        `;
    });

    customerRow.insertAdjacentHTML('afterend', ledgerHtml);
    setTimeout(function() {
        document.querySelectorAll(`tr.${CSS.escape(className)}`).forEach(function(r) { r.style.display = ''; });
    }, 50);
}

function viewInvoice(invoiceId) {
    const apiBase = `${window.location.protocol}//${window.location.hostname}:8081`;
    const token = localStorage.getItem('auth_token');
    const printWindow = window.open('', '_blank', 'width=400,height=600');
    printWindow.document.write(`
      <html><head><title>Loading Invoice...</title></head>
      <body style="font-family:sans-serif;padding:40px;text-align:center;">
        <h2>Loading Invoice...</h2>
        <p>Please wait while we fetch the bill details.</p>
      </body></html>
    `);
    printWindow.document.close();

    fetch(`${apiBase}/api/invoices/${invoiceId}/receipt`, {
        headers: { 'Authorization': 'Bearer ' + token }
    })
    .then(res => res.text())
    .then(html => {
        printWindow.document.write(html);
        printWindow.document.close();
    })
    .catch(() => {
        printWindow.document.write('<html><body style="padding:40px;text-align:center;"><h2>Failed to load invoice</h2></body></html>');
        printWindow.document.close();
    });
}

// ── Add Customer ──────────────────────────────────────────

function saveCustomer() {
    const name = document.getElementById('custName').value.trim();
    const phone = document.getElementById('custPhone').value.trim();
    const creditLimit = parseFloat(document.getElementById('custCreditLimit')?.value) || 0;
    const openingBalance = parseFloat(document.getElementById('custOpeningBalance')?.value) || 0;

    if (!name || !phone) {
        alert('Customer name and phone are required');
        return;
    }

    const payload = { name, phone };
    if (creditLimit > 0) payload.credit_limit = creditLimit;
    if (openingBalance > 0) payload.opening_balance = openingBalance;

    window.apiRequest('/api/customers', {
        method: 'POST',
        body: JSON.stringify(payload)
    }).then(async (data) => {
        closeModal('addCustomerModal');
        document.getElementById('custName').value = '';
        document.getElementById('custPhone').value = '';
        if (document.getElementById('custCreditLimit')) document.getElementById('custCreditLimit').value = '';
        if (document.getElementById('custOpeningBalance')) document.getElementById('custOpeningBalance').value = '';
        const custIdField = document.getElementById('billCustomerId');
        if (custIdField) {
            custIdField.value = data.customer.id;
            const custInput = document.getElementById('customerSearchInput');
            if (custInput) custInput.value = data.customer.name;
        }
        loadCreditPage(null, 1);
    }).catch(err => {
        alert('Failed to add customer: ' + err.message);
    });
}

// ── Payment ───────────────────────────────────────────────

async function openPaymentModal(custId) {
    let bal = 0;
    let name = '';
    try {
        const fresh = await window.apiRequest(`/api/customers/${custId}`);
        bal = parseFloat(fresh.balance || 0);
        name = fresh.name || '';
    } catch (e) {
        const cached = window.creditCustomers.find(c => c.id === custId);
        if (!cached) return;
        bal = parseFloat(cached.balance || 0);
        name = cached.name;
    }

    document.getElementById('payCustName').innerText = name;
    document.getElementById('payOutstanding').innerText = formatCurrency(bal);
    document.getElementById('payOutstanding').dataset.raw = bal;
    document.getElementById('payCustId').value = custId;
    document.getElementById('payAmount').value = '';
    document.getElementById('payAmount').placeholder = `Max ${formatCurrency(bal)}`;
    openModal('paymentModal');
}

function processPayment() {
    const custId = document.getElementById('payCustId').value;
    const rawInput = document.getElementById('payAmount').value.trim();
    const outstanding = parseFloat(document.getElementById('payOutstanding').dataset.raw || 0);
    const notes = document.getElementById('payNotes')?.value.trim() || '';

    if (!/^\d+(\.\d{1,2})?$/.test(rawInput)) {
        alert('Enter a valid amount (up to 2 decimal places)');
        return;
    }
    const amount = Math.round(parseFloat(rawInput) * 100) / 100;
    if (amount <= 0) {
        alert('Enter a valid payment amount');
        return;
    }
    if (amount > outstanding + 0.01) {
        alert(`Amount exceeds outstanding balance of ${formatCurrency(outstanding)}`);
        return;
    }

    const payload = { amount };
    if (notes) payload.notes = notes;

    window.apiRequest(`/api/customers/${custId}/pay`, {
        method: 'POST',
        body: JSON.stringify(payload)
    }).then(data => {
        closeModal('paymentModal');
        document.getElementById('payAmount').value = '';
        if (document.getElementById('payNotes')) document.getElementById('payNotes').value = '';
        loadCreditPage();
        if (data.data) {
            printPaymentReceipt(data.data);
        }
    }).catch(err => {
        alert('Payment failed: ' + err.message);
    });
}

function printPaymentReceipt(data) {
    if (!data || !data.payment) return;

    const payment = data.payment;
    const customer = data.customer || {};
    const ledger = data.ledger || {};
    const dateStr = new Date().toLocaleString('en-IN');

    let itemsHtml = '';
    const items = data.items || [];
    if (items.length > 0) {
        let invoiceMap = {};
        items.forEach(it => {
            if (!invoiceMap[it.invoice_number]) invoiceMap[it.invoice_number] = [];
            invoiceMap[it.invoice_number].push(it);
        });

        itemsHtml = '<hr><div style="font-size:10px;font-weight:bold;margin:4px 0;">Items on Credit</div><table style="width:100%;border-collapse:collapse;font-size:9px;">';
        itemsHtml += '<tr style="border-top:1px dashed #000;border-bottom:1px dashed #000;"><th style="text-align:left;padding:2px 0;">Item</th><th style="text-align:right;padding:2px 0;">Qty</th><th style="text-align:right;padding:2px 0;">Rate</th><th style="text-align:right;padding:2px 0;">Amt</th></tr>';
        items.forEach(it => {
            itemsHtml += `<tr><td style="padding:1px 0;">${escHtml(it.product_name)}</td><td style="text-align:right;padding:1px 0;">${it.quantity}</td><td style="text-align:right;padding:1px 0;">${formatCurrency(it.unit_price)}</td><td style="text-align:right;padding:1px 0;">${formatCurrency(it.line_total)}</td></tr>`;
        });
        itemsHtml += '</table>';
    }

    const html = `
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Receipt — ${payment.receipt_number}</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{background:#ececec;display:flex;justify-content:center;padding:30px}
.bill{width:76mm;background:#fff;padding:8px 6px;font-family:"Courier New",monospace;color:#000;font-size:11px;line-height:1.3;border-left:4px solid #27ae60;border-right:4px solid #27ae60}
.logo{text-align:center;margin-bottom:4px}
.logo h1{font-size:28px;letter-spacing:1px;font-weight:bold}
.shop{text-align:center;line-height:16px;font-size:11px}
hr{border:none;border-top:1px dashed #000;margin:4px 0}
.fin-row{display:flex;justify-content:space-between;font-size:10px;padding:1px 0}
.fin-row-big{display:flex;justify-content:space-between;font-size:14px;font-weight:bold;margin:4px 0}
.barcode-text{text-align:center;margin-top:10px;letter-spacing:2px;font-size:10px}
.barcode{height:40px;margin:6px auto;width:85%;background:repeating-linear-gradient(to right,#000 0px,#000 2px,#fff 2px,#fff 4px,#000 4px,#000 5px,#fff 5px,#fff 7px)}
.footer{text-align:center;margin-top:6px;line-height:16px;font-size:10px}
@media print{body{background:#fff;padding:0}.bill{box-shadow:none}.no-print{display:none!important}}
</style>
</head>
<body>
<div class="bill">
    <div class="logo">
        <h1>PUDEERA FASHION SHOP</h1>
    </div>
    <div class="shop">
        New Bus Stand, Valliyoor - 627 117<br>
        Ph : 9384261577
    </div>
    <hr>
    <div style="display:flex;justify-content:space-between;margin:4px 0;font-size:10px;">
        <span>Receipt No : ${payment.receipt_number}</span>
        <span>Date : ${dateStr}</span>
    </div>
    <hr>
    <div style="padding:4px 0;">
        <div style="font-size:11px;"><strong>Customer:</strong> ${escHtml(customer.name || 'N/A')}</div>
        <div style="font-size:11px;"><strong>Type:</strong> CREDIT BALANCE PAYMENT</div>
    </div>
    ${itemsHtml}
    <hr>
    <div class="fin-row-big">
        <span>AMOUNT RECEIVED</span>
        <span>${formatCurrency(payment.amount)}</span>
    </div>
    <hr>
    <div class="fin-row">
        <span>Balance Before</span>
        <span>${formatCurrency(ledger.balance_before || 0)}</span>
    </div>
    <div class="fin-row">
        <span>Balance After</span>
        <span>${formatCurrency(ledger.balance_after || 0)}</span>
    </div>
    ${ledger.balance_after <= 0 ? '<hr><div style="text-align:center;font-weight:bold;font-size:14px;margin:4px 0;">*** ALL DUES CLEARED ***</div>' : ''}
    <hr>
    <div class="barcode-text">
        ${payment.receipt_number}
    </div>
    <div class="barcode"></div>
    <div class="footer">
        Thank you for your payment!<br>
        Have a great day!
    </div>
</div>
<div class="no-print" style="position:fixed;bottom:20px;right:20px;z-index:999;">
    <button onclick="window.print()" style="padding:10px 24px;background:#1a3a5a;color:#fff;border:none;border-radius:6px;font-size:14px;cursor:pointer;">
        🖨️ Print
    </button>
</div>
<script>
setTimeout(function(){window.print()},300);
<\/script>
</body>
</html>`;

    const printWindow = window.open('', '_blank', 'width=400,height=600');
    if (!printWindow) {
        alert('Popup blocked — please allow popups for this site to print receipts. Payment was recorded successfully.');
        return;
    }
    printWindow.document.write(html);
    printWindow.document.close();
}

function viewPaymentReceipt(ledgerId) {
    const customer = window.creditCustomers.find(c => {
        const entries = window.creditLedgerCache[c.id] || [];
        return entries.some(e => e.id === ledgerId);
    });
    if (!customer) return;

    const entries = window.creditLedgerCache[customer.id] || [];
    const entry = entries.find(e => e.id === ledgerId);
    if (!entry) return;

    const dateStr = entry.created_at
        ? new Date(entry.created_at).toLocaleString('en-IN')
        : '';

    const html = `
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payment Receipt</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{background:#ececec;display:flex;justify-content:center;padding:30px}
.bill{width:76mm;background:#fff;padding:8px 6px;font-family:"Courier New",monospace;color:#000;font-size:11px;line-height:1.3;border-left:4px solid #27ae60;border-right:4px solid #27ae60}
.logo{text-align:center;margin-bottom:4px}
.logo h1{font-size:28px;letter-spacing:1px;font-weight:bold}
.shop{text-align:center;line-height:16px;font-size:11px}
hr{border:none;border-top:1px dashed #000;margin:4px 0}
.fin-row{display:flex;justify-content:space-between;font-size:10px;padding:1px 0}
.fin-row-big{display:flex;justify-content:space-between;font-size:18px;font-weight:bold;margin:4px 0}
.barcode-text{text-align:center;margin-top:10px;letter-spacing:2px;font-size:10px}
.barcode{height:40px;margin:6px auto;width:85%;background:repeating-linear-gradient(to right,#000 0px,#000 2px,#fff 2px,#fff 4px,#000 4px,#000 5px,#fff 5px,#fff 7px)}
.footer{text-align:center;margin-top:6px;line-height:16px;font-size:10px}
@media print{body{background:#fff;padding:0}.bill{box-shadow:none}.no-print{display:none!important}}
</style>
</head>
<body>
<div class="bill">
    <div class="logo">
        <h1>PUDEERA FASHION SHOP</h1>
    </div>
    <div class="shop">
        New Bus Stand, Valliyoor - 627 117<br>
        Ph : 9384261577
    </div>
    <hr>
    <div style="display:flex;justify-content:space-between;margin:4px 0;font-size:10px;">
        <span>Ledger Ref</span>
        <span>${dateStr}</span>
    </div>
    <hr>
    <div style="padding:4px 0;">
        <div style="font-size:11px;"><strong>Customer:</strong> ${escHtml(customer.name)}</div>
        <div style="font-size:11px;"><strong>Type:</strong> ${entry.entry_type === 'payment' ? 'CREDIT BALANCE PAYMENT' : entry.notes || 'Transaction'}</div>
    </div>
    <hr>
    <div class="fin-row-big">
        <span>AMOUNT</span>
        <span>${formatCurrency(entry.credit)}</span>
    </div>
    <hr>
    <div class="fin-row">
        <span>Outstanding Balance</span>
        <span>${formatCurrency(entry.balance)}</span>
    </div>
    ${entry.balance <= 0 ? '<hr><div style="text-align:center;font-weight:bold;font-size:14px;margin:4px 0;">*** ALL DUES CLEARED ***</div>' : ''}
    <hr>
    <div class="footer">
        Thank you for your payment!<br>
        Have a great day!
    </div>
</div>
<div class="no-print" style="position:fixed;bottom:20px;right:20px;z-index:999;">
    <button onclick="window.print()" style="padding:10px 24px;background:#1a3a5a;color:#fff;border:none;border-radius:6px;font-size:14px;cursor:pointer;">
        🖨️ Print
    </button>
</div>
<script>
setTimeout(function(){window.print()},300);
<\/script>
</body>
</html>`;

    const printWindow = window.open('', '_blank', 'width=400,height=600');
    if (!printWindow) {
        alert('Popup blocked — please allow popups for this site to print receipts.');
        return;
    }
    printWindow.document.write(html);
    printWindow.document.close();
}

function printBillReceipt() {
    const content = document.getElementById('billReceiptContent');
    if (!content) return;

    const printWindow = window.open('', '_blank', 'width=400,height=600');
    printWindow.document.write(`
      <html><head><title>Receipt</title></head>
      <body style="font-family: monospace; white-space: pre-wrap; padding: 20px;">
${content.innerText}
      </body>
      <script>window.print(); window.onafterprint = function(){ window.close(); }<\/script>
      </html>
    `);
    printWindow.document.close();
}

// ── Helpers ───────────────────────────────────────────────

function escHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

document.addEventListener('DOMContentLoaded', function() {
    const observer = new MutationObserver(() => {
        const section = document.getElementById('credit_kadan');
        if (section && section.classList.contains('active')) {
            loadCreditPage();
        }
    });
    const sidebar = document.querySelector('.sidebar');
    if (sidebar) {
        observer.observe(sidebar, { attributes: true, subtree: true, attributeFilter: ['class'] });
    }
});
