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

export async function fetchVendorHistoryApi(vendorId) {
  return apiRequest(`/api/vendors/${vendorId}/history`);
}

export async function saveVendorPurchaseApi(purchaseData) {
  return apiRequest('/api/vendors/purchases', {
    method: 'POST',
    body: JSON.stringify(purchaseData),
  });
}
