/**
 * product.service.js — Product Master & Category API service
 */

import { apiRequest } from '../core/api.js';

export async function fetchProductsApi(params = {}) {
  const query = new URLSearchParams(params).toString();
  return apiRequest(`/api/products?${query}`);
}

export async function createProductApi(productData) {
  return apiRequest('/api/products', {
    method: 'POST',
    body: JSON.stringify(productData),
  });
}

export async function updateProductApi(id, productData) {
  return apiRequest(`/api/products/${id}`, {
    method: 'PUT',
    body: JSON.stringify(productData),
  });
}

export async function deleteProductApi(id) {
  return apiRequest(`/api/products/${id}`, {
    method: 'DELETE',
  });
}

export async function fetchCategoriesApi() {
  return apiRequest('/api/categories');
}

export async function fetchProductHistoryApi(productId) {
  return apiRequest(`/api/products/${productId}/history`);
}
