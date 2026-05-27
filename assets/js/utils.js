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

// Save the original openModal/closeModal if they exist, or define them
const originalCloseModal = window.closeModal || function(id) {
    document.getElementById(id).classList.remove('active');
};

window.closeModal = function(id) {
    originalCloseModal(id);
    if (id === 'addProductModal') {
        resetProductModal();   // your existing reset function
    }
};
function resetProductModal() {
    editingProductId = null;
    const titleEl = document.getElementById('addProductModalTitle');
    if (titleEl) titleEl.innerText = 'Add New Product';
    const btn = document.getElementById('addProductModalBtn');
    if (btn) {
        btn.innerText = 'Save Product';
        btn.onclick = saveProduct;  // ensure saveProduct is defined globally
    }
    // Clear inputs
    const nameInput = document.getElementById('pmProductName');
    if (nameInput) nameInput.value = '';
    const hsnInput = document.getElementById('pmProductHsn');
    if (hsnInput) hsnInput.value = '';
    const gstInput = document.getElementById('pmProductGst');
    if (gstInput) gstInput.value = '';
    // Reset category selection to first option if needed
    const catSelect = document.getElementById('pmProductCategory');
    if (catSelect && catSelect.options.length) catSelect.selectedIndex = 0;
    const subSelect = document.getElementById('pmProductSubcategory');
    if (subSelect) subSelect.innerHTML = '<option value="">No Subcategory</option>';
}

// Utility
function formatCurrency(amount) {
    return '₹' + parseFloat(amount).toFixed(2);
}

// Temporary stubs to prevent errors from other modules (do NOT add resetProductModal here)
function populateCustomerSelect() { /* will be implemented later */ }