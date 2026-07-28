/**
 * customers.js — Customer Credit (Kadan) page controller
 */

import { fetchCustomerCreditsApi } from '../services/credit.service.js';

let _customers = [];
let _searchQuery = '';
let _searchTimer = null;

export async function initCustomerCredit() {
  const tbody = document.querySelector('#creditTable tbody');
  const emptyState = document.getElementById('creditEmptyState');
  
  if (tbody) {
    tbody.innerHTML = '<tr><td colspan="9" style="text-align:center; padding: 32px; color: var(--muted);">Loading credit accounts...</td></tr>';
  }
  if (emptyState) emptyState.style.display = 'none';

  try {
    const response = await fetchCustomerCreditsApi();
    if (Array.isArray(response)) {
      _customers = response;
    } else if (response && Array.isArray(response.data)) {
      _customers = response.data;
    } else if (response && Array.isArray(response.customers)) {
      _customers = response.customers;
    } else {
      _customers = [];
    }
    renderCreditTable();
  } catch (err) {
    console.error('Failed to load customer credit records:', err);
    _customers = [];
    renderCreditTable();
  }
}

export function renderCreditTable() {
  const tbody = document.querySelector('#creditTable tbody');
  const emptyState = document.getElementById('creditEmptyState');
  const table = document.getElementById('creditTable');

  if (!tbody || !emptyState || !table) return;

  const filtered = _customers.filter((c) => {
    if (!_searchQuery) return true;
    const name = (c.name || '').toLowerCase();
    const phone = (c.phone || '').toLowerCase();
    const q = _searchQuery.toLowerCase();
    return name.includes(q) || phone.includes(q);
  });

  if (filtered.length === 0) {
    table.style.display = 'none';
    emptyState.style.display = 'flex';
    tbody.innerHTML = '';
    return;
  }

  table.style.display = 'table';
  emptyState.style.display = 'none';
  tbody.innerHTML = '';

  filtered.forEach((c) => {
    const tr = document.createElement('tr');
    tr.style.borderBottom = '1px solid var(--border)';
    tr.innerHTML = `
      <td style="padding: 14px 16px; font-family: var(--font-mono, monospace); font-weight: 500;">#${c.id || c.customerId || '-'}</td>
      <td style="padding: 14px 16px; color: var(--muted);">${c.createdAt ? new Date(c.createdAt).toLocaleDateString() : '-'}</td>
      <td style="padding: 14px 16px; font-weight: 600; color: var(--text-strong);">${c.name || 'Unknown'}</td>
      <td style="padding: 14px 16px; color: var(--muted);">${c.phone || '-'}</td>
      <td style="padding: 14px 16px; font-weight: 500;">₹${Number(c.totalPurchases || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 })}</td>
      <td style="padding: 14px 16px; color: var(--success); font-weight: 500;">₹${Number(c.totalPaid || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 })}</td>
      <td style="padding: 14px 16px; color: var(--danger); font-weight: 700;">₹${Number(c.outstandingBalance || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 })}</td>
      <td style="padding: 14px 16px;">
        <span class="badge ${c.outstandingBalance <= 0 ? 'badge-success' : 'badge-warning'}" style="padding: 3px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">
          ${c.outstandingBalance <= 0 ? 'Cleared' : 'Pending'}
        </span>
      </td>
      <td style="padding: 14px 16px; text-align: right;">
        <button class="btn btn-sm btn-outline" onclick="openPaymentModal(${c.id})" style="padding: 4px 10px; font-size: 0.78rem;">Collect</button>
      </td>
    `;
    tbody.appendChild(tr);
  });
}

// Global search input listener
window.onCreditSearchInput = function () {
  clearTimeout(_searchTimer);
  _searchTimer = setTimeout(() => {
    const input = document.getElementById('creditSearch');
    _searchQuery = input ? input.value.trim() : '';
    renderCreditTable();
  }, 250);
};
