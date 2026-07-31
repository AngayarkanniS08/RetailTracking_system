/**
 * vendor.service.js — Vendor & Purchase Order API service
 */

import { apiRequest } from '../core/api.js';

export async function fetchVendorsApi() {
  return apiRequest('/api/vendors');
}

export async function createVendorApi(vendorData) {
  return apiRequest('/api/vendors', {
    method: 'POST',
    body: JSON.stringify(vendorData),
  });
}

export async function fetchVendorHistoryApi(vendorId = null, params = {}) {
  const isValidUuid = typeof vendorId === 'string' && vendorId.trim() !== '' && vendorId !== 'null' && vendorId !== 'undefined';
  const query = new URLSearchParams(params).toString();
  const endpoint = isValidUuid ? `/api/vendors/${vendorId}/history` : '/api/vendors/history/all';
  return apiRequest(query ? `${endpoint}?${query}` : endpoint);
}

export async function saveVendorPurchaseApi(purchaseData) {
  return apiRequest('/api/vendors/purchases', {
    method: 'POST',
    body: JSON.stringify(purchaseData),
  });
}
