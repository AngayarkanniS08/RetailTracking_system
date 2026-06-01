(function () {
    'use strict';

    // ============================================
    // Global state
    // ============================================
    let categories = [];
    let products   = [];
    let activeCategoryFilter = 'all';

    // ============================================
    // Fetch data from API (always re-fetches)
    // ============================================
    async function loadCategories() {
        try {
            const data = await window.apiRequest('/api/categories');
            if (data && !data.error) {
                categories = data;
                renderCategoryTabs();
                populateCategoryDropdowns();    // product-add modal
                populateSubcategoryDropdown();  // sub-category section in manage modal
                updateStats();
            }
        } catch (e) {
            console.error('Error loading categories:', e);
        }
    }

    async function loadProducts() {
        try {
            const data = await window.apiRequest('/api/products');
            if (data && !data.error) {
                products = data;
                renderProductTable();
                renderCategoryTabs();  // update per-category counts
                updateStats();
            }
        } catch (e) {
            console.error('Error loading products:', e);
        }
    }

    // ============================================
    // Render UI
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
        
        const query = document.getElementById('pmSearch')?.value.toLowerCase() || '';
        
        let filtered = products;
        if (activeCategoryFilter !== 'all') {
            filtered = filtered.filter(p => p.category_id === activeCategoryFilter);
        }
        if (query) {
            filtered = filtered.filter(p => 
                p.name.toLowerCase().includes(query) || 
                (p.id && p.id.toLowerCase().includes(query))
            );
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

        try {
            const data = await window.apiRequest(`/api/subcategories?category_id=${categoryId}`);
            subSelect.innerHTML = `<option value="">No Subcategory</option>`;
            if (data && !data.error && data.length > 0) {
                data.forEach(sub => {
                    subSelect.innerHTML += `<option value="${sub.id}">${escapeHtml(sub.name)}</option>`;
                });
            }
        } catch (e) {
            console.error('Error loading subcategories:', e);
        }
    }

    // ============================================
    // CRUD actions
    // ============================================
    window.setCategoryFilter = function(categoryId) {
        activeCategoryFilter = categoryId;
        renderCategoryTabs();
        renderProductTable();
    };

    /** Called from Category dropdown change in Add Product modal */
    // Attaching directly to element instead of global window object
    document.addEventListener('DOMContentLoaded', () => {
        const pmProductCategory = document.getElementById('pmProductCategory');
        if (pmProductCategory) {
            pmProductCategory.addEventListener('change', (e) => {
                loadSubcategoriesIntoProductModal(e.target.value);
            });
        }

        const pmSearch = document.getElementById('pmSearch');
        if (pmSearch) {
            pmSearch.addEventListener('input', renderProductTable);
        }
    });


    window.saveCategory = async function() {
        const name = document.getElementById('pmCategoryName').value.trim();
        if (!name) {
            alert('Please enter a category name');
            return;
        }
        try {
            const data = await window.apiRequest('/api/categories', {
                method: 'POST',
                body: JSON.stringify({ name })
            });
            if (data && data.success) {
                document.getElementById('pmCategoryName').value = '';
                await loadCategories();  // refreshes tabs + both dropdowns
                await loadProducts();    // update per-category counts in tabs
                window.closeModal('addCategoryModal');
            } else {
                alert(data?.error || 'Failed to add category');
            }
        } catch (e) {
            alert('Error adding category');
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

        try {
            const data = await window.apiRequest('/api/subcategories', {
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
            } else {
                alert(data?.error || 'Failed to add subcategory');
            }
        } catch (e) {
            alert('Error adding subcategory');
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

        try {
            const data = await window.apiRequest('/api/products', {
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
                window.resetProductModal();
                window.closeModal('addProductModal');
            } else {
                alert(data?.error || 'Failed to add product');
            }
        } catch (e) {
            alert('Error adding product');
        }
    };

    window.deleteProduct = async function(productId) {
        if (!confirm('Delete this product? This action cannot be undone.')) return;
        try {
            const data = await window.apiRequest(`/api/products/${productId}`, { method: 'DELETE' });
            if (data && data.success) {
                await loadProducts();
                await loadCategories();
            } else {
                alert(data?.error || 'Failed to delete product');
            }
        } catch (e) {
            alert('Error deleting product');
        }
    };

    window.resetProductModal = function() {
        editingProductId = null;

        const title = document.getElementById('addProductModalTitle');
        const btn   = document.getElementById('addProductModalBtn');

        if (title) title.innerText = 'Add New Product';
        if (btn) {
            btn.innerText = 'Save Product';
            btn.onclick   = window.saveProduct;   // ← always point back to Add
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

    // ============================================
    // Render search filter
    // ============================================


    // ============================================
    // HTML escape helper
    // ============================================
    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/[&<>"']/g, m => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[m]));
    }

    // Global variable to track which product is being edited
    let editingProductId = null;

    // Called when clicking the Edit button (pencil icon)
    window.editProduct = async function(productId) {
        const product = products.find(p => p.id === productId);
        if (!product) {
            alert('Product not found');
            return;
        }
        editingProductId = productId;
        
        document.getElementById('addProductModalTitle').innerText = 'Edit Product';
        document.getElementById('addProductModalBtn').innerText = 'Update Product';
        const saveBtn = document.getElementById('addProductModalBtn');
        saveBtn.onclick = window.updateProduct;
        
        document.getElementById('pmProductName').value = product.name;
        document.getElementById('pmProductCategory').value = product.category_id;
        
        loadSubcategoriesIntoProductModal(product.category_id);
        setTimeout(() => {
            if (product.subcategory_id) {
                document.getElementById('pmProductSubcategory').value = product.subcategory_id;
            }
        }, 100);
        
        document.getElementById('pmProductUnit').value = product.unit;
        document.getElementById('pmProductHsn').value = product.hsn_code || '';
        document.getElementById('pmProductGst').value = product.gst_rate;
        
        window.openModal('addProductModal');
    };

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
        
        try {
            const data = await window.apiRequest(`/api/products/${editingProductId}`, {
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
                await loadProducts();
                window.closeModal('addProductModal');
                window.resetProductModal();
                alert('Product updated successfully');
            } else {
                alert(data?.error || 'Failed to update product');
            }
        } catch (e) {
            alert('Error updating product');
        }
    };

    // ============================================
    // Initialisation
    // ============================================
    async function initProductMaster() {
        await loadCategories();
        await loadProducts();
    }

    document.addEventListener('DOMContentLoaded', () => {
        // Initialize if dashboard is loaded
        initProductMaster();
    });

})();
