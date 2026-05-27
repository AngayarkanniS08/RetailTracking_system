// ============================================
// 1. Helper: API calls with JWT token
// ============================================
async function apiRequest(url, options = {}) {
    const token = localStorage.getItem('auth_token');
    if (!token) {
        console.warn('No auth token – redirecting to login');
        window.location.href = '/index.php?action=login';
        return null;
    }
    const headers = {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`,
        ...options.headers
    };

    let response;
    try {
        response = await fetch(url, { ...options, headers });
    } catch (networkErr) {
        console.error('Network error calling', url, networkErr);
        return null;
    }

    if (response.status === 401) {
        localStorage.removeItem('auth_token');
        window.location.href = '/index.php?action=login';
        return null;
    }

    // Safely parse JSON — catches PHP error pages / empty responses
    const text = await response.text();
    if (!text || !text.trim()) return null;
    try {
        return JSON.parse(text);
    } catch (e) {
        console.error('Non-JSON response from', url, '(status', response.status + '):', text.slice(0, 300));
        return null;
    }
}

// ============================================
// 2. Global state
// ============================================
let categories = [];
let products   = [];
let activeCategoryFilter = 'all';

// ============================================
// 3. Fetch data from API (always re-fetches)
// ============================================
async function loadCategories() {
    const data = await apiRequest('/api/categories');
    if (data && !data.error) {
        categories = data;
        renderCategoryTabs();
        populateCategoryDropdowns();    // product-add modal
        populateSubcategoryDropdown();  // sub-category section in manage modal
        updateStats();
    }
}

async function loadProducts() {
    const data = await apiRequest('/api/products');
    if (data && !data.error) {
        products = data;
        renderProductTable();
        renderCategoryTabs();  // update per-category counts
        updateStats();
    }
}

// ============================================
// 4. Render UI
// ============================================
function renderCategoryTabs() {
    const container = document.getElementById('pmCatFilters');
    if (!container) return;
    let html = `<button class="cat-btn ${activeCategoryFilter === 'all' ? 'active' : ''}" onclick="setCategoryFilter('all')">All <span class="product-count">${products.length}</span></button>`;
    categories.forEach(cat => {
        const count = products.filter(p => p.category_id === cat.id).length;
        html += `<button class="cat-btn ${activeCategoryFilter === cat.id ? 'active' : ''}" onclick="setCategoryFilter('${cat.id}')">${escapeHtml(cat.name)} <span class="product-count">${count}</span></button>`;
    });
    container.innerHTML = html;
}

function renderProductTable() {
    const tbody = document.querySelector('#productMasterTable tbody');
    if (!tbody) return;
    let filtered = products;
    if (activeCategoryFilter !== 'all') {
        filtered = products.filter(p => p.category_id === activeCategoryFilter);
    }
    if (filtered.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; color:var(--muted); padding:2rem;">No products found</td></tr>`;
        return;
    }
    tbody.innerHTML = filtered.map(p => `
        <tr>
            <td>${escapeHtml(p.id.slice(0, 8))}</td>
            <td>${escapeHtml(p.name)}</td>
            <td><span class="badge">${escapeHtml(p.category_name)}</span></td>
            <td>${escapeHtml(p.unit)}</td>
            <td>${p.hsn_code ? escapeHtml(p.hsn_code) : '-'}</td>
            <td>${p.gst_rate}%</td>
    <td>
    <div class="action-buttons">

        <!-- EDIT BUTTON -->
        <button class="btn-icon edit-btn"
            onclick="editProduct('${p.id}')"
            title="Edit product">

            <svg width="14" height="14" viewBox="0 0 24 24"
                fill="none" stroke="currentColor" stroke-width="2">

                <path d="M12 20h9"></path>

                <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"></path>

            </svg>
        </button>

        <!-- DELETE BUTTON -->
        <button class="btn-icon delete-btn"
            onclick="deleteProduct('${p.id}')"
            title="Delete product">

            <svg width="14" height="14" viewBox="0 0 24 24"
                fill="none" stroke="currentColor" stroke-width="2">

                <polyline points="3 6 5 6 21 6"></polyline>

                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>

            </svg>
        </button>

    </div>
</td>
            
        </tr>
    `).join('');
}

function updateStats() {
    const elProducts   = document.getElementById('pmTotalProducts');
    const elCategories = document.getElementById('pmTotalCategories');
    if (elProducts)   elProducts.innerText   = products.length;
    if (elCategories) elCategories.innerText = categories.length;
}

/** Populates the Category dropdown inside the "Add Product" modal */
function populateCategoryDropdowns() {
    const catSelect = document.getElementById('pmProductCategory');
    if (!catSelect) return;
    catSelect.innerHTML = categories.map(cat =>
        `<option value="${cat.id}">${escapeHtml(cat.name)}</option>`
    ).join('');

    // Also trigger subcategory load for the first category
    if (categories.length > 0) {
        loadSubcategoriesIntoProductModal(catSelect.value);
    }
}

/** Populates the "Select Category" dropdown in the Manage Modal subcategory section */
function populateSubcategoryDropdown() {
    const select = document.getElementById('pmSubCatParent');
    if (!select) return;
    select.innerHTML = categories.map(cat =>
        `<option value="${cat.id}">${escapeHtml(cat.name)}</option>`
    ).join('');
}

/** Loads subcategories for the selected category into the product add modal */
async function loadSubcategoriesIntoProductModal(categoryId) {
    const subSelect = document.getElementById('pmProductSubcategory');
    if (!subSelect || !categoryId) return;

    const data = await apiRequest(`/api/subcategories?category_id=${categoryId}`);
    subSelect.innerHTML = `<option value="">No Subcategory</option>`;
    if (data && !data.error && data.length > 0) {
        data.forEach(sub => {
            subSelect.innerHTML += `<option value="${sub.id}">${escapeHtml(sub.name)}</option>`;
        });
    }
}

// ============================================
// 5. CRUD actions
// ============================================
window.setCategoryFilter = function(categoryId) {
    activeCategoryFilter = categoryId;
    renderCategoryTabs();
    renderProductTable();
};

/** Called from Category dropdown change in Add Product modal */
window.onCategoryChange = function(categoryId) {
    loadSubcategoriesIntoProductModal(categoryId);
};

window.saveCategory = async function() {
    const name = document.getElementById('pmCategoryName').value.trim();
    if (!name) {
        alert('Please enter a category name');
        return;
    }
    const data = await apiRequest('/api/categories', {
        method: 'POST',
        body: JSON.stringify({ name })
    });
    if (data && data.success) {
        document.getElementById('pmCategoryName').value = '';
        await loadCategories();  // refreshes tabs + both dropdowns
        await loadProducts();    // update per-category counts in tabs
        closeModal('addCategoryModal');
    } else {
        alert(data?.error || 'Failed to add category');
    }
};

window.saveSubcategory = async function() {
    const categoryId = document.getElementById('pmSubCatParent')?.value;
    const subName    = document.getElementById('pmSubCategoryName').value.trim();

    if (!categoryId) {
        alert('Please select a parent category');
        return;
    }
    if (!subName) {
        alert('Please enter a subcategory name');
        return;
    }

    const data = await apiRequest('/api/subcategories', {
        method: 'POST',
        body: JSON.stringify({ category_id: categoryId, name: subName })
    });

    if (data && data.success) {
        document.getElementById('pmSubCategoryName').value = '';
        alert('Subcategory added successfully');
        // Refresh subcategory list in product modal if matching category is selected
        const prodCat = document.getElementById('pmProductCategory');
        if (prodCat && prodCat.value === categoryId) {
            loadSubcategoriesIntoProductModal(categoryId);
        }
        // Keep manage modal open so user can add more
    } else {
        alert(data?.error || 'Failed to add subcategory');
    }
};

window.saveProduct = async function() {
    const name          = document.getElementById('pmProductName').value.trim();
    const categoryId    = document.getElementById('pmProductCategory').value;
    const subcategoryId = document.getElementById('pmProductSubcategory')?.value || null;
    const unit          = document.getElementById('pmProductUnit').value;
    const hsn           = document.getElementById('pmProductHsn').value.trim();
    const gst           = parseFloat(document.getElementById('pmProductGst').value) || 0;

    if (!name || !categoryId || !unit) {
        alert('Product name, category, and unit are required');
        return;
    }

    const data = await apiRequest('/api/products', {
        method: 'POST',
        body: JSON.stringify({
            name,
            category_id:    categoryId,
            subcategory_id: subcategoryId || null,
            unit,
            hsn_code: hsn  || null,
            gst_rate: gst
        })
    });

    if (data && data.success) {
        await loadProducts();
        await loadCategories();
        resetProductModal();
        closeModal('addProductModal');
    } else {
        alert(data?.error || 'Failed to add product');
    }
};

window.deleteProduct = async function(productId) {
    if (!confirm('Delete this product? This action cannot be undone.')) return;
    const data = await apiRequest(`/api/products/${productId}`, { method: 'DELETE' });
    if (data && data.success) {
        await loadProducts();
        await loadCategories();
    } else {
        alert(data?.error || 'Failed to delete product');
    }
};

window.resetProductModal = function() {
    document.getElementById('pmProductName').value = '';
    document.getElementById('pmProductHsn').value  = '';
    document.getElementById('pmProductGst').value  = '';
    const subSelect = document.getElementById('pmProductSubcategory');
    if (subSelect) subSelect.innerHTML = `<option value="">No Subcategory</option>`;
};

// ============================================
// 6. Render search filter
// ============================================
window.renderProductMaster = function() {
    const query = document.getElementById('pmSearch')?.value.toLowerCase() || '';
    const tbody  = document.querySelector('#productMasterTable tbody');
    if (!tbody) return;

    const filtered = products.filter(p =>
        (activeCategoryFilter === 'all' || p.category_id === activeCategoryFilter) &&
        (p.name.toLowerCase().includes(query) || (p.id && p.id.toLowerCase().includes(query)))
    );

    if (filtered.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; color:var(--muted); padding:2rem;">No products found</td></tr>`;
        return;
    }

    tbody.innerHTML = filtered.map(p => `
        <tr>
            <td>${escapeHtml(p.id.slice(0, 8))}</td>
            <td>${escapeHtml(p.name)}</td>
            <td><span class="badge">${escapeHtml(p.category_name)}</span></td>
            <td>${escapeHtml(p.unit)}</td>
            <td>${p.hsn_code ? escapeHtml(p.hsn_code) : '-'}</td>
            <td>${p.gst_rate}%</td>
            <td>
                <div class="action-buttons">
                    <button class="btn-icon edit-btn" onclick="editProduct('${p.id}')" title="Edit product">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 20h9"></path>
                            <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"></path>
                        </svg>
                    </button>
                    <button class="btn-icon delete-btn" onclick="deleteProduct('${p.id}')" title="Delete product">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3 6 5 6 21 6"></polyline>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                        </svg>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
};

// ============================================
// 7. HTML escape helper
// ============================================
function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>"']/g, m => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[m]));
}

// ============================================
// 8. Initialisation — always re-fetches fresh data
// ============================================
async function initProductMaster() {
    await loadCategories();
    await loadProducts();
}

// DOMContentLoaded: ALWAYS pre-load data so categories survive page refresh.
// Sidebar.js also calls initProductMaster() on every navigation to product_master.
document.addEventListener('DOMContentLoaded', () => {
    // Only run initialization if we are logged in (dashboardView is present)
    if (!document.getElementById('dashboardView')) {
        return;
    }

    // Wire the Save/Update button — onclick attribute removed from HTML to avoid double-fire
    const btn = document.getElementById('addProductModalBtn');
    if (btn) btn.onclick = saveProduct;

    initProductMaster();
});
// Global variable to track which product is being edited
let editingProductId = null;

// Called when clicking the Edit button (pencil icon)
window.editProduct = async function(productId) {
    // Find product in the local `products` array (fetched from API)
    const product = products.find(p => p.id === productId);
    if (!product) {
        alert('Product not found');
        return;
    }
    editingProductId = productId;
    
    // Populate the Add Product modal with existing data
    document.getElementById('addProductModalTitle').innerText = 'Edit Product';
    document.getElementById('addProductModalBtn').innerText = 'Update Product';
    // Change the button's onclick to our update function
    const saveBtn = document.getElementById('addProductModalBtn');
    saveBtn.onclick = updateProduct;
    
    document.getElementById('pmProductName').value = product.name;
    document.getElementById('pmProductCategory').value = product.category_id; // Assuming category_id exists
    // Trigger subcategory dropdown population based on selected category
    if (typeof onCategoryChange === 'function') {
        onCategoryChange(product.category_id);
        // Wait a bit for subcategories to load, then set value
        setTimeout(() => {
            if (product.subcategory_id) {
                document.getElementById('pmProductSubcategory').value = product.subcategory_id;
            }
        }, 100);
    }
    document.getElementById('pmProductUnit').value = product.unit;
    document.getElementById('pmProductHsn').value = product.hsn_code || '';
    document.getElementById('pmProductGst').value = product.gst_rate;
    
    openModal('addProductModal');
};

// Called when the modal's save button is in "Update" mode
window.updateProduct = async function() {
    const name = document.getElementById('pmProductName').value.trim();
    const categoryId = document.getElementById('pmProductCategory').value;
    const subcategoryId = document.getElementById('pmProductSubcategory').value || null;
    const unit = document.getElementById('pmProductUnit').value;
    const hsn = document.getElementById('pmProductHsn').value.trim();
    const gst = parseFloat(document.getElementById('pmProductGst').value) || 0;
    
    if (!name || !categoryId || !unit) {
        alert('Product name, category, and unit are required');
        return;
    }
    
    const data = await apiRequest(`/api/products/${editingProductId}`, {
        method: 'PUT',
        body: JSON.stringify({
            name,
            category_id: categoryId,
            subcategory_id: subcategoryId,
            unit,
            hsn_code: hsn,
            gst_rate: gst
        })
    });
    
    if (data && data.success) {
        // Refresh products list
        await loadProducts();
        // Close modal and reset to "Add" mode
        closeModal('addProductModal');
        resetProductModal(); // this function should reset title/button/onclick
        alert('Product updated successfully');
    } else {
        alert(data?.error || 'Failed to update product');
    }
};

// Reset modal back to "Add" mode
window.resetProductModal = function() {
    editingProductId = null;

    const title = document.getElementById('addProductModalTitle');
    const btn   = document.getElementById('addProductModalBtn');

    if (title) title.innerText = 'Add New Product';
    if (btn) {
        btn.innerText = 'Save Product';
        btn.onclick   = saveProduct;   // ← always point back to Add
    }

    // Clear form fields
    const nameEl = document.getElementById('pmProductName');
    const hsnEl  = document.getElementById('pmProductHsn');
    const gstEl  = document.getElementById('pmProductGst');
    if (nameEl) nameEl.value = '';
    if (hsnEl)  hsnEl.value  = '';
    if (gstEl)  gstEl.value  = '';

    // Reset category to first option
    const catSelect = document.getElementById('pmProductCategory');
    if (catSelect && catSelect.options.length) catSelect.selectedIndex = 0;

    // Clear subcategory dropdown
    const subSelect = document.getElementById('pmProductSubcategory');
    if (subSelect) subSelect.innerHTML = '<option value="">No Subcategory</option>';
};