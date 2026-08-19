# Dual-Mode Product Category Deletion – Master Implementation Plan

> **Plan Status**: Draft / Ready for Execution  
> **Target Feature**: Category Deletion (Safe Mode & Force Mode)  
> **Architecture Standard**: Layered Clean Architecture (Controller → Service → Repository) with Single-Transaction Cascade  

---

## Executive Summary

This specification defines the production implementation of product category deletion for the Retail Tracking System. To prevent accidental data loss and maintain enterprise-grade referential integrity, deletion supports two explicit modes:

1. **Safe Delete (`force=false`, Default)**: Refuses deletion if any products reference the category (`HTTP 409 Conflict`).
2. **Force Delete (`force=true`)**: Deletes the category and all dependent records (product images, inventory batches, product variants, price history, audit logs, and products) inside a single atomic database transaction.

---

## 1. Architectural Design & System Flow

```text
                               ┌───────────────────────────┐
                               │  Frontend (ProductMaster) │
                               └─────────────┬─────────────┘
                                             │
                       DELETE /api/categories/{id}?force=true|false
                                             │
                                             ▼
                               ┌───────────────────────────┐
                               │     CategoryController    │
                               └─────────────┬─────────────┘
                                             │ (Validates input & query params)
                                             ▼
                               ┌───────────────────────────┐
                               │      CategoryService      │
                               └─────────────┬─────────────┘
                                             │ (Enforces Safe vs Force rules)
                                             ▼
                               ┌───────────────────────────┐
                               │     CategoryRepository    │
                               └─────────────┬─────────────┘
                                             │ (Atomic DB Cascade Transaction)
                                             ▼
                               ┌───────────────────────────┐
                               │    PostgreSQL Database    │
                               └───────────────────────────┘
```

---

## 2. API Endpoint Specification

### Endpoint
```http
DELETE /api/categories/{categoryId}?force={boolean}
```

### Path Parameters
* `categoryId` (string, required): Valid UUID of the category to delete.

### Query Parameters
* `force` (boolean, optional, default: `false`): Allowed values: `true`, `false`, `1`, `0`.

---

### Standard Response Schemas

#### A. Safe Delete Success (`200 OK`)
```json
{
    "success": true,
    "message": "Category deleted successfully."
}
```

#### B. Safe Delete Blocked (`409 Conflict`)
```json
{
    "success": false,
    "code": "CATEGORY_HAS_PRODUCTS",
    "message": "Category contains existing products.",
    "productCount": 5,
    "action": "Use force delete to remove the category and its products."
}
```

#### C. Force Delete Success (`200 OK`)
```json
{
    "success": true,
    "message": "Category deleted successfully.",
    "deleted": {
        "category": 1,
        "products": 5,
        "inventory": 18,
        "variants": 9,
        "images": 22
    }
}
```

#### D. Category Not Found (`404 Not Found`)
```json
{
    "success": false,
    "code": "CATEGORY_NOT_FOUND",
    "message": "Category does not exist."
}
```

#### E. Invalid Input (`400 Bad Request`)
```json
{
    "success": false,
    "code": "INVALID_REQUEST",
    "message": "Invalid category ID or force parameter format."
}
```

---

## 3. Strict Layer Responsibilities

### A. Controller (`CategoryController::destroy`)
* Extract `$id` and query string `force`.
* Validate `$id` format (non-empty UUID) and `force` parameter (`true/false/1/0`).
* Pass sanitized parameters to `CategoryService::deleteCategory($id, $force)`.
* Map returned results/exceptions into JSON responses with proper HTTP status codes (`200`, `400`, `404`, `409`, `500`).
* **Strict Constraint**: Zero business or database logic.

### B. Service (`CategoryService::deleteCategory`)
```text
                          Receive Request ($id, $force)
                                       │
                                       ▼
                          Validate Category Exists
                                       │
                          ┌────────────┴────────────┐
               Cat Missing│                         │Cat Exists
                          ▼                         ▼
                  Throw 404 Exception        Count Products
                                                    │
                                  ┌─────────────────┴─────────────────┐
                       $force=false│                                   │$force=true
                                   ▼                                   ▼
                            Product Count > 0 ?                Begin Transaction
                          ┌────────┴────────┐                          │
                      YES │                 │NO                        ▼
                          ▼                 ▼                Cascade Delete Dependents
                   Throw 409 Conflict  Delete Category       (Images → Inventory → 
                   Response Payload        (Single)           Variants → Prices → 
                                            │                 Products → Category)
                                            │                          │
                                            ▼                          ▼
                                      Return Success                 Commit
                                                                       │
                                                                       ▼
                                                                 Return Summary
```

### C. Repository (`CategoryRepository`)
* Implements explicit DB deletion methods:
  * `findCategory(string $id): ?array`
  * `countProductsByCategory(string $id): int`
  * `findDependentCounts(string $id): array`
  * `deleteProductImagesByCategory(string $id): int`
  * `deleteInventoryByCategory(string $id): int`
  * `deleteProductVariantsByCategory(string $id): int`
  * `deleteProductPricesByCategory(string $id): int`
  * `deleteProductLogsByCategory(string $id): int`
  * `deleteProductsByCategory(string $id): int`
  * `deleteCategory(string $id): bool`
  * Transaction controls: `beginTransaction()`, `commit()`, `rollback()`.
* **Execution Order Constraint**: Must delete child tables first to strictly prevent foreign-key constraints violations.

---

## 4. Frontend UI/UX Specification

### Manage Categories List (`views/layouts/modals.php`)
* Each row renders:
  * Category Name
  * Product Count Badge (e.g., `5 products`)
  * Action Button: **Delete** (Red icon button)

### Modal Interactivity Workflows (`public/assets/js/ProductMaster.js`)

#### Workflow 1: Safe Delete (`productCount === 0`)
1. User clicks **Delete** icon.
2. System renders **Standard Confirmation Modal**:
   > **Delete Category?**  
   > Are you sure you want to delete **Lace Work**? This action cannot be undone.  
   > `[Cancel]` `[Delete]`
3. User clicks **Delete** → Asynchronous `DELETE /api/categories/{id}?force=false`.
4. Dynamic DOM update: Row removed, combobox & product grid refreshed, success toast displayed.

#### Workflow 2: Force Delete (`productCount > 0`)
1. User clicks **Delete** icon on a category with products.
2. System fetches dependent counts and renders **Force Delete Danger Modal**:
   > ⚠️ **Category Contains Active Products**  
   > Category **Lace Work** is currently assigned to **5 products**.  
   > Deleting this category will permanently remove:  
   > • **5** Products  
   > • **18** Inventory records  
   > • **9** Product variants  
   > • **22** Product images  
   > ⚠️ **This action CANNOT be undone.**  
   > `[Cancel]` `[Delete Everything (5 Products)]` (Red Primary Button)
3. User clicks **Delete Everything** → Asynchronous `DELETE /api/categories/{id}?force=true`.
4. Dynamic DOM update: Row removed, categories & products state reloaded, UI updated without full page refresh.

---

## 5. Master Implementation Task Checklist

### Phase 1: Repository Layer & Cascade Operations
- [x] **1.1** Update `CategoryRepositoryInterface.php` to define `countProductsByCategory`, `findDependentCounts`, and single-transaction cascade deletion methods.
- [x] **1.2** Implement `countProductsByCategory(string $id): int` in `CategoryRepository.php`.
- [x] **1.3** Implement `findDependentCounts(string $id): array` returning breakdown of images, inventory, variants, and product counts.
- [x] **1.4** Implement cascade deletion methods (`deleteProductImagesByCategory`, `deleteInventoryByCategory`, `deleteProductVariantsByCategory`, `deleteProductsByCategory`, `deleteCategory`).
- [x] **1.5** Add transactional safety wrappers (`beginTransaction`, `commit`, `rollback`) in `CategoryRepository.php`.

### Phase 2: Service Layer & Business Logic
- [x] **2.1** Update `CategoryService.php` to accept `deleteCategory(string $id, bool $force = false): array`.
- [x] **2.2** Implement existence check (`Category Not Found` → `404`).
- [x] **2.3** Implement Safe Delete validation (`$force === false` and `product_count > 0` → throw `ValidationException` with structured `CATEGORY_HAS_PRODUCTS` payload).
- [x] **2.4** Implement Force Delete transactional block (`$force === true` → execute full cascade order in single transaction, returning summary counts).
- [x] **2.5** Implement rollback handling to catch any `Exception` during Force Delete and rethrow clean domain exceptions.

### Phase 3: Controller & Route Registration
- [x] **3.1** Update `CategoryController::destroy` to extract and validate `force` query flag (`true/false/1/0`).
- [x] **3.2** Update controller response formatter to output standardized JSON structures for `200`, `400`, `404`, `409`, and `500`.
- [x] **3.3** Verify route `/api/categories/{id}` in `ApiRoutes.php` correctly passes query parameters to `CategoryController::destroy`.

### Phase 4: Frontend Modal & Dynamic Interactivity
- [x] **4.1** Update `#addCategoryModal` in `views/layouts/modals.php` to include `#existingCategoriesList`.
- [x] **4.2** Add `#deleteCategoryModal` confirmation modal markup to `views/layouts/modals.php` with support for both Safe and Force delete warnings.
- [x] **4.3** Update `renderExistingCategoriesList()` in `ProductMaster.js` to render rows with category name, product count badge, and delete button.
- [x] **4.4** Update `window.deleteCategory(id)` in `ProductMaster.js` to branch between Safe Delete and Force Delete dialog rendering.
- [x] **4.5** Implement `confirmDeleteCategory` event handler supporting both standard delete and `?force=true` AJAX requests.
- [x] **4.6** Implement post-deletion dynamic state update (`loadCategories()`, `loadProducts()`, closing modal, showing toast message).

### Phase 5: Security, Audit Logging & Concurrency Safety
- [x] **5.1** Ensure all DB operations retain tenant isolation (`user_id = current_setting('app.current_user_id')::uuid`).
- [x] **5.2** Add audit log event recording for both Safe and Force category deletions (capturing category ID, user ID, timestamp, and deleted counts summary).
- [x] **5.3** Disable delete confirmation buttons during active AJAX request to prevent duplicate click submissions.

### Phase 6: Automated Verification & Testing
- [x] **6.1** Run PHP syntax check on modified backend files (`php -l`).
- [x] **6.2** Perform API unit/integration verification for Safe Delete mode.
- [x] **6.3** Perform API unit/integration verification for Force Delete mode.
- [x] **6.4** Execute master project audit via `.agent/scripts/checklist.py .`.
