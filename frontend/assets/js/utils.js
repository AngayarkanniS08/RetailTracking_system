/**
 * Shared Utilities for Retail Tracking System
 */

// ── API Helper ─────────────────────────────────────────────────────────────
/**
 * Wrapper for fetch API to include JWT token and handle 401 Unauthorized
 */
async function apiRequest(path, options = {}) {
    const token = localStorage.getItem('auth_token');
    if (!token && !path.includes('/api/login') && !path.includes('/api/register')) {
        window.location.href = '/Modules/auth/login.html';
        throw new Error('Not authenticated');
    }

    const headers = {
        'Content-Type': 'application/json',
        ...options.headers
    };
    if (token) {
        headers['Authorization'] = 'Bearer ' + token;
    }

    try {
        const res = await fetch(path, { ...options, headers });
        
        if (res.status === 401) {
            localStorage.removeItem('auth_token');
            localStorage.removeItem('auth_user');
            window.location.href = '/Modules/auth/login.html';
            throw new Error('Session expired');
        }

        const data = await res.json();
        if (!res.ok) {
            throw new Error(data.error || 'API request failed');
        }
        return data;
    } catch (err) {
        console.error(`API Error on ${path}:`, err);
        throw err;
    }
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

// Export functions to global window object
window.apiRequest = apiRequest;
window.openModal = openModal;
window.closeModal = closeModal;
window.formatCurrency = formatCurrency;
