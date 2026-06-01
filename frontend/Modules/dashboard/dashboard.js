/* dashboard.js — Dashboard page logic: auth guard, API calls, UI rendering */

(function () {
    'use strict';

    // ── Auth Guard ──────────────────────────────────────────────────────────
    // If no token, kick user back to login
    const token = localStorage.getItem('auth_token');
    if (!token) {
        window.location.href = '/Modules/auth/login.html';
        return;
    }



    // ── Populate user info in topbar ────────────────────────────────────────
    function initUserInfo() {
        const raw = localStorage.getItem('auth_user');
        if (!raw) return;
        try {
            const user = JSON.parse(raw);
            const nameEl   = document.getElementById('topbarUsername');
            const avatarEl = document.getElementById('topbarAvatar');
            if (nameEl)   nameEl.innerText   = user.username || 'User';
            if (avatarEl) avatarEl.innerText = (user.username || 'U')[0].toUpperCase();
        } catch (_) {}
    }

    // ── Logout ──────────────────────────────────────────────────────────────
    function initLogout() {
        const btn = document.getElementById('logoutBtn');
        if (!btn) return;
        btn.addEventListener('click', () => {
            localStorage.removeItem('auth_token');
            localStorage.removeItem('auth_user');
            window.location.href = '/Modules/auth/login.html';
        });
    }

    // ── Sidebar navigation ──────────────────────────────────────────────────
    function initSidebar() {
        document.querySelectorAll('.nav-item[data-section]').forEach(item => {
            item.addEventListener('click', () => {
                const target = item.getAttribute('data-section');
                switchSection(target);
            });
        });
    }

    function switchSection(sectionId) {
        // Toggle view sections
        document.querySelectorAll('.view-section').forEach(el => el.classList.remove('active'));
        const target = document.getElementById(sectionId);
        if (target) target.classList.add('active');

        // Toggle nav active state
        document.querySelectorAll('.nav-item[data-section]').forEach(el => el.classList.remove('active'));
        const activeNav = document.querySelector(`.nav-item[data-section="${sectionId}"]`);
        if (activeNav) activeNav.classList.add('active');
    }

    // ── Format helpers ──────────────────────────────────────────────────────
    function fmt(amount) {
        return '₹' + parseFloat(amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 });
    }

    // ── Load dashboard summary data ─────────────────────────────────────────
    async function loadDashboardStats() {
        try {
            const data = await apiRequest('/api/dashboard/stats');
            if (!data) return;

            // Sales summary cards
            setText('tcTodayRev',   fmt(data.today?.revenue));
            setText('tcTodayBills', data.today?.bills ?? 0);
            setText('tcTodayAvg',   fmt(data.today?.avg));

            setText('tcWeekRev',    fmt(data.week?.revenue));
            setText('tcWeekBills',  data.week?.bills ?? 0);
            setText('tcWeekAvg',    fmt(data.week?.avg));

            setText('tcMonthRev',   fmt(data.month?.revenue));
            setText('tcMonthBills', data.month?.bills ?? 0);
            setText('tcMonthAvg',   fmt(data.month?.avg));

            // Purchase summary cards
            setText('pcWeekAmount',    fmt(data.purchase_week?.amount));
            setText('pcWeekPurchases', data.purchase_week?.count ?? 0);
            setText('pcWeekPaid',      fmt(data.purchase_week?.paid));

            setText('pcMonthAmount',    fmt(data.purchase_month?.amount));
            setText('pcMonthPurchases', data.purchase_month?.count ?? 0);
            setText('pcMonthPaid',      fmt(data.purchase_month?.paid));

        } catch (err) {
            console.warn('Dashboard stats unavailable:', err.message);
        }
    }

    // ── Load top-selling / low-selling / old stock ──────────────────────────
    async function loadStockIntel() {
        try {
            const data = await apiRequest('/api/dashboard/stock-intel');
            if (!data) return;

            renderTable('highSellingTable', data.high_selling || [], [
                { key: 'name', label: 'Product' },
                { key: 'qty_sold', label: 'Qty Sold' },
                { key: 'revenue', label: 'Revenue', format: fmt }
            ]);
            renderTable('lowSellingTable', data.low_selling || [], [
                { key: 'name', label: 'Product' },
                { key: 'qty_sold', label: 'Qty Sold' },
                { key: 'revenue', label: 'Revenue', format: fmt }
            ]);
            renderTable('oldStockTable', data.old_stock || [], [
                { key: 'name', label: 'Product' },
                { key: 'batch', label: 'Batch' },
                { key: 'age_days', label: 'Age (days)' },
                { key: 'qty', label: 'Qty' }
            ]);
        } catch (err) {
            console.warn('Stock intel unavailable:', err.message);
        }
    }

    // ── Generic table renderer ──────────────────────────────────────────────
    function renderTable(tableId, rows, columns) {
        const table = document.getElementById(tableId);
        if (!table) return;
        const tbody = table.querySelector('tbody');
        if (!tbody) return;

        if (!rows.length) {
            tbody.innerHTML = `<tr><td colspan="${columns.length}" class="empty-state">No data available</td></tr>`;
            return;
        }
        tbody.innerHTML = rows.map(row => `
            <tr>${columns.map(col => {
                const val = row[col.key] ?? '—';
                return `<td>${col.format ? col.format(val) : val}</td>`;
            }).join('')}</tr>
        `).join('');
    }

    // ── DOM helper ──────────────────────────────────────────────────────────
    function setText(id, value) {
        const el = document.getElementById(id);
        if (el) el.innerText = value;
    }

    // ── Bootstrap ───────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', () => {
        initUserInfo();
        initLogout();
        initSidebar();
        loadDashboardStats();
        loadStockIntel();
    });

})();
