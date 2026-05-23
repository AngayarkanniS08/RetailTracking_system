// Navigation
function switchTab(tabId) {
    // Hide all sections
    document.querySelectorAll('.view-section').forEach(el => el.classList.remove('active'));
    // Show selected section
    const activeSection = document.getElementById(tabId);
    if (activeSection) activeSection.classList.add('active');
    // Update sidebar active class
    document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
    const activeNav = Array.from(document.querySelectorAll('.nav-item')).find(el => 
        el.getAttribute('onclick')?.includes(tabId)
    );
    if (activeNav) activeNav.classList.add('active');
}

// Modal handling
function openModal(id) {
    document.getElementById(id).classList.add('active');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

// Utility
function formatCurrency(amount) {
    return '₹' + parseFloat(amount).toFixed(2);
}

// Temporary dummy functions to prevent errors
function resetProductModal() { /* do nothing for now */ }
function populateCustomerSelect() { /* will be implemented later */ }
// Add any other missing functions here as empty stubs