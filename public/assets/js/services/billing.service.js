/**
 * billing.service.js — POS Billing & Checkout API service
 */

import { apiRequest } from '../core/api.js';

export async function searchPosProductsApi(query = '', category = '', page = 1) {
  const params = new URLSearchParams({ q: query, category, page });
  return apiRequest(`/api/billing/search-products?${params}`);
}

export async function processCheckoutApi(checkoutData) {
  return apiRequest('/api/billing/checkout', {
    method: 'POST',
    body: JSON.stringify(checkoutData),
  });
}

export async function fetchCustomersApi(query = '') {
  return apiRequest(`/api/customers/search?q=${encodeURIComponent(query)}`);
}
