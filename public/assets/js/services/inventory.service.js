/**
 * inventory.service.js — Inventory stock management API service
 */

import { apiRequest } from '../core/api.js';

export async function fetchInventoryItemsApi(params = {}) {
  const query = new URLSearchParams(params).toString();
  const url = query ? `/api/inventory/batches?${query}` : '/api/inventory/batches';
  const res = await apiRequest(url);
  return res.data || res.batches || (Array.isArray(res) ? res : []);
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
