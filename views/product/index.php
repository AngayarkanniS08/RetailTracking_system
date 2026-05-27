<!-- 
  FEATURE DOCUMENTATION: Product Master
  - Catalog Registry: Central hub for defining products in the system.
  - Categorization: "Manage Categories & Subcategories" modal to build product hierarchies.
  - Product Creation: "+ Add Product" modal capturing custom units, categories, and taxation (HSN/GST).
--> <script> console.log('Token:', localStorage.getItem('auth_token'))</script>
        <section id="product_master" class="view-section">
          <div class="card-header">
            <span>Product Master</span>
            <div class="d-flex">
              <button class="btn btn-outline btn-sm" onclick="openModal('addCategoryModal')">+ Add Category</button>
              <button class="btn btn-primary btn-sm" onclick="resetProductModal(); openModal('addProductModal')">+ Add Product</button>
            </div>
          </div>

          <!-- Search and Category Tabs -->
          <div class="input-group" style="margin-bottom: 1rem;">
            <input type="text" id="pmSearch" class="input-field" placeholder="Search products by name or ID..." oninput="renderProductMaster()">
          </div>

          <div class="cat-filters" id="pmCatFilters">
            <!-- Rendered by JS -->
          </div>

          <!-- Stats -->
          <div class="stats-grid" style="margin-bottom: 1.5rem;">
            <div class="stat-card">
              <div class="stat-label">Total Products</div>
              <div class="stat-value" id="pmTotalProducts">0</div>
            </div>
            <div class="stat-card">
              <div class="stat-label">Categories</div>
              <div class="stat-value" id="pmTotalCategories">0</div>
            </div>
            <div class="stat-card">
              <div class="stat-label">Active Batches</div>
              <div class="stat-value" style="color:var(--info)" id="pmTotalBatches">0</div>
            </div>
          </div>

          <div class="card-panel">
            <div class="table-container">
              <table id="productMasterTable">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Product Name</th>
                    <th>Category</th>
                    <th>Unit</th>
                    <th>HSN Code</th>
                    <th>GST (%)</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <!-- Rendered via JS -->
                </tbody>
              </table>
            </div>
          </div>
        </section>
