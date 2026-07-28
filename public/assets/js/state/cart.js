/**
 * cart.js — Shopping Cart reactive state manager for POS
 */

import { emit, Events } from '../core/events.js';

let _cartItems = [];
let _discount = 0;
let _gstEnabled = false;

export function getCart() {
  return {
    items: [..._cartItems],
    discount: _discount,
    gstEnabled: _gstEnabled,
    subtotal: calculateSubtotal(),
    gstAmount: calculateGst(),
    total: calculateTotal(),
  };
}

export function addToCart(product, quantity = 1, priceMode = 'wholesale') {
  const price = priceMode === 'retail' ? product.retail_price : product.wholesale_price;
  const existing = _cartItems.find((item) => item.id === product.id && item.priceMode === priceMode);

  if (existing) {
    existing.quantity += quantity;
  } else {
    _cartItems.push({
      id: product.id,
      name: product.name,
      price: parseFloat(price),
      quantity,
      priceMode,
      unit: product.unit || 'pcs',
    });
  }

  notify();
}

export function updateCartQuantity(index, newQty) {
  if (index >= 0 && index < _cartItems.length) {
    if (newQty <= 0) {
      _cartItems.splice(index, 1);
    } else {
      _cartItems[index].quantity = newQty;
    }
    notify();
  }
}

export function removeFromCart(index) {
  if (index >= 0 && index < _cartItems.length) {
    _cartItems.splice(index, 1);
    notify();
  }
}

export function clearCart() {
  _cartItems = [];
  _discount = 0;
  notify();
}

export function setDiscount(amount) {
  _discount = Math.max(0, parseFloat(amount) || 0);
  notify();
}

export function setGstEnabled(enabled) {
  _gstEnabled = Boolean(enabled);
  notify();
}

function calculateSubtotal() {
  return _cartItems.reduce((acc, item) => acc + item.price * item.quantity, 0);
}

function calculateGst() {
  if (!_gstEnabled) return 0;
  return calculateSubtotal() * 0.18; // 18% standard GST
}

function calculateTotal() {
  const sub = calculateSubtotal();
  const gst = calculateGst();
  return Math.max(0, sub + gst - _discount);
}

function notify() {
  emit(Events.CART_UPDATED, getCart());
}
