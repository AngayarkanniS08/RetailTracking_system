// ============================================
// Global state
// ============================================
let categories = [];
let products = [];


// ============================================
// 2. Fetch data from API (always re-fetches)
// ============================================
async function loadCategories() {
    const data = await apiRequest('/api/categories');
    if (data && !data.error) {
        categories = data;
        populateCategoryDropdowns();    // product-add modal
        populateSubcategoryDropdown();  // sub-category section in manage modal
        updateStats();
    }
}
let currentPage = 1;
let currentSearch = '';
let totalPages = 1;

async function loadProducts(page = 1) {
    currentPage = page;
    let url = `/api/products?page=${page}&limit=4`;
    if (currentSearch) {
        url += `&search=${encodeURIComponent(currentSearch)}`;
    }
    try {
        const data = await window.apiRequest(url);
        if (data && !data.error) {
            products = data.data;               // replace global products array with current page's products
            totalPages = data.pagination.total_pages;
            renderProductTable();               // re-render table with new products
            renderPaginationControls(data.pagination); // update buttons
        }
    } catch (err) {
        console.error('Error loading products:', err);
    }
}



function renderPaginationControls(pagination) {
    let container = document.getElementById('paginationControls');
    if (!container) {
        const accordion = document.querySelector('#productAccordionContainer');
        if (accordion && accordion.parentNode) {
            const div = document.createElement('div');
            div.id = 'paginationControls';
            div.className = 'pagination';
            accordion.parentNode.insertBefore(div, accordion.nextSibling);
            container = div;
        } else return;
    }

    if (pagination.total_pages <= 1) {
        container.style.display = 'none';
        return;
    }
    container.style.display = 'flex';
    container.innerHTML = `
        <button class="pagination-btn" id="prevPageBtn" ${!pagination.has_prev ? 'disabled' : ''}>← Previous</button>
        <span class="pagination-info">Page ${pagination.current_page} of ${pagination.total_pages}</span>
        <button class="pagination-btn" id="nextPageBtn" ${!pagination.has_next ? 'disabled' : ''}>Next →</button>
    `;

    document.getElementById('prevPageBtn')?.addEventListener('click', () => {
        if (pagination.has_prev) loadProducts(pagination.current_page - 1);
    });
    document.getElementById('nextPageBtn')?.addEventListener('click', () => {
        if (pagination.has_next) loadProducts(pagination.current_page + 1);
    });
}
// ============================================
// 3. Render UI
// ============================================
let collapsedCategoriesState = {};

function getSubcategoryColor(name) {
    if (!name) return 'var(--muted)';
    const lower = name.toLowerCase().trim();
    if (lower === 'lining') {
        return '#f97316'; // Vivid orange
    }
    if (lower === 'cotton' || lower === 'pure cotton') return '#10b981'; // emerald green
    if (lower === 'silk' || lower === 'art silk') return '#ec4899'; // pink
    if (lower === 'fancy' || lower === 'designer') return '#3b82f6'; // blue
    if (lower === 'lace' || lower === 'lace work') return '#06b6d4'; // cyan
    if (lower === 'border') return '#f59e0b'; // amber

    // Generate a hash-based color if not matched (avoiding violet/purple hues to respect Purple Ban)
    let hash = 0;
    for (let i = 0; i < name.length; i++) {
        hash = name.charCodeAt(i) + ((hash << 5) - hash);
    }
    const hues = [200, 140, 30, 45, 180, 340, 15]; // Selected nice hues: blue, emerald, orange, amber, cyan, rose, reddish-orange
    const hue = hues[Math.abs(hash) % hues.length];
    return `hsl(${hue}, 75%, 45%)`;
}

function renderProductTable() {
    const container = document.getElementById('productAccordionContainer');
    if (!container) return;

    // Clear accordion container securely
    container.innerHTML = '';

    // Products are already filtered server-side by search and category.
    // No client-side filtering needed — just group and render.
    let filtered = products;

    // Step 2: Group ALL filtered products by Category and Subcategory
    const groupedAll = {};
    filtered.forEach(p => {
        let catId = p.category_id;
        if (!groupedAll[catId]) {
            const catObj = categories.find(c => c.id === catId);
            const catName = catObj ? catObj.name : (p.category_name || 'Uncategorized');
            groupedAll[catId] = {
                id: catId,
                name: catName,
                subcategories: {}
            };
        }

        let subId = p.subcategory_id;
        if (!subId) {
            if (!groupedAll[catId].subcategories['none']) {
                groupedAll[catId].subcategories['none'] = {
                    id: null,
                    name: 'Other Products',
                    products: []
                };
            }
            groupedAll[catId].subcategories['none'].products.push(p);
        } else {
            if (!groupedAll[catId].subcategories[subId]) {
                groupedAll[catId].subcategories[subId] = {
                    id: subId,
                    name: p.subcategory_name || 'Other',
                    products: []
                };
            }
            groupedAll[catId].subcategories[subId].products.push(p);
        }
    });

    const activeCategoriesList = Object.values(groupedAll).sort((a, b) => a.name.localeCompare(b.name));

    // Step 3: Render all active categories accordion UI
    let totalRenderedProducts = 0;

    activeCategoriesList.forEach(cat => {
        // Count total products in this category
        let catProductCount = 0;
        Object.values(cat.subcategories).forEach(sub => {
            catProductCount += sub.products.length;
        });

        // Hide empty categories (as requested: "if there is no product under a category it should not show in product list")
        if (catProductCount === 0) {
            return;
        }

        totalRenderedProducts += catProductCount;

        const catAccordion = document.createElement('div');
        catAccordion.className = 'category-accordion';

        // Check collapse state (auto-expand during search, else check tracked state)
        const isCollapsed = collapsedCategoriesState[cat.id] === true;

        const catHeader = document.createElement('div');
        catHeader.className = `category-header ${!isCollapsed ? 'active' : ''}`;
        catHeader.innerHTML = `
            <div class="category-title-area">
                <span class="category-name">${escapeHtml(cat.name)}</span>
                <span class="category-count">${catProductCount} Product${catProductCount !== 1 ? 's' : ''}</span>
            </div>
            <svg class="category-chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="6 9 12 15 18 9"></polyline>
            </svg>
        `;

        const catContent = document.createElement('div');
        catContent.className = `category-content ${isCollapsed ? 'collapsed' : ''}`;

        // Toggle Category collapse
        catHeader.addEventListener('click', () => {
            const currentlyCollapsed = catContent.classList.contains('collapsed');
            if (currentlyCollapsed) {
                catHeader.classList.add('active');
                catContent.classList.remove('collapsed');
                collapsedCategoriesState[cat.id] = false;
            } else {
                catHeader.classList.remove('active');
                catContent.classList.add('collapsed');
                collapsedCategoriesState[cat.id] = true;
            }
        });

        // Render Subcategories
        const subcategories = Object.values(cat.subcategories);
        // Sort subcategories: put 'none' at the bottom, others alphabetical
        subcategories.sort((a, b) => {
            if (a.id === null) return 1;
            if (b.id === null) return -1;
            return a.name.localeCompare(b.name);
        });

        subcategories.forEach(sub => {
            // Hide subcategories with 0 products
            if (sub.products.length === 0) return;

            const subSection = document.createElement('div');
            subSection.className = 'subcategory-section';

            const accentColor = getSubcategoryColor(sub.name);
            subSection.style.borderLeftColor = accentColor;

            const subHeader = document.createElement('div');
            subHeader.className = 'subcategory-header';

            const displayName = sub.id === null ? 'Other Products' : sub.name;
            const subProductCount = sub.products.length;

            subHeader.innerHTML = `
                <div class="subcategory-title-area">
                    <span class="subcategory-name" style="color: ${sub.id !== null ? accentColor : 'var(--text)'}">${escapeHtml(displayName)}</span>
                    <span class="subcategory-count">${subProductCount}</span>
                </div>
            `;
            subSection.appendChild(subHeader);

            const tableContainer = document.createElement('div');
            tableContainer.className = 'table-container';

            const table = document.createElement('table');
            table.className = 'subcategory-table';
            table.innerHTML = `
                <thead>
                    <tr>
                        <th style="width: 15%">ID</th>
                        <th style="width: 45%">Product Name</th>
                        <th style="width: 12%">Unit</th>
                        <th style="width: 13%">HSN Code</th>
                        <th style="width: 10%">GST (%)</th>
                        <th style="width: 5%">Action</th>
                    </tr>
                </thead>
                <tbody></tbody>
            `;

            const tableBody = table.querySelector('tbody');
            sub.products.forEach(p => {
                const tr = document.createElement('tr');

                const tdId = document.createElement('td');
                tdId.textContent = p.display_id ? '#' + p.display_id : (p.id ? p.id.slice(0, 8) : '');
                tr.appendChild(tdId);

                const tdName = document.createElement('td');
                tdName.textContent = p.name || '';
                tr.appendChild(tdName);

                const tdUnit = document.createElement('td');
                tdUnit.textContent = p.unit || '';
                tr.appendChild(tdUnit);

                const tdHsn = document.createElement('td');
                tdHsn.textContent = p.hsn_code || '-';
                tr.appendChild(tdHsn);

                const tdGst = document.createElement('td');
                tdGst.textContent = (p.gst_rate !== undefined ? p.gst_rate : '0') + '%';
                tr.appendChild(tdGst);

                const tdActions = document.createElement('td');
                const actionDiv = document.createElement('div');
                actionDiv.className = 'action-buttons';

                const editBtn = document.createElement('button');
                editBtn.className = 'btn-icon edit-btn';
                editBtn.title = 'Edit product';
                editBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    editProduct(p.id);
                });
                editBtn.innerHTML = `
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 20h9"></path>
                        <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"></path>
                    </svg>
                `;
                actionDiv.appendChild(editBtn);

                const deleteBtn = document.createElement('button');
                deleteBtn.className = 'btn-icon delete-btn';
                deleteBtn.title = 'Delete product';
                deleteBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    deleteProduct(p.id);
                });
                deleteBtn.innerHTML = `
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    </svg>
                `;
                actionDiv.appendChild(deleteBtn);

                tdActions.appendChild(actionDiv);
                tr.appendChild(tdActions);
                tableBody.appendChild(tr);
            });

            tableContainer.appendChild(table);
            subSection.appendChild(tableContainer);
            catContent.appendChild(subSection);
        });

        catAccordion.appendChild(catHeader);
        catAccordion.appendChild(catContent);
        container.appendChild(catAccordion);
    });

    if (activeCategoriesList.length === 0) {
        const emptyDiv = document.createElement('div');
        emptyDiv.className = 'card-panel';
        emptyDiv.style.textAlign = 'center';
        emptyDiv.style.color = 'var(--text-muted)';
        emptyDiv.style.padding = '3rem';
        emptyDiv.textContent = 'No matching products found under your categories.';
        container.appendChild(emptyDiv);
    }

}

function updateStats() {
    const elProducts = document.getElementById('pmTotalProducts');
    const elCategories = document.getElementById('pmTotalCategories');
    const totalAllCount = categories.reduce((sum, cat) => sum + parseInt(cat.product_count || 0), 0);
    if (elProducts) elProducts.innerText = totalAllCount;
    if (elCategories) elCategories.innerText = categories.length;
}

/** Populates the Category combobox inside the "Add Product" modal */
function populateCategoryDropdowns() {
    if (categoryCombobox) {
        categoryCombobox.loadItems(categories);
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

    const data = await apiRequest(`/api/subcategories?category_id=${categoryId}`);

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
}

// ============================================
// 4. CRUD actions
// ============================================
let previousSearch = '';
const pmSearch = document.getElementById('pmSearch');
if (pmSearch) {
    pmSearch.addEventListener('input', (e) => {
        const searchTerm = e.target.value;
        if (searchTerm !== previousSearch) {
            previousSearch = searchTerm;
            currentSearch = searchTerm;
            currentPage = 1;
            loadProducts(1);
        }
    });
}

/** Called when category selection changes in Add Product modal */
window.onCategoryChange = function (categoryId) {
    if (subcategoryCombobox) {
        subcategoryCombobox.loadForCategory(categoryId);
    } else {
        loadSubcategoriesIntoProductModal(categoryId);
    }
};

window.saveCategory = async function () {
    const name = document.getElementById('pmCategoryName').value.trim();
    if (!name) {
        alert('Please enter a category name');
        return;
    }
    try {
        const data = await apiRequest('/api/categories', {
            method: 'POST',
            body: JSON.stringify({ name })
        });
        if (data && data.success) {
            document.getElementById('pmCategoryName').value = '';
            await loadCategories();  // refreshes both dropdowns
            await loadProducts();
            closeModal('addCategoryModal');
        }
    } catch (e) {
        alert(e.message || 'Failed to add category');
    }
};

window.saveSubcategory = async function () {
    const categoryId = document.getElementById('pmSubCatParent')?.value;
    const subName = document.getElementById('pmSubCategoryName').value.trim();

    if (!categoryId) {
        alert('Please select a parent category');
        return;
    }
    if (!subName) {
        alert('Please enter a subcategory name');
        return;
    }

    try {
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
                // Also refresh the combobox (clears its cache)
                if (typeof subcategoryCombobox !== 'undefined' && subcategoryCombobox) {
                    delete subcategoryCombobox.cache[categoryId];
                    subcategoryCombobox.loadForCategory(categoryId);
                }
            }
            // Keep manage modal open so user can add more
        }
    } catch (e) {
        alert(e.message || 'Failed to add subcategory');
    }
};

window.saveProduct = async function () {
    const name = document.getElementById('pmProductName').value.trim();
    const categoryId = document.getElementById('pmProductCategoryId').value;
    const subcategoryId = document.getElementById('pmProductSubcategoryId').value || null;
    const unit = document.getElementById('pmProductUnit').value;
    const hsn = document.getElementById('pmProductHsn').value.trim();
    const gst = parseFloat(document.getElementById('pmProductGst').value) || 0;

    if (!name || !categoryId || !unit) {
        alert('Product name, category, and unit are required');
        return;
    }

    if (!Number.isFinite(gst) || gst < 0 || gst > 100) {
        alert('GST rate must be between 0% and 100%');
        return;
    }

    if (hsn && !/^\d{4}(\d{2})?(\d{2})?$/.test(hsn)) {
        alert('HSN code must be 4, 6 or 8 digits');
        return;
    }

    const data = await apiRequest('/api/products', {
        method: 'POST',
        body: JSON.stringify({
            name,
            category_id: categoryId,
            subcategory_id: subcategoryId || null,
            unit,
            hsn_code: hsn || null,
            gst_rate: gst
        })
    });

    if (data && data.success) {
        await loadProducts(currentPage);
        await loadCategories();
        resetProductModal();
        closeModal('addProductModal');
    } else {
        alert(data?.error || 'Failed to add product');
    }
};

// ============================================
// 5. Render search filter
// ============================================
window.renderProductMaster = function () {
    renderProductTable();
};

// ============================================
// 6. HTML escape helper
// ============================================
function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>"']/g, m => {
        switch (m) {
            case '&': return '&amp;';
            case '<': return '&lt;';
            case '>': return '&gt;';
            case '"': return '&quot;';
            case "'": return '&#39;';
            default: return m;
        }
    });
}

// ============================================
// 7. Initialisation — always re-fetches fresh data
// ============================================
async function initProductMaster() {
    await loadCategories();
    await loadProducts(1);
}

// Tracks which product id is awaiting delete confirmation
let pendingDeleteProductId = null;

window.deleteProduct = function (productId) {
    const product = products.find(p => p.id === productId);
    if (!product) return;
    pendingDeleteProductId = productId;
    const nameSpan = document.getElementById('deleteProductName');
    if (nameSpan) nameSpan.innerText = product.name;
    openModal('deleteProductModal');
};

// DOMContentLoaded: ALWAYS pre-load data so categories survive page refresh.
// Sidebar.js also calls initProductMaster() on every navigation to product_master.
document.addEventListener('DOMContentLoaded', () => {
    // Only run initialization if the product_master section exists in the DOM
    if (!document.getElementById('product_master')) {
        return;
    }

    // Wire the Save/Update button — onclick attribute removed from HTML to avoid double-fire
    const btn = document.getElementById('addProductModalBtn');
    if (btn) btn.onclick = saveProduct;

    initProductMaster();

    // Real-time HSN validation
    const hsnInput = document.getElementById('pmProductHsn');
    const hsnError = document.getElementById('pmHsnError');
    if (hsnInput && hsnError) {
        hsnInput.addEventListener('input', function() {
            const val = this.value.trim();
            if (val && !/^\d{4}(\d{2})?(\d{2})?$/.test(val)) {
                hsnError.style.display = 'block';
            } else {
                hsnError.style.display = 'none';
            }
        });
    }

    // Handle delete confirmation
    const deleteModal = document.getElementById('deleteProductModal');
    if (deleteModal) {
        deleteModal.addEventListener('click', async (e) => {
            if (e.target.id === 'confirmDeleteBtn' && pendingDeleteProductId) {
                const productId = pendingDeleteProductId;
                const data = await apiRequest(`/api/products/${productId}`, { method: 'DELETE' });
                if (data && data.success) {
                    await loadProducts(currentPage);
                    await loadCategories();
                    closeModal('deleteProductModal');
                    pendingDeleteProductId = null;
                    // Reset modal body to default (optional)
                    const modalBody = document.getElementById('deleteProductModalBody');
                    modalBody.innerHTML = `
                    <p>Are you sure you want to delete the product <strong id="deleteProductName"></strong>?</p>
                    <p class="text-muted" style="font-size: 0.85rem;">Inventory records (stock list, batches) will be permanently deleted. Invoice and purchase history will be preserved.</p>
                `;
                } else {
                    const errorMsg = data?.error || 'Failed to delete product';
                    const modalBody = document.getElementById('deleteProductModalBody');
                    modalBody.innerHTML = `
                    <div style="color: var(--danger); margin-bottom: 1rem;">
                        <strong>Error:</strong> ${escapeHtml(errorMsg)}
                    </div>
                    <p>Some records are still linked to this product. Please contact support.</p>
                `;
                    const modalFooter = deleteModal.querySelector('.modal-footer');
                    if (modalFooter) {
                        modalFooter.innerHTML = `
                            <button class="btn btn-outline" onclick="closeModal('deleteProductModal')">Cancel</button>
                            <button class="btn btn-danger" onclick="deleteProduct('${productId}')">Retry Delete</button>
                        `;
                    }
                }
            }
        });
    }
});

// Global variable to track which product is being edited
let editingProductId = null;

// Called when clicking the Edit button (pencil icon)
window.editProduct = async function (productId) {
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
    if (categoryCombobox) {
        categoryCombobox.hidden.value = product.category_id;
        categoryCombobox.input.value = product.category_name || '';
        categoryCombobox.closeDropdown();
    }

    if (subcategoryCombobox) {
        await subcategoryCombobox.loadForCategory(product.category_id);
        if (product.subcategory_id) {
            subcategoryCombobox.hidden.value = product.subcategory_id;
            // Find name from loaded items to set input text
            const found = subcategoryCombobox.allItems.find(i => i.id === product.subcategory_id);
            if (found) subcategoryCombobox.input.value = found.name;
        }
    } else {
        // Trigger subcategory dropdown population based on selected category (fallback)
        if (typeof onCategoryChange === 'function') {
            onCategoryChange(product.category_id);
            // Wait a bit for subcategories to load, then set value
            setTimeout(() => {
                const oldSelect = document.getElementById('pmProductSubcategory');
                if (oldSelect && product.subcategory_id) {
                    oldSelect.value = product.subcategory_id;
                }
            }, 100);
        }
    }
    document.getElementById('pmProductUnit').value = product.unit;
    document.getElementById('pmProductHsn').value = product.hsn_code || '';
    document.getElementById('pmProductGst').value = product.gst_rate;

    openModal('addProductModal');
};

// Called when the modal's save button is in "Update" mode
window.updateProduct = async function () {
    const name = document.getElementById('pmProductName').value.trim();
    const categoryId = document.getElementById('pmProductCategoryId').value;
    const subcategoryId = document.getElementById('pmProductSubcategoryId')?.value || null;
    const unit = document.getElementById('pmProductUnit').value;
    const hsn = document.getElementById('pmProductHsn').value.trim();
    const gst = parseFloat(document.getElementById('pmProductGst').value) || 0;

    if (!name || !categoryId || !unit) {
        alert('Product name, category, and unit are required');
        return;
    }

    if (!Number.isFinite(gst) || gst < 0 || gst > 100) {
        alert('GST rate must be between 0% and 100%');
        return;
    }

    if (hsn && !/^\d{4}(\d{2})?(\d{2})?$/.test(hsn)) {
        alert('HSN code must be 4, 6 or 8 digits');
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
        await loadProducts(currentPage);
        closeModal('addProductModal');
        resetProductModal(); // this function should reset title/button/onclick
        alert('Product updated successfully');
    } else {
        alert(data?.error || 'Failed to update product');
    }
};

// Reset modal back to "Add" mode
window.resetProductModal = function () {
    editingProductId = null;

    const title = document.getElementById('addProductModalTitle');
    const btn = document.getElementById('addProductModalBtn');

    if (title) title.innerText = 'Add New Product';
    if (btn) {
        btn.innerText = 'Save Product';
        btn.onclick = saveProduct;   // ← always point back to Add
    }

    // Clear form fields
    const nameEl = document.getElementById('pmProductName');
    const hsnEl = document.getElementById('pmProductHsn');
    const gstEl = document.getElementById('pmProductGst');
    if (nameEl) nameEl.value = '';
    if (hsnEl) hsnEl.value = '';
    if (gstEl) gstEl.value = '';

    // Reset category combobox and load subcategories
    if (categoryCombobox) {
        categoryCombobox.clear();
    }
    if (subcategoryCombobox) {
        subcategoryCombobox.clear();
    }
};

// ─────────────────────────────────────────────────────────────
// Category Combobox (Client‑Side Searchable)
// ─────────────────────────────────────────────────────────────
class CategoryCombobox {
    constructor(inputId, hiddenId, dropdownId) {
        this.input = document.getElementById(inputId);
        this.hidden = document.getElementById(hiddenId);
        this.dropdown = document.getElementById(dropdownId);
        this.container = this.dropdown ? this.dropdown.parentElement : null;
        this.allItems = [];
        this.filteredItems = [];
        this.selectedIndex = -1;
        this.isOpen = false;

        if (!this.input) return;
        this.initEventListeners();
    }

    initEventListeners() {
        this.input.addEventListener('input', () => this.onInput());
        this.input.addEventListener('focus', () => {
            this.loadItems(categories);
            this.openDropdown();
        });
        this.input.addEventListener('blur', () => setTimeout(() => this.closeDropdown(), 200));
        this.input.addEventListener('keydown', (e) => this.onKeyDown(e));
        document.addEventListener('click', (e) => {
            if (this.dropdown && !this.dropdown.contains(e.target) && e.target !== this.input) {
                this.closeDropdown();
            }
        });
    }

    loadItems(items) {
        this.allItems = items.map(item => ({ id: item.id, name: item.name }));
        this.filteredItems = [...this.allItems];
        this.input.placeholder = this.allItems.length ? "Type to search category..." : "No categories";
    }

    onInput() {
        const search = this.input.value.toLowerCase().trim();
        if (!search) {
            this.filteredItems = [...this.allItems];
        } else {
            this.filteredItems = this.allItems.filter(item =>
                item.name.toLowerCase().includes(search)
            );
        }
        this.selectedIndex = -1;
        this.renderDropdown();
        if (!this.isOpen) this.openDropdown();
    }

    renderDropdown() {
        if (!this.isOpen) return;
        this.dropdown.innerHTML = '';
        if (this.filteredItems.length === 0) {
            const div = document.createElement('div');
            div.className = 'combobox-item';
            div.textContent = 'No matching categories';
            this.dropdown.appendChild(div);
        } else {
            this.filteredItems.forEach((item, idx) => {
                const div = document.createElement('div');
                div.className = `combobox-item ${idx === this.selectedIndex ? 'selected' : ''}`;
                div.textContent = item.name;
                div.addEventListener('mousedown', (e) => {
                    e.preventDefault();
                    this.selectItem(item.id, item.name);
                });
                div.addEventListener('mouseenter', () => this.setSelectedIndex(idx));
                this.dropdown.appendChild(div);
            });
        }
    }

    selectItem(id, name) {
        this.hidden.value = id;
        this.input.value = name;
        this.closeDropdown();
        if (typeof window.onCategoryChange === 'function') {
            window.onCategoryChange(id);
        }
    }

    onKeyDown(e) {
        if (!this.isOpen && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) {
            this.openDropdown();
            e.preventDefault();
            return;
        }
        if (!this.isOpen) return;

        switch (e.key) {
            case 'ArrowDown':
                this.selectedIndex = Math.min(this.selectedIndex + 1, this.filteredItems.length - 1);
                this.renderDropdown();
                this.scrollToSelected();
                e.preventDefault();
                break;
            case 'ArrowUp':
                this.selectedIndex = Math.max(this.selectedIndex - 1, -1);
                this.renderDropdown();
                this.scrollToSelected();
                e.preventDefault();
                break;
            case 'Enter':
                if (this.selectedIndex >= 0 && this.filteredItems[this.selectedIndex]) {
                    const item = this.filteredItems[this.selectedIndex];
                    this.selectItem(item.id, item.name);
                }
                e.preventDefault();
                break;
            case 'Escape':
                this.closeDropdown();
                e.preventDefault();
                break;
        }
    }

    scrollToSelected() {
        const selectedEl = this.dropdown.querySelector('.combobox-item.selected');
        if (selectedEl) selectedEl.scrollIntoView({ block: 'nearest' });
    }

    setSelectedIndex(idx) {
        this.selectedIndex = idx;
        this.renderDropdown();
    }

    openDropdown() {
        this.isOpen = true;
        if (this.container) this.container.classList.add('is-open');
        this.renderDropdown();
    }

    closeDropdown() {
        this.isOpen = false;
        if (this.container) this.container.classList.remove('is-open');
        this.selectedIndex = -1;

        const selectedId = this.hidden.value;
        if (selectedId) {
            const found = this.allItems.find(item => item.id === selectedId);
            if (found) {
                this.input.value = found.name;
            } else {
                this.input.value = '';
                this.hidden.value = '';
            }
        } else {
            this.input.value = '';
        }
    }

    clear() {
        this.allItems = [];
        this.filteredItems = [];
        this.hidden.value = '';
        this.input.value = '';
        this.input.placeholder = 'Type to search category...';
        this.closeDropdown();
    }
}
// ─────────────────────────────────────────────────────────────
// Subcategory Combobox (Client‑Side Searchable)
// ─────────────────────────────────────────────────────────────
class SubcategoryCombobox {
    constructor(inputId, hiddenId, dropdownId) {
        this.input = document.getElementById(inputId);
        this.hidden = document.getElementById(hiddenId);
        this.dropdown = document.getElementById(dropdownId);
        this.container = this.dropdown ? this.dropdown.parentElement : null;
        this.allItems = [];          // full list (id, name)
        this.filteredItems = [];     // after filtering
        this.selectedIndex = -1;
        this.isOpen = false;
        this.currentCategoryId = null;
        this.cache = {};             // categoryId -> subcategories array

        if (!this.input) return;
        this.initEventListeners();
    }

    initEventListeners() {
        this.input.addEventListener('input', () => this.onInput());
        this.input.addEventListener('focus', () => this.openDropdown());
        this.input.addEventListener('blur', () => setTimeout(() => this.closeDropdown(), 200));
        this.input.addEventListener('keydown', (e) => this.onKeyDown(e));
        document.addEventListener('click', (e) => {
            if (!this.dropdown.contains(e.target) && e.target !== this.input) {
                this.closeDropdown();
            }
        });
    }

    async loadForCategory(categoryId) {
        if (!categoryId) {
            this.clear();
            return;
        }
        this.currentCategoryId = categoryId;

        // Use cache if available
        if (this.cache[categoryId]) {
            this.allItems = this.cache[categoryId];
            this.filteredItems = [...this.allItems];
            this.input.placeholder = this.allItems.length ? "Type to search subcategory..." : "No subcategories";
            this.hidden.value = '';
            this.input.value = '';
            if (this.isOpen) this.renderDropdown();
            return;
        }

        // Fetch from API
        this.showLoading(true);
        try {
            const data = await window.apiRequest(`/api/subcategories?category_id=${categoryId}`);
            if (data && !data.error) {
                this.allItems = data.map(item => ({ id: item.id, name: item.name }));
                this.cache[categoryId] = this.allItems;
                this.filteredItems = [...this.allItems];
                this.input.placeholder = this.allItems.length ? "Type to search subcategory..." : "No subcategories";
                this.hidden.value = '';
                this.input.value = '';
                if (this.isOpen) this.renderDropdown();
            }
        } catch (err) {
            console.error('Failed to load subcategories', err);
        } finally {
            this.showLoading(false);
        }
    }

    onInput() {
        const search = this.input.value.toLowerCase().trim();
        if (!search) {
            this.filteredItems = [...this.allItems];
        } else {
            this.filteredItems = this.allItems.filter(item =>
                item.name.toLowerCase().includes(search)
            );
        }
        this.selectedIndex = -1;
        this.renderDropdown();
        if (!this.isOpen) this.openDropdown();
    }

    renderDropdown() {
        if (!this.isOpen) return;
        this.dropdown.innerHTML = '';
        if (this.filteredItems.length === 0) {
            const div = document.createElement('div');
            div.className = 'combobox-item';
            div.textContent = 'No matching subcategories';
            this.dropdown.appendChild(div);
        } else {
            this.filteredItems.forEach((item, idx) => {
                const div = document.createElement('div');
                div.className = `combobox-item ${idx === this.selectedIndex ? 'selected' : ''}`;
                div.textContent = item.name;
                div.addEventListener('mousedown', (e) => {
                    e.preventDefault();
                    this.selectItem(item.id, item.name);
                });
                div.addEventListener('mouseenter', () => this.setSelectedIndex(idx));
                this.dropdown.appendChild(div);
            });
        }
    }

    selectItem(id, name) {
        this.hidden.value = id;
        this.input.value = name;
        this.closeDropdown();
    }

    onKeyDown(e) {
        if (!this.isOpen && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) {
            this.openDropdown();
            e.preventDefault();
            return;
        }
        if (!this.isOpen) return;

        switch (e.key) {
            case 'ArrowDown':
                this.selectedIndex = Math.min(this.selectedIndex + 1, this.filteredItems.length - 1);
                this.renderDropdown();
                this.scrollToSelected();
                e.preventDefault();
                break;
            case 'ArrowUp':
                this.selectedIndex = Math.max(this.selectedIndex - 1, -1);
                this.renderDropdown();
                this.scrollToSelected();
                e.preventDefault();
                break;
            case 'Enter':
                if (this.selectedIndex >= 0 && this.filteredItems[this.selectedIndex]) {
                    const item = this.filteredItems[this.selectedIndex];
                    this.selectItem(item.id, item.name);
                }
                e.preventDefault();
                break;
            case 'Escape':
                this.closeDropdown();
                e.preventDefault();
                break;
        }
    }

    scrollToSelected() {
        const selectedEl = this.dropdown.querySelector('.combobox-item.selected');
        if (selectedEl) selectedEl.scrollIntoView({ block: 'nearest' });
    }

    setSelectedIndex(idx) {
        this.selectedIndex = idx;
        this.renderDropdown();
    }

    openDropdown() {
        this.isOpen = true;
        if (this.container) this.container.classList.add('is-open');
        this.renderDropdown();
    }

    closeDropdown() {
        this.isOpen = false;
        if (this.container) this.container.classList.remove('is-open');
        this.selectedIndex = -1;

        // Revert input text if it doesn't match the selected item
        const selectedId = this.hidden.value;
        if (selectedId) {
            const found = this.allItems.find(item => item.id === selectedId);
            if (found) {
                this.input.value = found.name;
            } else {
                this.input.value = '';
                this.hidden.value = '';
            }
        } else {
            this.input.value = '';
        }
    }

    clear() {
        this.allItems = [];
        this.filteredItems = [];
        this.hidden.value = '';
        this.input.value = '';
        this.input.placeholder = 'Select a category first';
        this.closeDropdown();
    }

    showLoading(show) {
        // You can add a spinner inside the input or just disable temporarily
        this.input.disabled = show;
    }
}

// Initialize the comboboxes after DOM is ready
let subcategoryCombobox = null;
let categoryCombobox = null;

document.addEventListener('DOMContentLoaded', () => {
    categoryCombobox = new CategoryCombobox(
        'pmProductCategoryInput',
        'pmProductCategoryId',
        'categoryDropdown'
    );
    subcategoryCombobox = new SubcategoryCombobox(
        'pmProductSubcategoryInput',
        'pmProductSubcategoryId',
        'subcategoryDropdown'
    );
});