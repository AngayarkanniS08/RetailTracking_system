// Global variables
let categories = [];

// Load categories from API
async function loadCategories() {
    const res = await fetch('/RetailTracking_system/api/categories.php')
    categories = await res.json();
    renderCategoryTabs();
    populateCategoryDropdowns();
}

// Render category tabs in Product Master page
function renderCategoryTabs() {
    const container = document.getElementById('pmCatFilters');
    if (!container) return;
    let html = `<button class="cat-btn active" onclick="filterByCategory('all')">All <span class="product-count">${categories.length}</span></button>`;
    categories.forEach(cat => {
        html += `<button class="cat-btn" onclick="filterByCategory('${cat.name}')">${cat.name} <span class="product-count">0</span></button>`;
    });
    container.innerHTML = html;
}

// Populate category dropdown in Add Product modal
function populateCategoryDropdowns() {
    const selects = document.querySelectorAll('#pmProductCategory, #pmSubCatParent');
    selects.forEach(sel => {
        if (!sel) return;
        sel.innerHTML = '';
        categories.forEach(cat => {
            sel.innerHTML += `<option value="${cat.name}">${cat.name}</option>`;
        });
    });
}

// Add new category
async function addCategory() {
    const name = document.getElementById('pmCategoryName').value.trim();
    if (!name) return alert('Enter category name');
    const res = await fetch('/RetailTracking_system/api/categories.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name })
    });
    const data = await res.json();
    if (data.success) {
        await loadCategories();
        document.getElementById('pmCategoryName').value = '';
        closeModal('addCategoryModal');
    } else {
        alert(data.error);
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    loadCategories();
    // Attach event listener to the save button in modal
    const saveBtn = document.querySelector('#addCategoryModal .btn-primary');
    if (saveBtn) saveBtn.onclick = addCategory;
});