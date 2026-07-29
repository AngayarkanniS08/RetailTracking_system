(function () {
    'use strict';

    // ============================================
    // API Helper with port 8081 & JWT support
    // ============================================
    const API_BASE = `${window.location.protocol}//${window.location.hostname}:8081`;

    async function apiRequest(path, options = {}) {
        const token = localStorage.getItem('auth_token');
        const url = path.startsWith('http') ? path : `${API_BASE}${path}`;
        const headers = {
            'Content-Type': 'application/json',
            ...(token ? { Authorization: `Bearer ${token}` } : {}),
            ...options.headers,
        };
        const res = await fetch(url, { ...options, headers });
        if (res.status === 401) {
            localStorage.removeItem('auth_token');
            alert('Your session has expired. Please log in again to continue.');
            window.location.href = '/login';
            throw new Error('Session expired (401)');
        }
        if (!res.ok) {
            const err = await res.json().catch(() => ({}));
            throw new Error(err.error || 'API request failed');
        }
        return res.json();
    }
    window.apiRequest = apiRequest;

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
            const catList = Array.isArray(data) ? data : (data?.data || []);
            categories = catList;
            renderCategoryTabs();
            populateCategoryDropdowns();    // product-add modal
            populateSubcategoryDropdown();  // sub-category section in manage modal
            updateStats();
        } catch (e) {
            console.error('Error loading categories:', e);
        }
    }

    async function loadProducts() {
        try {
            const data = await window.apiRequest('/api/products');
            const prodList = Array.isArray(data) ? data : (data?.data || []);
            products = prodList;
            renderProductTable();
            renderCategoryTabs();  // update per-category counts
            updateStats();
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
        
        // Clear existing filters securely
        container.innerHTML = '';

        // Create "All" filter button
        const allBtn = document.createElement('button');
        allBtn.className = `cat-btn ${activeCategoryFilter === 'all' ? 'active' : ''}`;
        allBtn.addEventListener('click', () => setCategoryFilter('all'));
        
        const allText = document.createTextNode('All ');
        allBtn.appendChild(allText);
        
        const allSpan = document.createElement('span');
        allSpan.className = 'product-count';
        allSpan.textContent = products.length;
        allBtn.appendChild(allSpan);
        
        container.appendChild(allBtn);

        // Create a filter button for each category
        categories.forEach(cat => {
            const count = products.filter(p => p.category_id === cat.id).length;
            
            const catBtn = document.createElement('button');
            catBtn.className = `cat-btn ${activeCategoryFilter === cat.id ? 'active' : ''}`;
            catBtn.addEventListener('click', () => setCategoryFilter(cat.id));
            
            const catText = document.createTextNode(cat.name + ' ');
            catBtn.appendChild(catText);
            
            const catSpan = document.createElement('span');
            catSpan.className = 'product-count';
            catSpan.textContent = count;
            catBtn.appendChild(catSpan);
            
            container.appendChild(catBtn);
        });
    }

    function renderProductTable() {
        const tableContainer = document.getElementById('productMasterTableContainer');
        const emptyState = document.getElementById('pmEmptyState');
        const tbody = document.querySelector('#productMasterTable tbody');
        if (!tbody) return;
        
        // Clear table body
        tbody.innerHTML = '';

        const query = document.getElementById('pmSearch')?.value.toLowerCase().trim() || '';
        
        let filtered = products;
        if (activeCategoryFilter !== 'all') {
            filtered = filtered.filter(p => p.category_id === activeCategoryFilter);
        }
        if (query) {
            filtered = filtered.filter(p => 
                (p.name && p.name.toLowerCase().includes(query)) || 
                (p.hsn_code && p.hsn_code.toLowerCase().includes(query)) ||
                (p.category_name && p.category_name.toLowerCase().includes(query)) ||
                (p.subcategory_name && p.subcategory_name.toLowerCase().includes(query)) ||
                (p.display_id && String(p.display_id).includes(query)) || 
                (p.id && p.id.toLowerCase().includes(query))
            );
        }
        
        if (filtered.length === 0) {
            if (tableContainer) tableContainer.style.display = 'none';
            if (emptyState) {
                emptyState.style.display = 'block';
                const titleEl = document.getElementById('emptyStateTitle');
                const subEl = document.getElementById('emptyStateSub');

                if (products.length === 0) {
                    if (titleEl) titleEl.textContent = 'No Products in Catalog';
                    if (subEl) subEl.textContent = 'Your inventory catalog is currently empty. Get started by adding your first product or category.';
                } else {
                    if (titleEl) titleEl.textContent = 'No Matching Products';
                    if (subEl) subEl.textContent = 'No products match your search query or active filter. Try resetting your search or filter.';
                }
            }
            return;
        }

        if (tableContainer) tableContainer.style.display = 'block';
        if (emptyState) emptyState.style.display = 'none';

        filtered.forEach(p => {
            const tr = document.createElement('tr');
            tr.style.borderBottom = '1px solid var(--border, #f1f5f9)';

            // Product ID
            const tdId = document.createElement('td');
            tdId.style.padding = '14px 20px';
            tdId.style.fontWeight = '500';
            tdId.style.fontSize = '0.85rem';
            tdId.style.color = 'var(--muted)';
            tdId.textContent = p.display_id ? '#' + p.display_id : (p.id ? '#' + p.id.slice(0, 8) : '-');
            tr.appendChild(tdId);

            // Product Name
            const tdName = document.createElement('td');
            tdName.style.padding = '14px 20px';
            const nameSpan = document.createElement('span');
            nameSpan.style.fontWeight = '600';
            nameSpan.style.color = 'var(--text-strong)';
            nameSpan.textContent = p.name || '';
            tdName.appendChild(nameSpan);
            tr.appendChild(tdName);

            // Category
            const tdCat = document.createElement('td');
            tdCat.style.padding = '14px 20px';
            if (p.category_name) {
                const badge = document.createElement('span');
                badge.className = 'badge';
                badge.textContent = p.category_name;
                tdCat.appendChild(badge);
            } else {
                tdCat.textContent = '-';
            }
            tr.appendChild(tdCat);

            // Subcategory
            const tdSubcat = document.createElement('td');
            tdSubcat.style.padding = '14px 20px';
            if (p.subcategory_name) {
                const subBadge = document.createElement('span');
                subBadge.className = 'sub-badge';
                subBadge.textContent = p.subcategory_name;
                tdSubcat.appendChild(subBadge);
            } else {
                tdSubcat.textContent = '-';
            }
            tr.appendChild(tdSubcat);

            // Unit
            const tdUnit = document.createElement('td');
            tdUnit.style.padding = '14px 20px';
            const unitBadge = document.createElement('span');
            unitBadge.style.fontSize = '0.8rem';
            unitBadge.style.padding = '2px 8px';
            unitBadge.style.borderRadius = '4px';
            unitBadge.style.background = 'var(--bg-hover)';
            unitBadge.style.border = '1px solid var(--border)';
            unitBadge.textContent = p.unit || '-';
            tdUnit.appendChild(unitBadge);
            tr.appendChild(tdUnit);

            // HSN Code
            const tdHsn = document.createElement('td');
            tdHsn.style.padding = '14px 20px';
            tdHsn.style.fontSize = '0.875rem';
            tdHsn.textContent = p.hsn_code || '-';
            tr.appendChild(tdHsn);

            // GST Rate
            const tdGst = document.createElement('td');
            tdGst.style.padding = '14px 20px';
            tdGst.style.fontSize = '0.875rem';
            tdGst.textContent = (p.gst_rate !== undefined && p.gst_rate !== null ? p.gst_rate : '0') + '%';
            tr.appendChild(tdGst);

            // Action Buttons
            const tdActions = document.createElement('td');
            tdActions.style.padding = '14px 20px';
            tdActions.style.textAlign = 'right';
            const actionDiv = document.createElement('div');
            actionDiv.className = 'action-buttons';
            actionDiv.style.justifyContent = 'flex-end';

            // Edit button
            const editBtn = document.createElement('button');
            editBtn.className = 'btn-icon edit-btn';
            editBtn.title = 'Edit product';
            editBtn.addEventListener('click', () => editProduct(p.id));
            editBtn.innerHTML = `
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 20h9"></path>
                    <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"></path>
                </svg>
            `;
            actionDiv.appendChild(editBtn);

            // Delete button
            const deleteBtn = document.createElement('button');
            deleteBtn.className = 'btn-icon delete-btn';
            deleteBtn.title = 'Delete product';
            deleteBtn.addEventListener('click', () => deleteProduct(p.id));
            deleteBtn.innerHTML = `
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="3 6 5 6 21 6"></polyline>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                </svg>
            `;
            actionDiv.appendChild(deleteBtn);

            tdActions.appendChild(actionDiv);
            tr.appendChild(tdActions);

            tbody.appendChild(tr);
        });
    }

    function updateStats() {
        const elProducts      = document.getElementById('pmTotalProducts');
        const elCategories    = document.getElementById('pmTotalCategories');
        const elSubcategories = document.getElementById('pmTotalSubcategories');
        const elBatches       = document.getElementById('pmTotalBatches');

        if (elProducts)   elProducts.innerText   = products.length;
        if (elCategories) elCategories.innerText = categories.length;
        
        let subCount = 0;
        products.forEach(p => { if (p.subcategory_id) subCount++; });
        if (elSubcategories) elSubcategories.innerText = subCount;
    }

    /** Populates the Category dropdown inside the "Add Product" modal */
    function populateCategoryDropdowns() {
        const catSelect = document.getElementById('pmProductCategory');
        if (!catSelect) return;
        
        catSelect.innerHTML = '';
        categories.forEach(cat => {
            const opt = document.createElement('option');
            opt.value = cat.id;
            opt.textContent = cat.name;
            catSelect.appendChild(opt);
        });

        // Also trigger subcategory load for the first category
        if (categories.length > 0) {
            loadSubcategoriesIntoProductModal(catSelect.value);
        }
    }

    /** Populates the "Select Category" dropdown in the Manage Modal subcategory section */
    function populateSubcategoryDropdown() {
        const select = document.getElementById('pmSubCatParent');
        if (!select) return;
        
        select.innerHTML = '';
        categories.forEach(cat => {
            const opt = document.createElement('option');
            opt.value = cat.id;
            opt.textContent = cat.name;
            select.appendChild(opt);
        });
    }

    /** Loads subcategories for the selected category into the product add modal */
    async function loadSubcategoriesIntoProductModal(categoryId) {
        const subSelect = document.getElementById('pmProductSubcategory');
        if (!subSelect || !categoryId) return;

        try {
            const data = await window.apiRequest(`/api/subcategories?category_id=${categoryId}`);
            
            subSelect.innerHTML = '';
            
            const defaultOpt = document.createElement('option');
            defaultOpt.value = '';
            defaultOpt.textContent = 'No Subcategory';
            subSelect.appendChild(defaultOpt);

            if (data && !data.error && data.length > 0) {
                data.forEach(sub => {
                    const opt = document.createElement('option');
                    opt.value = sub.id;
                    opt.textContent = sub.name;
                    subSelect.appendChild(opt);
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
            alert(e.message || 'Error adding category');
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
            alert(e.message || 'Error adding subcategory');
        }
    };

    // Helpers for retrieving category and subcategory values from dropdowns or comboboxes
    function getSelectedCategory() {
        const sel = document.getElementById('pmProductCategory');
        if (sel && sel.value) return sel.value;
        const hidden = document.getElementById('pmProductCategoryId');
        if (hidden && hidden.value) return hidden.value;
        return '';
    }

    function setSelectedCategory(catId, catName) {
        const sel = document.getElementById('pmProductCategory');
        if (sel) sel.value = catId || '';
        const hidden = document.getElementById('pmProductCategoryId');
        if (hidden) hidden.value = catId || '';
        const input = document.getElementById('pmProductCategoryInput');
        if (input) input.value = catName || '';
    }

    function getSelectedSubcategory() {
        const sel = document.getElementById('pmProductSubcategory');
        if (sel && sel.value) return sel.value;
        const hidden = document.getElementById('pmProductSubcategoryId');
        if (hidden && hidden.value) return hidden.value;
        return null;
    }

    function setSelectedSubcategory(subId, subName) {
        const sel = document.getElementById('pmProductSubcategory');
        if (sel) sel.value = subId || '';
        const hidden = document.getElementById('pmProductSubcategoryId');
        if (hidden) hidden.value = subId || '';
        const input = document.getElementById('pmProductSubcategoryInput');
        if (input) input.value = subName || '';
    }

    window.saveProduct = async function() {
        const name          = document.getElementById('pmProductName')?.value.trim() || '';
        const categoryId    = getSelectedCategory();
        const subcategoryId = getSelectedSubcategory();
        const unit          = document.getElementById('pmProductUnit')?.value || 'pcs';
        const hsn           = document.getElementById('pmProductHsn')?.value.trim() || '';
        const gst           = parseFloat(document.getElementById('pmProductGst')?.value) || 0;

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

            if (data && (data.success || data.id || data.product)) {
                await loadProducts();
                await loadCategories();
                window.resetProductModal();
                if (window.closeModal) window.closeModal('addProductModal');
            } else {
                alert(data?.error || 'Failed to add product');
            }
        } catch (e) {
            alert(e.message || 'Error adding product');
        }
    };

    let pendingDeleteProductId = null;

    window.deleteProduct = function(productId) {
        const product = products.find(p => p.id === productId);
        if (!product) return;

        pendingDeleteProductId = productId;
        const nameEl = document.getElementById('deleteProductName');
        if (nameEl) nameEl.textContent = product.name;

        const catEl = document.getElementById('deleteProductCategory');
        if (catEl) catEl.textContent = product.category_name || 'General';

        const idEl = document.getElementById('deleteProductId');
        if (idEl) idEl.textContent = product.display_id ? `#${product.display_id}` : `#${product.id.slice(0, 6)}`;

        const confirmBtn = document.getElementById('confirmDeleteBtn');
        if (confirmBtn) {
            confirmBtn.onclick = async function() {
                if (!pendingDeleteProductId) return;
                try {
                    confirmBtn.disabled = true;
                    confirmBtn.textContent = 'Deleting...';
                    const data = await window.apiRequest(`/api/products/${pendingDeleteProductId}`, { method: 'DELETE' });
                    if (data && data.success) {
                        if (window.closeModal) window.closeModal('deleteProductModal');
                        await loadProducts();
                        await loadCategories();
                    } else {
                        alert(data?.error || 'Failed to delete product');
                    }
                } catch (e) {
                    alert('Error deleting product');
                } finally {
                    confirmBtn.disabled = false;
                    confirmBtn.innerHTML = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:4px;"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg> Delete Product`;
                    pendingDeleteProductId = null;
                }
            };
        }

        if (window.openModal) {
            window.openModal('deleteProductModal');
        } else {
            const modal = document.getElementById('deleteProductModal');
            if (modal) modal.classList.add('active');
        }
    };

    window.resetProductModal = function() {
        editingProductId = null;

        const title = document.getElementById('addProductModalTitle');
        const btn   = document.getElementById('addProductModalBtn');

        if (title) title.innerText = 'Add New Product';
        if (btn) {
            btn.innerText = 'Save Product';
            btn.onclick   = window.saveProduct;
        }

        // Clear form fields
        const nameEl = document.getElementById('pmProductName');
        const hsnEl  = document.getElementById('pmProductHsn');
        const gstEl  = document.getElementById('pmProductGst');
        if (nameEl) nameEl.value = '';
        if (hsnEl)  hsnEl.value  = '';
        if (gstEl)  gstEl.value  = '';

        setSelectedCategory('', '');
        setSelectedSubcategory('', '');
    };

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
        
        const titleEl = document.getElementById('addProductModalTitle');
        const saveBtn = document.getElementById('addProductModalBtn');
        if (titleEl) titleEl.innerText = 'Edit Product';
        if (saveBtn) {
            saveBtn.innerText = 'Update Product';
            saveBtn.onclick = window.updateProduct;
        }
        
        const nameEl = document.getElementById('pmProductName');
        const unitEl = document.getElementById('pmProductUnit');
        const hsnEl  = document.getElementById('pmProductHsn');
        const gstEl  = document.getElementById('pmProductGst');

        if (nameEl) nameEl.value = product.name || '';
        if (unitEl) unitEl.value = product.unit || 'pcs';
        if (hsnEl)  hsnEl.value  = product.hsn_code || '';
        if (gstEl)  gstEl.value  = product.gst_rate !== undefined ? product.gst_rate : '';
        
        setSelectedCategory(product.category_id, product.category_name);
        setSelectedSubcategory(product.subcategory_id, product.subcategory_name);

        if (window.openModal) {
            window.openModal('addProductModal');
        } else {
            const modal = document.getElementById('addProductModal');
            if (modal) modal.classList.add('active');
        }
    };

    window.updateProduct = async function() {
        if (!editingProductId) return;

        const name          = document.getElementById('pmProductName')?.value.trim() || '';
        const categoryId    = getSelectedCategory();
        const subcategoryId = getSelectedSubcategory();
        const unit          = document.getElementById('pmProductUnit')?.value || 'pcs';
        const hsn           = document.getElementById('pmProductHsn')?.value.trim() || '';
        const gst           = parseFloat(document.getElementById('pmProductGst')?.value) || 0;
        
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
            
            if (data && (data.success || data.id || data.product)) {
                await loadProducts();
                if (window.closeModal) window.closeModal('addProductModal');
                window.resetProductModal();
            } else {
                alert(data?.error || 'Failed to update product');
            }
        } catch (e) {
            alert(e.message || 'Error updating product');
        }
    };

    // ============================================
    // Initialisation
    // ============================================
    async function initProductMaster() {
        await loadCategories();
        await loadProducts();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            if (document.getElementById('product_master')) initProductMaster();
        });
    } else {
        if (document.getElementById('product_master')) initProductMaster();
    }

})();
