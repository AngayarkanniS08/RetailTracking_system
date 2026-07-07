// ============================================
// Sidebar Navigation (works with your existing HTML)
// ============================================

// List of all main section IDs (must match the ids in your views)
const sections = [
    'dashboard',
    'billing_pos',
    'credit_kadan',
    'day_to_day_selling',
    'product_master',
    'inventory',
    'vendor_list',
    'vendorhistory',
    'stockintel',
    'product_history'
];

// Switch to a specific section by ID
function switchTab(sectionId, vendorId = null) {
    // Hide all sections
    sections.forEach(id => {
        const section = document.getElementById(id);
        if (section) section.classList.remove('active');
    });

    // Show the selected section
    const activeSection = document.getElementById(sectionId);
    if (activeSection) activeSection.classList.add('active');

    // Update active class on sidebar nav items
    document.querySelectorAll('.nav-item').forEach(item => {
        const itemSection = item.getAttribute('data-section');
        if (itemSection === sectionId) {
            item.classList.add('active');
        } else {
            item.classList.remove('active');
        }
    });

    // Call module‑specific initialisation when its section is shown
    if (sectionId === 'dashboard') {
        if (typeof initDashboard === 'function') initDashboard();
    }
    if (sectionId === 'product_master') {
        if (typeof initProductMaster === 'function') initProductMaster();
    }
    if (sectionId === 'inventory') {
        if (typeof initInventory === 'function') initInventory();
    }
    if (sectionId === 'vendor_list') {
        if (typeof initVendorPage === 'function') initVendorPage();
    }
    if (sectionId === 'credit_kadan' || sectionId === 'credit') {
        if (typeof loadCreditPage === 'function') loadCreditPage();
    }
    if (sectionId === 'vendorhistory' && typeof initVendorHistory === 'function') initVendorHistory(vendorId);

    if (sectionId === 'day_to_day_selling') {
        if (typeof initDayToDaySelling === 'function') initDayToDaySelling();
    }

    if (sectionId === 'billing_pos') {
        if (posProducts.length === 0) loadPOSData();
    }

}


// Initialise sidebar click handlers
function initSidebar() {
    document.querySelectorAll('.nav-item').forEach(item => {
        const sectionId = item.getAttribute('data-section');
        if (sectionId) {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                switchTab(sectionId);
            });
        }
    });

    // Activate the default section (e.g., dashboard) if none is active
    // Only do this on logged-in pages where dashboard sections exist.
    const activeExists = sections.some(id => {
        const sec = document.getElementById(id);
        return sec && sec.classList.contains('active');
    });
    if (!activeExists && document.getElementById('dashboardView')) {
        switchTab('dashboard');
    }
}

// Run when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSidebar);
} else {
    initSidebar();
}

// Make switchTab globally available for onclick handlers (if you use inline onclick)
window.switchTab = switchTab;