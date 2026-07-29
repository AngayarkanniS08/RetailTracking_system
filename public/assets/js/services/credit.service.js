/**
 * credit.service.js — Customer credit & dues API service
 */

import { apiRequest } from '../core/api.js';

export async function fetchCustomerCreditsApi(page = 1, limit = 20, search = '') {
  const params = new URLSearchParams({ page, limit });
  if (search) params.set('search', search);
  return apiRequest(`/api/customers?${params}`);
}

export async function recordCreditPaymentApi(customerId, paymentData) {
  return apiRequest(`/api/customers/${customerId}/pay`, {
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

export async function recordCreditSaleApi(customerId, data) {
  return apiRequest(`/api/customers/${customerId}/credit-sale`, {
    method: 'POST',
    body: JSON.stringify(data),
  });
}

export async function recordCreditReturnApi(customerId, data) {
  return apiRequest(`/api/customers/${customerId}/credit-return`, {
    method: 'POST',
    body: JSON.stringify(data),
  });
}

export async function checkCreditLimitApi(customerId, amount) {
  return apiRequest(`/api/customers/${customerId}/credit-check?amount=${amount}`);
}

export async function fetchLedgerEntriesApi(customerId, { limit = 50, offset = 0, type } = {}) {
  const params = new URLSearchParams({ limit, offset });
  if (type) params.set('type', type);
  return apiRequest(`/api/customers/${customerId}/ledger/entries?${params}`);
}

export async function fetchLedgerSummaryApi(customerId) {
  return apiRequest(`/api/customers/${customerId}/ledger/summary`);
}

export async function fetchLedgerBalanceApi(customerId) {
  return apiRequest(`/api/customers/${customerId}/ledger/balance`);
}
