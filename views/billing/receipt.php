<?php
if (!isset($invoice) || !isset($shop)) {
    http_response_code(500);
    echo 'Missing invoice data';
    return;
}
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
<?php $gstRateDisplay = isset($items[0]) ? $items[0]->gstRateSnapshot / 2 : 0; ?>
    <div class="fin-row">
        <span>CGST @ <?= number_format($gstRateDisplay, 1) ?>%</span>
        <span><?= number_format($cgst, 2) ?></span>
    </div>
    <div class="fin-row">
        <span>SGST @ <?= number_format($gstRateDisplay, 1) ?>%</span>
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

<div class="no-print" style="position:fixed;bottom:20px;right:20px;z-index:999;">
    <button onclick="window.print()" style="padding:10px 24px;background:#1a3a5a;color:#fff;border:none;border-radius:6px;font-size:14px;cursor:pointer;">
        🖨️ Print
    </button>
</div>

<script>
setTimeout(function(){window.print()},300);
</script>

</body>
</html>
