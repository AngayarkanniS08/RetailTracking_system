# Production Implementation Plan – Delete Existing Product Category

## Executive Summary
This implementation plan details the full backend and frontend solution for safely deleting product categories in the Retail Tracking System. The design adheres strictly to clean architecture standards (Controller → Service → Repository), REST principles, transactional safety, non-blocking UI updates, and strict user-scoped multi-tenancy.

---

## 1. System & Architectural Alignment

```
Products Page (ProductMaster.js)
        │
        ├──────────────────────► Add / Edit Category
        │
        └──────────────────────► Delete Category (Modal Confirmation + AJAX)
                                      │
                                      ▼
                      CategoryController::destroy($id)
                                      │
                                      ▼
                      CategoryService::deleteCategory($id)
                                      │
                                      ▼
                      CategoryRepository::delete($id)
                                      │
                                      ▼
                        PostgreSQL Database (categories)
```

---

## 2. API Design & Routing Specifications

### Endpoint
```http
DELETE /api/categories/{id}
```
*Note: The project routes use `/api/categories/{id}` (registered in `ApiRoutes.php`).*

### Security & Middleware
* **Authentication**: `AuthMiddleware::authenticate(900)` (Requires valid JWT with 15-minute window for state-modifying operations).
* **Multi-tenancy**: All DB queries are automatically scoped by PostgreSQL RLS / session setting `current_setting('app.current_user_id')::uuid`.

### Standardized API Responses

#### 200 OK (Success)
```json
{
    "success": true,
    "message": "Category deleted successfully."
}
```

#### 404 Not Found (Invalid ID or category does not exist for tenant)
```json
{
    "success": false,
    "error": "Category not found."
}
```

#### 409 Conflict (Category linked to products)
```json
{
    "success": false,
    "error": "Category is currently assigned to 5 product(s). Cannot delete."
}
```

#### 400 Bad Request (Invalid UUID format)
```json
{
    "success": false,
    "error": "Invalid Category ID format."
}
```

#### 500 Internal Server Error (Unexpected system error)
```json
{
    "success": false,
    "error": "Unable to delete category due to an internal server error."
}
```

---

## 3. Component Design & Layer Responsibilities

### A. Controller Layer (`CategoryController::destroy`)
* **File**: `src/Modules/Product/Controller/Api/CategoryController.php`
* **Responsibilities**:
  1. Validate incoming HTTP method (`DELETE`).
  2. Validate `$id` (ensure non-empty UUID string format).
  3. Invoke `CategoryService::deleteCategory($id)`.
  4. Return standardized JSON response with proper HTTP status code (`200`, `400`, `404`, `409`, `500`).
* **Strict Constraints**: No SQL queries, business checks, or transaction management inside the controller.

### B. Service Layer (`CategoryService::deleteCategory`)
* **File**: `src/Modules/Product/Service/CategoryService.php`
* **Responsibilities**:
  1. Verify category existence using `$this->repo->findById($id)`. If missing, throw `ValidationException("Category not found.", 404)`.
  2. Check if category is assigned to products using `$this->repo->countProductsUsingCategory($id)`.
     - If `product_count > 0`, throw `ValidationException("Category is currently assigned to products and cannot be deleted.", 409)`.
  3. Check if category has child subcategories using `$this->repo->countSubcategoriesUsingCategory($id)` or cascade policy check.
  4. Wrap deletion in a database transaction block:
     ```php
     $this->repo->beginTransaction();
     try {
         $deleted = $this->repo->delete($id);
         $this->repo->commit();
         return $deleted;
     } catch (Exception $e) {
         $this->repo->rollback();
         throw $e;
     }
     ```

### C. Repository Layer (`CategoryRepository`)
* **File**: `src/Modules/Product/Repository/CategoryRepository.php`
* **Contract**: `src/Modules/Product/Repository/Contract/CategoryRepositoryInterface.php`
* **Methods**:
  ```php
  public function findById(string $id): ?array;
  public function countProductsUsingCategory(string $id): int;
  public function delete(string $id): bool;
  public function beginTransaction(): void;
  public function commit(): void;
  public function rollback(): void;
  ```
* **SQL Statements**:
  * **Product Usage Check**:
    ```sql
    SELECT COUNT(*) FROM products 
    WHERE category_id = ? AND user_id = current_setting('app.current_user_id')::uuid
    ```
  * **Category Delete**:
    ```sql
    DELETE FROM categories 
    WHERE id = ? AND user_id = current_setting('app.current_user_id')::uuid
    ```

---

## 4. Frontend UI/UX Integration Plan

### A. Modal Layout Updates (`views/layouts/modals.php`)
1. **Manage Categories Modal (`#addCategoryModal`)**:
   * Expand width to `600px` for optimal table layout.
   * Add a dynamic **Existing Categories List** section rendering all loaded categories.
   * Each row displays:
     * Category Name
     * Product Count badge (e.g., `3 products`)
     * Action Button: **Delete** (Red icon button, disabled or warning tooltipped if product count > 0).

2. **Category Delete Confirmation Modal (`#deleteCategoryModal`)**:
   * Add a generic/dedicated confirm dialog overlay:
     ```html
     <div id="deleteCategoryModal" class="modal-overlay">
       <div class="modal-content" style="max-width: 420px;">
         <div class="modal-header">
           <div class="modal-title">Delete Category</div>
           <button class="close-btn" onclick="closeModal('deleteCategoryModal')">&times;</button>
         </div>
         <div class="modal-body" id="deleteCategoryModalBody">
           <p>Are you sure you want to delete <strong id="deleteCategoryName"></strong>?</p>
           <p class="text-muted" style="font-size: 0.85rem;">This action cannot be undone.</p>
         </div>
         <div class="modal-footer d-flex gap-2 justify-content-end">
           <button class="btn btn-outline" onclick="closeModal('deleteCategoryModal')">Cancel</button>
           <button class="btn btn-danger" id="confirmDeleteCategoryBtn">Delete Category</button>
         </div>
       </div>
     </div>
     ```

### B. JavaScript Interactivity (`public/assets/js/ProductMaster.js`)
1. **Dynamic Category Row Rendering**:
   * Function `renderCategoryList()` inside `ProductMaster.js` dynamically populates the list in `#addCategoryModal`.
2. **AJAX Deletion Flow**:
   * User clicks **Delete** icon on category row → `confirmDeleteCategory(catId, catName, productCount)`.
   * If `productCount > 0`: Show warning toast / prompt explaining products must be reassigned first.
   * If `productCount === 0`: Open confirmation modal.
   * Click **Confirm Delete** → AJAX `DELETE /api/categories/{catId}`.
   * On Success:
     - Remove row from category list DOM.
     - Call `await loadCategories()` to update global state and dropdowns (`#pmSubCatParent`, `#categoryCombobox`).
     - Call `await loadProducts()` to update product grid.
     - Show success toast: *"Category deleted successfully."*
   * On Error:
     - Display error toast / alert without closing modal.

---

## 5. Security & Edge Case Matrix

| Edge Case / Threat | Mitigation Strategy |
| :--- | :--- |
| **Concurrent Deletion** | Wrapped in DB transaction; primary key search ensures idempotent non-crash. |
| **Foreign Key Constraint Violation** | Catch `PDOException` with code `23503` as fallback and return `409 Conflict`. |
| **Cross-Tenant Access** | Tenant UUID checked via DB RLS (`user_id = current_setting('app.current_user_id')::uuid`). |
| **Active Subcategories** | Repository checks if subcategories reference category before deletion. |
| **Stale UI Dropdowns** | Category combobox & parent dropdown re-fetched asynchronously post-deletion. |

---

## 6. Implementation Task Checklist

1. [ ] **Repository Layer**: Add `countProductsUsingCategory()` and transaction helpers to `CategoryRepository` & Interface.
2. [ ] **Service Layer**: Update `CategoryService::deleteCategory()` with validation exception codes for existence and usage checks.
3. [ ] **Controller Layer**: Update `CategoryController::destroy()` to format HTTP response status codes (`404`, `409`, `200`) correctly.
4. [ ] **Modal Views**: Update `views/layouts/modals.php` with existing categories table & `#deleteCategoryModal`.
5. [ ] **Frontend JS**: Add category list rendering, row deletion event listeners, and live cache refreshing in `ProductMaster.js`.
6. [ ] **Verification**: Run unit/integration tests and audit via `checklist.py`.
