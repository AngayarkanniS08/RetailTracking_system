<!-- 
  FEATURE DOCUMENTATION: Product Master
  - Catalog Registry: Central hub for defining products in the system.
  - Categorization: "Manage Categories & Subcategories" modal to build product hierarchies.
  - Product Creation: "+ Add Product" modal capturing custom units, categories, and taxation (HSN/GST).
-->
<section id="product_master" class="view-section active">

  <!-- Top Header Bar with inline Search and Action Button -->
  <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
    <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--text-strong); margin: 0;">Product Master</h2>
    
    <div class="d-flex" style="gap: 12px; align-items: center;">
      <div style="min-width: 240px;">
        <input type="text" id="pmSearch" class="input-field" placeholder="Search products..." style="padding: 8px 14px; font-size: 0.875rem;">
      </div>
      <button class="btn btn-outline btn-sm" onclick="openModal('addCategoryModal')">+ Manage Categories</button>
      <button class="btn btn-primary btn-sm" onclick="resetProductModal(); openModal('addProductModal')" style="padding: 9px 18px; font-weight: 600;">+ Add Product</button>
    </div>
  </div>


  <!-- 4 Stat Cards in a Single Row -->
  <div class="stats-grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 1.5rem;">
    <div class="stat-card" style="background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 1.25rem 1.5rem;">
      <div class="stat-label" style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted); margin-bottom: 6px;">TOTAL PRODUCTS</div>
      <div class="stat-value" id="pmTotalProducts" style="font-size: 1.75rem; font-weight: 800; color: var(--text-strong);">0</div>
    </div>
    
    <div class="stat-card" style="background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 1.25rem 1.5rem;">
      <div class="stat-label" style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted); margin-bottom: 6px;">CATEGORIES</div>
      <div class="stat-value" id="pmTotalCategories" style="font-size: 1.75rem; font-weight: 800; color: var(--text-strong);">0</div>
    </div>

    <div class="stat-card" style="background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 1.25rem 1.5rem;">
      <div class="stat-label" style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted); margin-bottom: 6px;">SUBCATEGORIES</div>
      <div class="stat-value" id="pmTotalSubcategories" style="font-size: 1.75rem; font-weight: 800; color: var(--ok, #10b981);">0</div>
    </div>
    
    <div class="stat-card" style="background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 1.25rem 1.5rem;">
      <div class="stat-label" style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted); margin-bottom: 6px;">ACTIVE BATCHES</div>
      <div class="stat-value" id="pmTotalBatches" style="font-size: 1.75rem; font-weight: 800; color: var(--info, #3b82f6);">0</div>
    </div>
  </div>

  <!-- Main Data Panel & Table -->
  <div class="card-panel" style="background: var(--card); border: 1px solid var(--border); border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); overflow: hidden; padding: 0;">
    
    <div class="table-container" id="productMasterTableContainer">
      <table id="productMasterTable" class="data-table" style="width: 100%; border-collapse: collapse;">
        <thead>
          <tr style="background: var(--bg-hover, #f8fafc); border-bottom: 1px solid var(--border, #e2e8f0);">
            <th style="font-size: 11px; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: var(--muted); padding: 14px 20px;">PRODUCT ID</th>
            <th style="font-size: 11px; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: var(--muted); padding: 14px 20px;">PRODUCT NAME</th>
            <th style="font-size: 11px; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: var(--muted); padding: 10px 16px; vertical-align: middle;">
              <div style="display: flex; align-items: center; justify-content: flex-start;">
                <select id="thCategoryFilter" class="input-field th-filter-select" aria-label="Category" onchange="onCategoryColumnFilterChange(this.value)">
                  <option value="all">All Categories</option>
                </select>
              </div>
            </th>
            <th style="font-size: 11px; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: var(--muted); padding: 10px 16px; vertical-align: middle;">
              <div style="display: flex; align-items: center; justify-content: flex-start;">
                <select id="thSubcategoryFilter" class="input-field th-filter-select" aria-label="Subcategory" onchange="onSubcategoryColumnFilterChange(this.value)">
                  <option value="all">All Subcategories</option>
                </select>
              </div>
            </th>
            <th style="font-size: 11px; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: var(--muted); padding: 14px 20px;">UNIT</th>
            <th style="font-size: 11px; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: var(--muted); padding: 14px 20px;">HSN CODE</th>
            <th style="font-size: 11px; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: var(--muted); padding: 14px 20px;">GST (%)</th>
            <th style="font-size: 11px; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: var(--muted); padding: 14px 20px; text-align: right;">ACTION</th>
          </tr>
        </thead>
        <tbody>
          <!-- Rendered via JS -->
        </tbody>
      </table>
    </div>

    <!-- Empty State Container matching reference UI -->
    <div id="pmEmptyState" class="empty-state" style="display: none; padding: 4rem 1.5rem; text-align: center;">
      <div class="empty-state-badge" style="width: 52px; height: 52px; border-radius: 50%; background: var(--bg-hover, #f1f5f9); display: flex; align-items: center; justify-content: center; margin: 0 auto 16px auto; color: var(--muted, #64748b);">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
          <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
          <line x1="12" y1="22" x2="12" y2="12"></line>
        </svg>
      </div>
      <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 6px; color: var(--text-strong, #0f172a);" id="emptyStateTitle">No product records found</h3>
      <p style="color: var(--muted, #64748b); max-width: 420px; margin: 0 auto 20px auto; font-size: 0.875rem; line-height: 1.5;" id="emptyStateSub">Product catalog records and categories will appear here in real-time.</p>
      <div class="d-flex" style="justify-content: center; gap: 12px;">
        <button class="btn btn-outline btn-sm" onclick="resetProductModal(); openModal('addProductModal')">+ Add Product</button>
      </div>
    </div>

  </div>
  
  <div id="paginationControls" class="pagination" style="margin-top: 1rem;"></div>
</section>

<script src="/public/assets/js/product.js?v=<?= time(); ?>"></script>
