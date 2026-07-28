/**
 * billing.js — POS Billing page controller module
 */

import { getCart, addToCart, updateCartQuantity, removeFromCart, setDiscount, clearCart } from '../state/cart.js';
import { on, Events } from '../core/events.js';
import { formatCurrency } from '../utils/format.js';
import { showToast } from '../ui/toast.js';

export function initBillingPage() {
  on(Events.CART_UPDATED, renderCart);

  // Bind discount input
  const discountInput = document.getElementById('billing-discount');
  if (discountInput) {
    discountInput.addEventListener('input', (e) => {
      setDiscount(e.target.value);
    });
  }

  // Bind clear cart button
  const clearBtn = document.getElementById('billing-clear-btn');
  if (clearBtn) {
    clearBtn.addEventListener('click', () => {
      clearCart();
      showToast('Cart cleared', 'info');
    });
  }
}

function renderCart(cart) {
  const container = document.getElementById('cart-items-list');
  if (!container) return;

  if (cart.items.length === 0) {
    container.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-2">Cart is empty</td></tr>';
  } else {
    container.innerHTML = cart.items
      .map(
        (item, idx) => `
      <tr>
        <td><span class="cart-name">${item.name}</span></td>
        <td>${formatCurrency(item.price)}</td>
        <td>
          <input type="number" class="qty-input" value="${item.quantity}" min="1" data-idx="${idx}" />
        </td>
        <td>${formatCurrency(item.price * item.quantity)}</td>
      </tr>
    `
      )
      .join('');
  }

  const subtotalEl = document.getElementById('cart-subtotal');
  const totalEl = document.getElementById('cart-total');

  if (subtotalEl) subtotalEl.textContent = formatCurrency(cart.subtotal);
  if (totalEl) totalEl.textContent = formatCurrency(cart.total);
}
