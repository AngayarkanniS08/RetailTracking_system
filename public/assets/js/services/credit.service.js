/**
 * credit.service.js — Customer credit & dues API service
 */

import { apiRequest } from '../core/api.js';

export async function fetchCustomerCreditsApi() {
  return apiRequest('/api/customers');
}

export async function recordCreditPaymentApi(customerId, paymentData) {
  return apiRequest(`/api/customers/${customerId}/credit-payments`, {
    method: 'POST',
    body: JSON.stringify(paymentData),
  });
}

export async function createCustomerApi(customerData) {
  return apiRequest('/api/customers', {
    method: 'POST',
    body: JSON.stringify(customerData),
  });
}
