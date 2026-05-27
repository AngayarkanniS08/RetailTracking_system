// ============================================
// 1. Helper: API calls with JWT token
// ============================================
async function apiRequest(url, options = {}) {
    const token = localStorage.getItem('auth_token');
    if (!token) {
        console.error('No auth token found');
        // Optionally redirect to login
        // window.location.href = '/index.php?action=login';
        return null;
    }
    const headers = {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`,
        ...options.headers
    };
    const response = await fetch(url, { ...options, headers });
    if (response.status === 401) {
        // Token expired or invalid → logout
        localStorage.removeItem('auth_token');
        window.location.href = '/index.php?action=login';
        return null;
    }
    return response.json();
}

// ============================================
// 2. Global state
// ============================================
let categories = [];
let products = [];
let activeCategoryFilter = 'all';

// ============================================
// 3. Fetch data from API
// ============================================
async function loadCategories() {
    const data = await apiRequest('/api/categories');
    if (data && !data.error) {
        categories = data;
        renderCategoryTabs();
        populateCategoryDropdowns();
        updateStats(); // update total categories count
    }
}

async function loadProducts() {
    const data = await apiRequest('/api/products');
    if (data && !data.error) {
        products = data;
        renderProductTable();
        updateStats(); // update total products, categories count
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
        const productCount = products.filter(p => p.category_id === cat.id).length;
        html += `<button class="cat-btn ${activeCategoryFilter === cat.id ? 'active' : ''}" onclick="setCategoryFilter('${cat.id}')">${cat.name} <span class="product-count">${productCount}</span></button>`;
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
    tbody.innerHTML = filtered.map(p => `
        <tr>
            <td>${p.id}</td>
            <td>${escapeHtml(p.name)}</td>
            <td><span class="badge">${escapeHtml(p.category_name)}</span></td>
            <td>${p.unit}</td>
            <td>${p.hsn_code || '-'}</td>
            <td>${p.gst_rate}%</td>
            <td>
                <button class="btn-icon-danger" onclick="deleteProduct('${p.id}')">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    </svg>
                </button>
            </td>
        </tr>
    `).join('');
}

function updateStats() {
    const totalProducts = products.length;
    const totalCategories = categories.length;
    document.getElementById('pmTotalProducts').innerText = totalProducts;
    document.getElementById('pmTotalCategories').innerText = totalCategories;
    // batches count can be loaded separately if needed
}

function populateCategoryDropdowns() {
    // Populate product category select in modal
    const catSelect = document.getElementById('pmProductCategory');
    if (!catSelect) return;
    catSelect.innerHTML = categories.map(cat => `<option value="${cat.id}">${escapeHtml(cat.name)}</option>`).join('');
}

// ============================================
// 5. CRUD actions
// ============================================
window.setCategoryFilter = function(categoryId) {
    activeCategoryFilter = categoryId;
    renderCategoryTabs();
    renderProductTable();
};

window.saveCategory = async function() {
    const name = document.getElementById('pmCategoryName').value.trim();
    if (!name) return alert('Please enter a category name');
    const data = await apiRequest('/api/categories', {
        method: 'POST',
        body: JSON.stringify({ name })
    });
    if (data && data.success) {
        await loadCategories();   // refresh list
        document.getElementById('pmCategoryName').value = '';
        closeModal('addCategoryModal');
    } else {
        alert(data?.error || 'Failed to add category');
    }
};

window.saveProduct = async function() {
    const name = document.getElementById('pmProductName').value.trim();
    const categoryId = document.getElementById('pmProductCategory').value;
    const unit = document.getElementById('pmProductUnit').value;
    const hsn = document.getElementById('pmProductHsn').value.trim();
    const gst = parseFloat(document.getElementById('pmProductGst').value) || 0;
    if (!name || !categoryId || !unit) return alert('Product name, category, and unit are required');
    const data = await apiRequest('/api/products', {
        method: 'POST',
        body: JSON.stringify({ name, category_id: categoryId, unit, hsn_code: hsn, gst_rate: gst })
    });
    if (data && data.success) {
        await loadProducts();
        await loadCategories(); // to update category counts
        resetProductModal();
        closeModal('addProductModal');
    } else {
        alert(data?.error || 'Failed to add product');
    }
};

window.deleteProduct = async function(productId) {
    if (!confirm('Are you sure? This will also delete all batches and sales of this product.')) return;
    const data = await apiRequest(`/api/products/${productId}`, { method: 'DELETE' });
    if (data && data.success) {
        await loadProducts();
        await loadCategories();
    } else {
        alert(data?.error || 'Failed to delete product');
    }
};

function resetProductModal() {
    document.getElementById('pmProductName').value = '';
    document.getElementById('pmProductHsn').value = '';
    document.getElementById('pmProductGst').value = '';
    // reset category selection to first option
}

// Helper to escape HTML
function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}

// ============================================
// 6. Initialisation
// ============================================
async function initProductMaster() {
       // Avoid re‑fetching if already loaded (optional)
    if (products.length === 0 && categories.length === 0) {
        await loadCategories();
        await loadProducts();
    } else {
        // Just re-render with existing data
        renderCategoryTabs();
        renderProductTable();
        updateStats();
        populateCategoryDropdowns();
    }
}


// Run when the product master section becomes visible (you can call it from your tab router)
// If the product master view is always present but hidden, initialise when DOM ready.
document.addEventListener('DOMContentLoaded', () => {
    // Check if we are on product master page (element exists)
    const productSection = document.getElementById('productmaster');
    if (productSection && productSection.classList.contains('active')) {
        initProductMaster();
    }
});

// If your sidebar uses switchTab, you can also call initProductMaster when that tab is shown.
window.navigateTo = window.navigateTo || function(sectionId) {
    // ... existing navigation code
    if (sectionId === 'productmaster') {
        initProductMaster();
    }
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
        // Refresh categories list
        await loadCategories();
        await loadProducts(); // to update category counts in tabs
        document.getElementById('pmCategoryName').value = '';
        closeModal('addCategoryModal');
    } else {
        alert(data?.error || 'Failed to add category');
    }
};

// Inside productmaster.js, after other functions

async function loadSubcategories(categoryId) {
    const data = await apiRequest(`/api/subcategories?category_id=${categoryId}`);
    if (data && !data.error) {
        // You can store or render subcategory dropdown if needed
        return data;
    }
    return [];

    function populateSubcategoryDropdown() {
    const select = document.getElementById('pmSubCatParent');
    if (select && categories.length) {
        select.innerHTML = categories.map(cat => `<option value="${cat.id}">${escapeHtml(cat.name)}</option>`).join('');
    }
}
}

window.saveSubcategory = async function() {
    const categorySelect = document.getElementById('pmSubCatParent');
    const categoryId = categorySelect ? categorySelect.value : '';
    const subName = document.getElementById('pmSubCategoryName').value.trim();
    
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
        closeModal('addSubcategoryModal');
        alert('Subcategory added');
        // Optionally refresh product master if needed
    } else {
        alert(data?.error || 'Failed to add subcategory');
    }

    
};