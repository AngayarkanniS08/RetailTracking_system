# Inventory Module — Architecture

The Inventory module follows the app's enterprise pattern: **all business logic
lives in the backend**, controllers are thin HTTP adapters, repositories are
data-only, and the frontend renders server-computed values.

```
HTTP ──► Router ──► Controller (thin) ──► Service (workflows + rules)
                                              │
                    ┌──────────┬──────────────┼───────────────┐
                    ▼          ▼              ▼               ▼
               Validator   Policy       Repository        Mapper
               (shapes)   (ownership)  (SQL + DTOs)    (row → DTO)
                                              │
                                              ▼
                                       Event Dispatcher
                                        │     │      │
                                   Audit    Cache   Notifications
```

## 1. Layer responsibilities

| Layer | Files | Owns |
|---|---|---|
| Controller | `Controller/Api/InventoryController.php` | HTTP in/out only; reads JSON body + query, delegates to Validator + Service, serialises `{success, data, ...}`. No rules. |
| Service | `Service/InventoryService.php`, `Service/InventorySearchService.php` | Every workflow (`listInventory`, `getDetails`, `createBatch`, `updateBatch`, `restockBatch`) and every pure rule (`calculateStockStatus`, `calculateInventoryValue`, `calculateNewQuantity`, `calculateRestockRecommendation`, `normalizeSearch`). |
| Validator | `Validator/InventoryValidator.php` | Shapes + validates every input payload (UUID, positive ints, floats, immutability, list-query clamps). |
| Policy | `Policy/InventoryPolicy.php` | Ownership assertions + restock overflow guard. |
| Mapper | `Mapper/InventoryMapper.php` | Pure row→DTO hydration (`toBatchDTO`, `toSummaryDTO`, `toDetailsDTO`). |
| Repository | `Repository/Contract/InventoryRepositoryInterface.php`, `Repository/InventoryRepository.php` | SQL only. Tenant-scoped via `current_setting('app.current_user_id', true)::uuid` + RLS. Returns raw rows. |
| Cache | `Cache/InventoryCacheService.php` | Cache *invalidation* only. |
| Audit | `Audit/InventoryAuditLogger.php` | Writes `inventory_audit_logs` rows (action, before/after JSONB, IP, UA, reason). |
| Events | `Events/*.php`, `Events/Listeners/*.php` | `InventoryEvent` value objects + synchronous dispatcher. Listeners: `AuditEventListener`, `CacheEventListener`, `NotificationEventListener`. |
| DTOs | `DTO/Inventory*.php` | Typed payloads: `InventoryCreateDTO`, `InventoryUpdateDTO`, `InventoryRestockDTO`, `InventoryBatchDTO`, `InventorySummaryDTO`, `InventoryDetailsDTO`. |

**Hard rules**
- Zero business logic in controllers, repositories, or frontend JS.
- No SQL outside repositories (the one `DELETE` in `deleteBatch` is a defensive,
  unexposed path — flagged for extraction).
- No cache logic outside `InventoryCacheService` / the service read-cache helpers.
- Every mutation dispatches an event; listeners are optional and failures are
  swallowed by the dispatcher (best-effort audit/cache/notify).

## 2. Request flow (example: restock)

1. `POST /api/inventory/{id}/restock` → `InventoryController::restock($id)`
2. Controller: `InventoryValidator::validateRestock($id, body)` → `InventoryRestockDTO`
3. Controller: `InventoryService::restockBatch($dto, userId)`
4. Service: `InventoryPolicy::assertOwned(repo->findById($id), $userId)`
5. Service: `newQuantity = calculateNewQuantity(current, add)`; `InventoryPolicy::canRestock(current, add, MAX_QUANTITY)`
6. Service: `repo->restock($id, $newQuantity)`
7. Service: recompute status + value + recommendation, dispatch `InventoryRestockedEvent`
8. Listeners (best-effort): audit log row; cache invalidation of
   `inventory:*`, `pos:search:*`, `notifications:*`, `dashboard:*`; notification refresh.

## 3. API contract

| Method | Route | Payload | Returns |
|---|---|---|---|
| GET | `/api/inventory` | `page, limit, search, category_id, subcategory_id` | `{success, data[], pagination, summary, filters}` |
| GET | `/api/inventory/{id}` | — | details + `inventory_value` + `restock_recommendation` |
| POST | `/api/inventory` | `{product_id, batch_number, vendor_name, quantity, cost_price, selling_price, retail_price, created_at?}` | created batch |
| PUT | `/api/inventory/{id}` | update payload + `expected_updated_at` (optimistic concurrency) | updated batch |
| POST | `/api/inventory/{id}/restock` | `{add_quantity, reason?}` | `{previous_quantity, added_quantity, new_quantity, stock_status, severity, status_text, inventory_value, restock_recommendation}` |
| GET | `/api/inventory/categories` | — | categories |
| GET | `/api/inventory/subcategories` | `category_id?` | subcategories |
| GET | `/api/inventory/alerts` | — | active low-stock alerts |
| POST | `/api/inventory/alerts` | `{product_id, lead_time, daily_sales, emergency_stock}` | saved ROP config |
| PATCH | `/api/inventory/alerts/{productId}/disable` | — | disables alert (admin) |

Legacy aliases (kept for backward compatibility): `GET/POST /api/inventory/batches`,
`PUT /api/inventory/batches/{id}`.

**Route-order constraint:** static sub-paths (`categories`, `subcategories`,
`batches`, `alerts`) MUST be registered before `GET /api/inventory/{id}` — the
Router returns the first regex match (`src/Core/Router.php`). Violating this
silently shadows `alerts` (fixed in `src/Core/ApiRoutes.php`).

## 4. Business rules

- **Stock status** (pure, in `InventoryService::calculateStockStatus`):
  - `quantity <= 0` → `OUT_OF_STOCK` (critical)
  - `quantity <= emergency_stock` (if configured) → `CRITICAL_STOCK` (critical)
  - `quantity <= reorder_level` (default 10 if unset) → `LOW_STOCK` (warning)
  - otherwise → `IN_STOCK`
- **Inventory value** = `quantity × cost_price`, rounded to 2dp.
- **Restock recommendation** (max = `original_quantity`, fallback `initial_qty`):
  - `recommended_target` = `maximum_capacity`
  - `deficit` = `max(0, maximum_capacity − current_quantity)`
  - `recommended_order_quantity` = `deficit`
- **Restock is additive**: the client sends `add_quantity`; the total is always
  computed server-side. Overflow guard at 1e9 units.
- **Immutability**: `id`, `user_id`, `product_id`, `product_name`, `unit` cannot
  be updated.
- **Optimistic concurrency**: `PUT` accepts `expected_updated_at`; the service
  compares it to the stored `updated_at` (normalised to second precision) and
  returns `"This batch was modified by another user. Refresh and try again."`
  on mismatch.

## 5. Caching

- Reads: `InventoryService` caches list + details responses in Valkey for 60s
  (`inventory:list:…`, `inventory:details:…`).
- Invalidations: `CacheEventListener` on every mutation clears:
  - `inventory:*` (module lists/details)
  - `pos:search:*` (POS search depends on live stock)
  - `notifications:*` (badge counts)
  - `dashboard:*` (stock-intel cards)
- Cache failures are non-fatal (`readCache`/`writeCache` swallow exceptions).

## 6. Frontend split (`public/assets/js/pages/inventory/`)

| File | Role |
|---|---|
| `InventoryController.js` | Orchestrator: DOM wiring, load/render/navigate, exposes `initInventoryPage()` (imported by `main.js`). |
| `InventoryState.js` | Pure state container (page, filters, items, pricing mode, edit/restock targets). |
| `InventoryAPI.js` | Thin REST client, 1:1 endpoint mapping. |
| `InventoryRenderer.js` | Pure DOM rendering (stats, table, pagination) — displays server values only. |
| `InventoryTable.js` | Row-action delegation (`data-stock-details`, `data-restock`, `data-edit-batch`). |
| `InventoryFilters.js` | Debounced search + category/subcategory comboboxes (server-side filtering). |
| `InventoryPagination.js` | Pagination control delegation. |
| `InventoryModal.js` | Restock / edit / details / add-stock / alert modal workflows + pricing-mode UX. |
| `InventoryEvents.js` | Bridge for legacy inline `onclick` handlers → controller/modal methods. |

The frontend never computes stock status, value, or recommendations — it renders
`status_text`, `status_badge_class`, `inventory_value`, `restock_recommendation`
straight from the API. After any mutation the controller reloads the list and
triggers `window.refreshNotifications()` (NotificationController).

## 7. Audit

Every mutation writes an `inventory_audit_logs` row via `InventoryAuditLogger`:

```
action (created|updated|restocked|deleted), batch_id, user_id,
before_data JSONB, after_data JSONB, reason, ip_address, user_agent
```

Migration: `Database/Migrations/Inventory/022_inventory_audit_logs.sql`.

## 8. Testing

DB-free unit tests (hand-rolled, run with `php tests/Inventory/…`):

- `tests/Inventory/InventoryServiceRulesTest.php` — status thresholds, value,
  restock math, recommendation, overflow guard, search normalisation, duplicate
  batch guard, optimistic concurrency (via `FakeInventoryRepository`),
  restock response shape.
- `tests/Inventory/InventoryValidatorTest.php` — UUID/immutability/positivity/
  overflow/date/query contracts.

## 9. Extension points

- Add a listener to the dispatcher in `InventoryService::__construct` to react
  to mutations (e.g. analytics, webhooks).
- New workflows: add a service method (rules) + repository method (SQL) +
  optional controller route; register static routes before `/api/inventory/{id}`.
- The unexposed `deleteBatch` service method currently runs inline SQL — extract
  to the repository if it is ever exposed via REST.
