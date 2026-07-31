<?php
if (!isset($invoice)) {
    http_response_code(500);
    echo 'Missing invoice data';
    return;
}
$shop = [
    'name' => 'Pudeera Fashion Shop',
    'address' => "New Bus Stand, Valliyoor - 627 117",
    'gst' => '33ABBFA1628A1ZC',
    'phone' => '9384261577'
];
$items = $invoice->items ?? [];
$date = !empty($invoice->billedAt) ? date('d/m/y', strtotime($invoice->billedAt)) : '';
$cgst = $invoice->totalGst / 2;
$sgst = $invoice->totalGst / 2;
$netSale = $invoice->subtotal - $invoice->discountAmount;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bill — <?= htmlspecialchars($invoice->invoiceNumber) ?></title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{background:#ececec;display:flex;justify-content:center;padding:30px}
.bill{width:76mm;background:#fff;padding:8px 6px;font-family:"Courier New",monospace;color:#000;font-size:11px;line-height:1.3;border-left:4px solid #c0392b;border-right:4px solid #c0392b}
.logo{text-align:center;margin-bottom:4px}
.logo h1{font-size:28px;letter-spacing:1px;font-weight:bold}
.shop{text-align:center;line-height:16px;font-size:11px}
.bill-info{display:flex;justify-content:space-between;margin:4px 0;font-size:10px}
table{width:100%;border-collapse:collapse;font-size:10px}
th{text-align:left;padding:2px 0;font-weight:bold;border-top:1px dashed #000;border-bottom:1px dashed #000}
td{vertical-align:top;padding:2px 0}
td:last-child,th:last-child{text-align:right}
td:nth-child(3),th:nth-child(3){text-align:right}
td:nth-child(4),th:nth-child(4){text-align:right}
td:nth-child(6),th:nth-child(6){text-align:right}
td:nth-child(7),th:nth-child(7){text-align:right}
hr{border:none;border-top:1px dashed #000;margin:4px 0}
.fin-row{display:flex;justify-content:space-between;font-size:10px;padding:1px 0}
.fin-row-big{display:flex;justify-content:space-between;font-size:18px;font-weight:bold;margin:4px 0}
.barcode-text{text-align:center;margin-top:10px;letter-spacing:2px;font-size:10px}
.barcode{height:40px;margin:6px auto;width:85%;background:repeating-linear-gradient(to right,#000 0px,#000 2px,#fff 2px,#fff 4px,#000 4px,#000 5px,#fff 5px,#fff 7px)}
.footer{text-align:center;margin-top:6px;line-height:16px;font-size:10px}

/* ── Modern Return Component (no-print) ── */
.no-print{position:fixed;bottom:20px;right:20px;z-index:999;display:flex;flex-direction:column;gap:12px;align-items:stretch;width:380px;font-family:Inter,system-ui,-apple-system,sans-serif}
.no-print>button{width:100%}
.btn-return-toggle{padding:10px 16px;background:#6366f1;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;transition:background-color .2s}
.btn-return-toggle:hover{background:#4f46e5}
.btn-print{padding:12px 24px;background:#6366f1;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;transition:background-color .2s}
.btn-print:hover{background:#4f46e5}
#receiptReturnSection{display:none;background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;padding:16px;max-height:70vh;overflow-y:auto;font-size:13px;color:#1f2937;box-shadow:0 10px 15px -3px rgba(0,0,0,0.1)}
#receiptReturnSection .rr-header{font-size:14px;font-weight:700;color:#111827;margin-bottom:12px}
.rr-list-header{display:grid;grid-template-columns:2fr 80px 100px;gap:12px;padding:6px 12px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#6b7280;background:#f3f4f6;border-radius:8px 8px 0 0;position:sticky;top:0;z-index:1}
#receiptReturnItems{display:flex;flex-direction:column;gap:8px}
.receipt-ret-row{display:grid;grid-template-columns:2fr 80px 100px;gap:12px;align-items:center;padding:12px;background:#f8fafc;border:1px solid #eef2f7;border-radius:10px}
.receipt-ret-row .rr-info{min-width:0}
.receipt-ret-row .rr-name{font-weight:600;font-size:12px;color:#111827;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.receipt-ret-row .rr-meta{font-size:11px;color:#6b7280}
.receipt-ret-row input{width:100%;border:1px solid #d1d5db;border-radius:8px;background:#fff;color:#111827;font-size:12px;text-align:center;padding:6px 8px;box-sizing:border-box}
.receipt-ret-row input:focus{outline:none;border-color:#6366f1;box-shadow:0 0 0 2px rgba(99,102,241,.15)}
.rr-empty{color:#6b7280;text-align:center;padding:16px}
.rr-reason-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(92px,1fr));gap:6px;margin-top:12px}
.badge-pill{display:inline-block;padding:.35em .65em;font-size:.75em;font-weight:700;border-radius:50rem;background-color:#f8f9fa;color:#212529;border:1px solid #dee2e6;cursor:pointer;transition:background-color .2s,border-color .2s;white-space:nowrap}
.badge-pill:hover{background-color:#e2e6ea;border-color:#ced4da}
#receiptRetReason{width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:8px;background:#fff;color:#111827;font-size:12px;margin-top:12px;box-sizing:border-box}
#receiptRetReason:focus{outline:none;border-color:#6366f1;box-shadow:0 0 0 2px rgba(99,102,241,.15)}
.btn-process-return{width:100%;margin-top:10px;padding:10px;background:#ef4444;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;transition:background-color .2s}
.btn-process-return:hover{background:#dc2626}
.btn-process-return:disabled{background:#fca5a5;cursor:not-allowed}

@media print{body{background:#fff;padding:0}.bill{box-shadow:none}.no-print{display:none!important}}
</style>
</head>
<body>

<div class="bill">

    <!-- Logo -->
    <div class="logo">
        <h1><?= htmlspecialchars($shop['name']) ?></h1>
    </div>

    <div class="shop">
        <?= nl2br(htmlspecialchars($shop['address'])) ?><br>
        Ph : <?= htmlspecialchars($shop['phone']) ?>
    </div>

    <hr>

    <div class="bill-info">
        <span>Bill No : <?= htmlspecialchars($invoice->invoiceNumber) ?></span>
        <span>Date : <?= htmlspecialchars($date) ?></span>
    </div>

    <hr>

    <table>
        <thead>
        <tr>
            <th style="width:22px;">SNo</th>
            <th>Particulars</th>
            <th style="width:40px;">Rate</th>
            <th style="width:28px;">Disc</th>
            <th style="width:18px;">Qty</th>
            <th style="width:28px;">GST%</th>
            <th style="width:48px;">Amount</th>
        </tr>
        </thead>
        <tbody>
<?php $idx = 0; foreach ($items as $item): $idx++; ?>
        <tr>
            <td><?= $idx ?></td>
            <td>
                <?= htmlspecialchars($item->productNameSnapshot ?: 'Item') ?>
                <?php if (!empty($item->hsnCodeSnapshot)): ?><br><span style="font-size:9px;"><?= htmlspecialchars($item->hsnCodeSnapshot) ?></span><?php endif; ?>
            </td>
            <td><?= number_format($item->unitPrice, 2) ?></td>
            <td><?= $item->discountAmount ? number_format($item->discountAmount, 0) : '' ?></td>
            <td><?= number_format($item->quantity, 0) ?></td>
            <td><?= $item->gstRateSnapshot ? number_format($item->gstRateSnapshot, 0).'%' : '' ?></td>
            <td><?= number_format($item->quantity * $item->unitPrice, 2) ?></td>
        </tr>
<?php endforeach; ?>
<?php if (empty($items)): ?>
        <tr><td colspan="7" style="text-align:center;padding:6px;">No items</td></tr>
<?php endif; ?>
        </tbody>
    </table>

    <hr>

    <div class="fin-row">
        <span>Sub Total</span>
        <span><?= number_format($invoice->subtotal, 2) ?></span>
    </div>

<?php if ($invoice->discountAmount > 0): ?>
    <div class="fin-row">
        <span>Discount</span>
        <span>-<?= number_format($invoice->discountAmount, 2) ?></span>
    </div>
<?php endif; ?>

    <hr>

    <div class="fin-row">
        <span>Sale</span>
        <span><?= number_format($netSale, 2) ?></span>
    </div>

<?php if ($invoice->totalGst > 0): ?>
<?php
$uniqueRates = array_unique(array_map(fn($i) => $i->gstRateSnapshot, $items));
$gstRateDisplay = count($uniqueRates) === 1 && $uniqueRates[0] > 0 ? $uniqueRates[0] / 2 : null;
$cgstLabel = 'CGST' . ($gstRateDisplay !== null ? ' @ ' . number_format($gstRateDisplay, 1) . '%' : '');
$sgstLabel = 'SGST' . ($gstRateDisplay !== null ? ' @ ' . number_format($gstRateDisplay, 1) . '%' : '');
?>
    <div class="fin-row">
        <span><?= $cgstLabel ?></span>
        <span><?= number_format($cgst, 2) ?></span>
    </div>
    <div class="fin-row">
        <span><?= $sgstLabel ?></span>
        <span><?= number_format($sgst, 2) ?></span>
    </div>
<?php endif; ?>

<?php if ($invoice->roundOff != 0): ?>
    <div class="fin-row">
        <span>Round Off</span>
        <span><?= number_format($invoice->roundOff, 2) ?></span>
    </div>
<?php endif; ?>

    <hr>

    <div class="fin-row-big">
        <span>Grand Total</span>
        <span><?= number_format($invoice->grandTotal, 2) ?></span>
    </div>

    <div class="fin-row">
        <span>Amount Paid</span>
        <span><?= number_format($invoice->amountPaid, 2) ?></span>
    </div>
<?php if ($invoice->balanceDue > 0): ?>
    <div class="fin-row">
        <span>Balance Due</span>
        <span style="color:#c00;"><?= number_format($invoice->balanceDue, 2) ?></span>
    </div>
<?php endif; ?>

    <hr>

    <div class="barcode-text">
        <?= htmlspecialchars($invoice->invoiceNumber) ?>
    </div>

    <div class="barcode"></div>

    <div class="footer">
        Goods once sold will not be taken back.<br>
        Bill exchange within 7 days only.<br>
        Thank you for your business!
    </div>

</div>

<div class="no-print">
    <button onclick="toggleReturnSection()" class="btn-return-toggle">
        ↩️ Return Items
    </button>
    <div id="receiptReturnSection">
        <div class="rr-header">Return Items</div>
        <div class="rr-list-header">
            <span>Item</span>
            <span style="text-align:center;">Qty</span>
            <span style="text-align:right;">Refund</span>
        </div>
        <div id="receiptReturnItems">
            <?php $anyReturnable = false; ?>
            <?php foreach ($items as $item):
                $ret = $item->quantity - ($item->alreadyReturned ?? 0);
                if ($ret <= 0) continue;
                $anyReturnable = true;
            ?>
            <div class="receipt-ret-row" data-item-id="<?= $item->id ?>" data-unit-price="<?= $item->unitPrice ?>" data-max="<?= $ret ?>">
                <div class="rr-info">
                    <div class="rr-name"><?= htmlspecialchars($item->productNameSnapshot ?: 'Item') ?></div>
                    <div class="rr-meta">Sold: <?= number_format($item->quantity, 0) ?> × ₹<?= number_format($item->unitPrice, 2) ?></div>
                </div>
                <input type="number" class="rr-qty" value="0" min="0" max="<?= $ret ?>" placeholder="Qty">
                <input type="number" class="rr-refund" value="0" min="0" placeholder="₹">
            </div>
            <?php endforeach; ?>
            <?php if (!$anyReturnable): ?>
            <div class="rr-empty">No returnable items</div>
            <?php endif; ?>
        </div>
        <div class="rr-reason-grid">
          <button type="button" class="badge-pill" onclick="document.getElementById('receiptRetReason').value='Wrong size'">Wrong size</button>
          <button type="button" class="badge-pill" onclick="document.getElementById('receiptRetReason').value='Damaged'">Damaged</button>
          <button type="button" class="badge-pill" onclick="document.getElementById('receiptRetReason').value='Quality issue'">Quality</button>
          <button type="button" class="badge-pill" onclick="document.getElementById('receiptRetReason').value='Wrong item'">Wrong item</button>
          <button type="button" class="badge-pill" onclick="document.getElementById('receiptRetReason').value='Changed mind'">Changed mind</button>
        </div>
        <input type="text" id="receiptRetReason" placeholder="Reason (min 3 chars)">
        <button id="processReturnBtn" onclick="submitReceiptReturn('<?= $invoice->id ?>')" class="btn-process-return">Process Return</button>
    </div>
    <button onclick="window.print()" class="btn-print">
        🖨️ Print
    </button>
</div>

<script>
setTimeout(function(){window.print()},300);

document.getElementById('receiptReturnItems').addEventListener('input', function(e) {
    var row = e.target.closest('.receipt-ret-row');
    if (!row) return;
    var unitPrice = parseFloat(row.dataset.unitPrice) || 0;
    var maxQty = parseFloat(row.dataset.max) || 0;

    if (e.target.classList.contains('rr-qty')) {
        var qty = parseFloat(e.target.value) || 0;
        if (qty > maxQty) { qty = maxQty; e.target.value = maxQty; }
        row.querySelector('.rr-refund').value = (qty * unitPrice).toFixed(2);
    } else if (e.target.classList.contains('rr-refund')) {
        var refund = parseFloat(e.target.value) || 0;
        var qty = unitPrice > 0 ? Math.round(refund / unitPrice) : 0;
        if (qty > maxQty) qty = maxQty;
        row.querySelector('.rr-qty').value = qty;
    }
});

function toggleReturnSection() {
    var el = document.getElementById('receiptReturnSection');
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
}

function submitReceiptReturn(invoiceId) {
    var reason = document.getElementById('receiptRetReason').value.trim();
    if (!reason || reason.length < 3) {
        if (window.notify) window.notify.warning('Please enter a return reason (min 3 characters)');
        else if (window.showToast) window.showToast('Please enter a return reason (min 3 characters)', 'warn');
        else alert('Please enter a return reason (min 3 characters)');
        return;
    }

    var rows = document.querySelectorAll('#receiptReturnItems .receipt-ret-row');
    var items = [];
    var token = (new URLSearchParams(window.location.search)).get('token') || localStorage.getItem('auth_token');

    rows.forEach(function(row) {
        var qty = parseFloat(row.querySelector('.rr-qty').value) || 0;
        if (qty <= 0) return;
        items.push({
            invoice_item_id: row.dataset.itemId,
            qty_returned: qty,
            refund_amount: parseFloat(row.querySelector('.rr-refund').value) || 0
        });
    });

    if (items.length === 0) {
        if (window.notify) window.notify.warning('No items selected');
        else if (window.showToast) window.showToast('No items selected', 'warn');
        else alert('No items selected');
        return;
    }

    var btn = document.getElementById('processReturnBtn');
    if (btn) { btn.disabled = true; btn.textContent = 'Processing...'; }

    fetch('/api/invoices/' + invoiceId + '/return', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + (token || '') },
        body: JSON.stringify({ items: items, reason: reason })
    }).then(function(r) {
        if (!r.ok) return r.json().then(function(d) { throw new Error(d.error || 'Return failed'); });
        return r.json();
    }).then(function(data) {
        if (window.notify) window.notify.success('Return processed successfully');
        if (data.warning) {
            if (window.notify) window.notify.warning(data.warning);
            else if (window.showToast) window.showToast(data.warning, 'warn');
        }
        if (data.stock_warning) {
            if (window.notify) window.notify.warning('⚠ Stock note: ' + data.stock_warning + ' — please adjust inventory manually.');
            else if (window.showToast) window.showToast('⚠ Stock note: ' + data.stock_warning + ' — please adjust inventory manually.', 'warn');
        }
        window.location.href = window.location.pathname + window.location.search + '&_=' + Date.now();
    }).catch(function(err) {
        if (window.notify) window.notify.error(err.message || 'Return failed');
        else if (window.showToast) window.showToast(err.message || 'Return failed', 'danger');
        else alert(err.message || 'Return failed');
        if (btn) { btn.disabled = false; btn.textContent = 'Process Return'; }
    });
}
</script>

</body>
</html>
