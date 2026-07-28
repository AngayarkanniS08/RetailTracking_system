/**
 * customers.js — Customer Credit (Kadan) page controller
 */

import {
  fetchCustomerCreditsApi,
  createCustomerApi,
  recordCreditPaymentApi,
} from '../services/credit.service.js';

let _customers = [];
let _searchQuery = '';
let _searchTimer = null;

export async function initCustomerCredit() {
  const tbody = document.querySelector('#creditTable tbody');
  const emptyState = document.getElementById('creditEmptyState');

  if (tbody) {
    tbody.innerHTML =
      '<tr><td colspan="9" style="text-align:center; padding: 32px; color: var(--muted);">Loading credit accounts...</td></tr>';
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
    const rawId = String(c.id || c.customerId || '');
    const shortId = rawId.length > 8 ? rawId.slice(-6).toUpperCase() : rawId;
    const tr = document.createElement('tr');
    tr.style.borderBottom = '1px solid var(--border)';
    tr.innerHTML = `
      <td style="padding: 14px 16px; font-family: var(--font-mono, monospace); font-weight: 600; color: var(--accent);">#${shortId}</td>
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
        <button class="btn btn-sm btn-outline" onclick="openPaymentModal('${c.id || c.customerId}')" style="padding: 4px 10px; font-size: 0.78rem;">Collect</button>
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

// Global modal action handlers
window.saveCustomer = async function () {
  const nameInput = document.getElementById('custName');
  const phoneInput = document.getElementById('custPhone');
  const limitInput = document.getElementById('custCreditLimit');
  const balanceInput = document.getElementById('custOpeningBalance');

  const name = nameInput ? nameInput.value.trim() : '';
  const phone = phoneInput ? phoneInput.value.trim() : '';
  const creditLimit = limitInput ? parseFloat(limitInput.value) || 0 : 0;
  const openingBalance = balanceInput ? parseFloat(balanceInput.value) || 0 : 0;

  if (!name) {
    alert('Please enter a valid customer name.');
    return;
  }
  if (!phone) {
    alert('Please enter a valid phone number.');
    return;
  }

  try {
    const payload = {
      name,
      phone,
      credit_limit: creditLimit,
      opening_balance: openingBalance,
    };
    await createCustomerApi(payload);

    // Clear form inputs
    if (nameInput) nameInput.value = '';
    if (phoneInput) phoneInput.value = '';
    if (limitInput) limitInput.value = '';
    if (balanceInput) balanceInput.value = '';

    // Close modal dialog
    if (typeof window.closeModal === 'function') {
      window.closeModal('addCustomerModal');
    }

    // Refresh customers list
    await initCustomerCredit();
  } catch (err) {
    console.error('Failed to create customer:', err);
    alert(err.message || 'Failed to create customer. Please try again.');
  }
};

window.openPaymentModal = function (customerId) {
  const customer = _customers.find((c) => (c.id || c.customerId) == customerId);
  if (!customer) return;

  const idInput = document.getElementById('payCustId');
  const nameEl = document.getElementById('payCustName');
  const outEl = document.getElementById('payOutstanding');
  const amountInput = document.getElementById('payAmount');
  const notesInput = document.getElementById('payNotes');

  if (idInput) idInput.value = customer.id || customer.customerId;
  if (nameEl) nameEl.textContent = customer.name || '-';
  if (outEl) outEl.textContent = `₹${Number(customer.outstandingBalance || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 })}`;
  if (amountInput) amountInput.value = '';
  if (notesInput) notesInput.value = '';

  if (typeof window.openModal === 'function') {
    window.openModal('paymentModal');
  }
};

window.processPayment = async function () {
  const idInput = document.getElementById('payCustId');
  const amountInput = document.getElementById('payAmount');
  const notesInput = document.getElementById('payNotes');

  const customerId = idInput ? idInput.value : '';
  const amount = amountInput ? parseFloat(amountInput.value) || 0 : 0;
  const notes = notesInput ? notesInput.value.trim() : '';

  if (!customerId) {
    alert('Invalid customer selected.');
    return;
  }
  if (amount <= 0) {
    alert('Please enter a payment amount greater than ₹0.');
    return;
  }

  try {
    await recordCreditPaymentApi(customerId, { amount, notes });

    if (typeof window.closeModal === 'function') {
      window.closeModal('paymentModal');
    }

    await initCustomerCredit();
  } catch (err) {
    console.error('Failed to record payment:', err);
    alert(err.message || 'Failed to record payment. Please try again.');
  }
};
