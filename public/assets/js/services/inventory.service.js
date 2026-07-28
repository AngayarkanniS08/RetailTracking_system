/**
 * inventory.service.js — Inventory stock management API service
 */

import { apiRequest } from '../core/api.js';

export async function fetchInventoryItemsApi(params = {}) {
  const query = new URLSearchParams(params).toString();
  return apiRequest(`/api/inventory?${query}`);
}

export async function updateStockApi(productId, adjustmentData) {
  return apiRequest(`/api/inventory/${productId}/stock`, {
    method: 'PATCH',
    body: JSON.stringify(adjustmentData),
  });
}

export async function fetchStockIntelligenceApi(productId) {
  return apiRequest(`/api/inventory/${productId}/intelligence`);
}
