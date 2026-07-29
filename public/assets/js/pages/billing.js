/**
 * billing.js — POS Billing page controller module
 */

import { formatCurrency } from '../utils/format.js';

export function initBillingPage() {
  calculateCart();
}

export function calculateCart() {
  let grossSubtotal = 0;
  let totalGst = 0;
  let totalItemDiscount = 0;

  const isGst = document.getElementById('enableGstToggle') ? document.getElementById('enableGstToggle').checked : true;
  const billDiscount = parseFloat(document.getElementById('cartDiscountInput') ? document.getElementById('cartDiscountInput').value : 0) || 0;

  // Calculate grid row items if present
  const rows = document.querySelectorAll('#billingGrid tbody tr');
  rows.forEach(tr => {
    const cells = tr.querySelectorAll('td');
    if (cells.length >= 8) {
      const price = parseFloat(cells[2].innerText.replace(/[^0-9.]/g, '')) || 0;
      const discount = parseFloat(cells[3].innerText.replace(/[^0-9.]/g, '')) || 0;
      const qty = parseFloat(cells[5].innerText.replace(/[^0-9.]/g, '')) || 0;
      const gstRate = parseFloat(cells[6].innerText.replace(/[^0-9.]/g, '')) || 0;

      if (qty > 0 && price > 0) {
        const lineSubtotal = (price * qty) - discount;
        const lineGst = isGst ? (lineSubtotal * (gstRate / 100)) : 0;
        const lineTotal = lineSubtotal + lineGst;
        cells[7].innerText = formatCurrency(lineTotal);

        grossSubtotal += price * qty;
        totalItemDiscount += discount;
        totalGst += lineGst;
      }
    }
  });

  const netSubtotal = grossSubtotal - totalItemDiscount;
  const total = Math.max(0, netSubtotal - billDiscount + totalGst);

  const subtotalEl = document.getElementById('cartSubtotal');
  const gstEl = document.getElementById('cartGst');
  const totalEl = document.getElementById('cartTotal');

  if (subtotalEl) subtotalEl.innerText = formatCurrency(netSubtotal);
  if (gstEl) gstEl.innerText = totalGst > 0 ? formatCurrency(totalGst) : "₹0.00";
  if (totalEl) totalEl.innerText = formatCurrency(total);

  const amountPaidEl = document.getElementById('amountPaidInput');
  if (amountPaidEl && (!amountPaidEl.value || amountPaidEl.dataset.autofilled === 'true')) {
    amountPaidEl.value = total > 0 ? total.toFixed(2) : '';
    amountPaidEl.dataset.autofilled = 'true';
  }

  return { grossSubtotal, netSubtotal, gst: totalGst, itemDiscount: totalItemDiscount, discount: billDiscount, total, isGst };
}

window.calculateCart = calculateCart;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', calculateCart);
} else {
  calculateCart();
}
