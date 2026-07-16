/**
 * Shared Utilities for Retail Tracking System
 */

// ── API Helper ─────────────────────────────────────────────────────────────
/**
 * Wrapper for fetch API to include JWT token and handle 401 Unauthorized
 */
let _redirecting = false;
async function apiRequest(path, options = {}) {
    const apiBase = `${window.location.protocol}//${window.location.hostname}:8081`;
    const fullPath = path.startsWith('http') ? path : apiBase + path;

    const token = localStorage.getItem('auth_token');
    const headers = {
        'Content-Type': 'application/json',
        'Authorization': token ? 'Bearer ' + token : '',
        ...options.headers
    };

    let response;
    try {
        response = await fetch(fullPath, { ...options, headers });
    } catch (networkErr) {
        console.error('Network error calling', fullPath, networkErr);
        throw networkErr;
    }

    if (response.status === 401) {
        localStorage.removeItem('auth_token');
        localStorage.removeItem('auth_user');
        if (!_redirecting) {
            _redirecting = true;
            logoutUser();
        }
        throw new Error('Session expired');
    }

    const text = await response.text();
    if (!text || !text.trim()) {
        if (!response.ok) throw new Error('API request failed (empty response)');
        return null;
    }

    let data;
    try {
        data = JSON.parse(text);
    } catch (e) {
        console.error('Non-JSON response from', path, '(status', response.status + '):', text.slice(0, 300));
        throw new Error('Invalid JSON response from API');
    }

    if (!response.ok) {
        throw new Error(data.error || 'API request failed');
    }
    return data;
}

// ── Modal Handling ─────────────────────────────────────────────────────────
function openModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.add('active');
    }
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.remove('active');
    }
}

// Global click listener to close modals when clicking outside
window.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('active');
    }
});

// ── Formatting ─────────────────────────────────────────────────────────────
function formatCurrency(amount) {
    return '₹' + parseFloat(amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// Helper: generate unique IDs (e.g. B12345)
function generateId(prefix) {
    return prefix + Math.floor(Math.random() * 100000);
}

// ── Dashboard Sales Summary ───────────────────────────────────────────────
async function openSalesSummaryDetail(period) {
    const today = new Date();
    const y = today.getFullYear();
    const m = String(today.getMonth() + 1).padStart(2, '0');
    const d = String(today.getDate()).padStart(2, '0');
    const todayStr = `${y}-${m}-${d}`;

    let dateFrom;
    let title;

    if (period === 'today') {
        dateFrom = todayStr;
        title = "Today's Sales Bills";
    } else if (period === 'week') {
        const dayOfWeek = today.getDay() || 7;
        const weekStart = new Date(today);
        weekStart.setDate(weekStart.getDate() - (dayOfWeek - 1));
        dateFrom = weekStart.toISOString().slice(0, 10);
        title = "This Week's Sales Bills";
    } else {
        dateFrom = `${y}-${m}-01`;
        title = "This Month's Sales Bills";
    }

    const dateTo = todayStr;

    document.getElementById('salesSummaryDetailTitle').innerText = title;
    const tbody = document.querySelector('#salesSummaryDetailTable tbody');
    tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;color:var(--muted);padding:1rem;">Loading...</td></tr>';
    openModal('salesSummaryDetailModal');

    try {
        const res = await apiRequest(`/api/invoices?date_from=${encodeURIComponent(dateFrom)}&date_to=${encodeURIComponent(dateTo)}&limit=100`);
        const invoices = Array.isArray(res) ? res : (res.data || []);
        tbody.innerHTML = '';

        if (invoices.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;color:var(--muted);padding:2rem;">No bills found for this period.</td></tr>';
            return;
        }

        invoices.forEach(function(inv) {
            const dateStr = inv.billedAt ? new Date(inv.billedAt).toLocaleDateString('en-IN', { day: '2-digit', month: 'short' }) : '-';
            const customerName = inv.customerNameSnapshot || 'Walk-in';
            tbody.innerHTML += `
                <tr style="cursor:pointer;" onclick="window.open('${window.location.protocol}//${window.location.hostname}:8081/api/invoices/${inv.id}/receipt?token=' + encodeURIComponent(localStorage.getItem('auth_token') || ''), '_blank')">
                    <td style="font-family:var(--mono); color:var(--accent); font-weight:600;">📄 ${inv.invoiceNumber || inv.id}</td>
                    <td style="color:var(--muted);">${dateStr}</td>
                    <td style="font-weight:500;">${customerName}</td>
                    <td style="font-weight:600; color:var(--ok);">${formatCurrency(inv.grandTotal || 0)}</td>
                </tr>
            `;
        });
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;color:var(--danger);padding:2rem;">Failed to load bills: ' + e.message + '</td></tr>';
    }
}

function logoutUser() {
    document.cookie = "auth_uid=; path=/; max-age=0; SameSite=Lax";
    localStorage.removeItem('auth_token');
    localStorage.removeItem('auth_user');
    window.location.href = '/login';
}

// Export functions to global window object
window.apiRequest = apiRequest;
window.openModal = openModal;
window.closeModal = closeModal;
window.formatCurrency = formatCurrency;
window.generateId = generateId;
window.openSalesSummaryDetail = openSalesSummaryDetail;
window.logoutUser = logoutUser;

