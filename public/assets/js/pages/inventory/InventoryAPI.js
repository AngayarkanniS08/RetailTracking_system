/**
 * InventoryAPI.js — REST client for the Inventory module.
 *
 * Thin HTTP layer. Every endpoint maps 1:1 to the backend contract:
 *   GET  /api/inventory                  list (page/limit/search/category/subcategory)
 *   GET  /api/inventory/{id}             details + value + restock recommendation
 *   POST /api/inventory                  create batch
 *   PUT  /api/inventory/{id}             edit batch
 *   POST /api/inventory/{id}/restock     additive restock { add_quantity }
 *   GET  /api/inventory/categories       category filter list
 *   GET  /api/inventory/subcategories    subcategory filter list
 *   POST /api/inventory/alerts           configure low-stock alert
 *
 * No business logic lives here — the backend owns all rules.
 */

import { apiRequest } from '../../core/api.js';

function qs(params = {}) {
  const query = new URLSearchParams();
  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') query.set(key, value);
  });
  const str = query.toString();
  return str ? `?${str}` : '';
}

export async function fetchInventoryApi(params = {}) {
  return apiRequest(`/api/inventory${qs(params)}`);
}

export async function fetchInventoryDetailsApi(id) {
  return apiRequest(`/api/inventory/${id}`);
}

export async function createInventoryApi(payload) {
  return apiRequest('/api/inventory', { method: 'POST', body: JSON.stringify(payload) });
}

export async function updateInventoryApi(id, payload) {
  return apiRequest(`/api/inventory/${id}`, { method: 'PUT', body: JSON.stringify(payload) });
}

export async function restockInventoryApi(id, payload) {
  return apiRequest(`/api/inventory/${id}/restock`, { method: 'POST', body: JSON.stringify(payload) });
}

export async function fetchInventoryCategoriesApi() {
  return apiRequest('/api/inventory/categories');
}

export async function fetchInventorySubcategoriesApi(categoryId) {
  return apiRequest(`/api/inventory/subcategories${categoryId ? `?category_id=${categoryId}` : ''}`);
}

export async function fetchProductsApi() {
  return apiRequest('/api/products');
}

export async function saveInventoryAlertApi(payload) {
  return apiRequest('/api/inventory/alerts', { method: 'POST', body: JSON.stringify(payload) });
}
