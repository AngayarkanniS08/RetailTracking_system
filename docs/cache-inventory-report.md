# Cache Inventory Report

## 1. Cache Infrastructure

| Component | Detail |
|-----------|--------|
| **Server** | Valkey (Redis-compatible) |
| **Host** | `VALKEY_HOST` env (default: `valkey`) |
| **Port** | `VALKEY_PORT` env (default: `6379`) |
| **Client** | `Core\Cache\ValkeyCache` — singleton wrapping `new Redis()` |
| **Connection** | Persistent single-instance, no pooling |
| **Serialization** | Raw `json_encode` — caller responsibility |

---

## 2. Complete Cache Key Inventory

### 2.1 `billing:invoices:list:*`

| Property | Value |
|----------|-------|
| **Pattern** | `billing:invoices:list:search:{md5}:df:{md5}:dt:{md5}:is:{s}:ps:{s}:page:{N}:limit:{N}:user:{uid}` |
| **Creator** | `InvoiceController::index()` line 91 |
| **Consumer** | `InvoiceController::index()` line 77 |
| **TTL** | 300 seconds |
| **Invalidation Owner** | `InvoiceController::invalidateInvoiceCache()` (SCAN), `InvoiceService::invalidateCaches()` (KEYS) |
| **Cache Value** | JSON: `{ data: [...], total, page, limit }` |

### 2.2 `credit:search:*`

| Property | Value |
|----------|-------|
| **Pattern** | `credit:search:{md5}:page:{N}:limit:{N}:user:{uid}` |
| **Creator** | `CustomerController::index()` line 62 |
| **Consumer** | `CustomerController::index()` line 48 |
| **TTL** | 300 seconds |
| **Invalidation Owner** | `CustomerService::invalidateCaches()` (KEYS), `CreditService::invalidateCache()` (KEYS), `InvoiceService::invalidateCaches()` (KEYS) |
| **Cache Value** | JSON: `{ data: [...], pagination: {...} }` |

### 2.3 `pos:search:*`

| Property | Value |
|----------|-------|
| **Pattern** | `pos:search:{md5}:page:{N}:limit:{N}:user:{uid}` |
| **Creator** | `PosSearchController::search()` line 96 |
| **Consumer** | `PosSearchController::search()` line 36 |
| **TTL** | 300 seconds |
| **Invalidation Owner** | `BatchService::invalidateCache()` (KEYS), `InvoiceService::invalidateCaches()` (KEYS), `PosSearchController::invalidateProductCache()` (KEYS), `PosSearchController::flushCache()` (KEYS) |
| **Gap** | Product CRUD does NOT invalidate `pos:search:*` |

### 2.4 `inventory:batches:*`

| Property | Value |
|----------|-------|
| **Pattern** | `inventory:batches:search:{md5}:cat:{c}:subcat:{s}:page:{N}:limit:{N}:user:{uid}` |
| **Creator** | `BatchController::index()` line 63 |
| **Consumer** | `BatchController::index()` line 49 |
| **TTL** | 300 seconds |
| **Invalidation Owner** | `BatchService::invalidateCache()` (KEYS), `InvoiceService::invalidateCaches()` (KEYS) |

### 2.5 `products:search:*`

| Property | Value |
|----------|-------|
| **Pattern** | `products:search:{md5}:cat:{c}:subcat:{s}:page:{N}:limit:{N}:user:{uid}` |
| **Creator** | `ProductController::index()` line 70 |
| **Consumer** | `ProductController::index()` line 53 |
| **TTL** | 300 seconds |
| **Invalidation Owner** | `ProductService::invalidateProductSearchCache()` (KEYS) |
| **Gap** | NOT invalidated by `InvoiceService::invalidateCaches()` |

### 2.6 `vendors:list:*` (versioned)

| Property | Value |
|----------|-------|
| **Pattern** | `vendors:list:v{ver}:search:{md5}:page:{N}:limit:{N}:user:{uid}` |
| **Creator** | `PurchaseController::index()` line 76 |
| **Consumer** | `PurchaseController::index()` line 62 |
| **TTL** | 300 seconds |
| **Invalidation Owner** | `PurchaseService::invalidateVendorCaches()` (INCR version counter) |
| **Strategy** | Version counter — elegant, non-blocking |

### 2.7 `vendors:history:*` (versioned)

| Property | Value |
|----------|-------|
| **Pattern** | `vendors:history:v{ver}:{id}:date:{md5}:month:{md5}:year:{md5}:user:{uid}` |
| **Creator** | `PurchaseController::vendorHistory()` line 326 |
| **Consumer** | `PurchaseController::vendorHistory()` line 312 |
| **TTL** | 300 seconds |
| **Invalidation Owner** | `PurchaseService::invalidateVendorCaches()` (INCR) |

### 2.8 `vendors:history:all:*` (versioned)

| Property | Value |
|----------|-------|
| **Pattern** | `vendors:history:all:v{ver}:date:{md5}:month:{md5}:year:{md5}:user:{uid}` |
| **Creator** | `PurchaseController::allHistory()` line 382 |
| **Consumer** | `PurchaseController::allHistory()` line 367 |
| **TTL** | 300 seconds |
| **Invalidation Owner** | `PurchaseService::invalidateVendorCaches()` (INCR) |

### 2.9 `vendors:payments:*` (versioned)

| Property | Value |
|----------|-------|
| **Pattern** | `vendors:payments:v{ver}:{id}:date:{md5}:month:{md5}:year:{md5}:user:{uid}` |
| **Creator** | `PurchaseController::vendorPayments()` line 439 |
| **Consumer** | `PurchaseController::vendorPayments()` line 425 |
| **TTL** | 300 seconds |
| **Invalidation Owner** | `PurchaseService::invalidateVendorCaches()` (INCR) |

### 2.10 `vendors:payments:all:*` (versioned)

| Property | Value |
|----------|-------|
| **Pattern** | `vendors:payments:all:v{ver}:date:{md5}:month:{md5}:year:{md5}:user:{uid}` |
| **Creator** | `PurchaseController::allPayments()` line 495 |
| **Consumer** | `PurchaseController::allPayments()` line 480 |
| **TTL** | 300 seconds |
| **Invalidation Owner** | `PurchaseService::invalidateVendorCaches()` (INCR) |

### 2.11 `vendors:cache:version`

| Property | Value |
|----------|-------|
| **Pattern** | `vendors:cache:version` (single counter key) |
| **Creator** | `PurchaseService::invalidateVendorCaches()` (INCR) |
| **Consumer** | `PurchaseController::getVendorCacheVersion()` (GET) |
| **TTL** | None (persistent counter) |
| **Invalidation Owner** | `PurchaseService::invalidateVendorCaches()` |

### 2.12 `backup:status:*` and `backup:progress:*`

| Property | Value |
|----------|-------|
| **Pattern** | `backup:status:{jobId}`, `backup:progress:{jobId}` |
| **Creator** | `JobQueue` |
| **Consumer** | `JobQueue` |
| **TTL** | 86400 seconds |
| **Invalidation Owner** | None (TTL expiry) |

### 2.13 `backup:queue`

| Property | Value |
|----------|-------|
| **Pattern** | `backup:queue` (list key) |
| **Creator** | `JobQueue` |
| **Consumer** | `JobQueue` |
| **TTL** | None |
| **Invalidation Owner** | `JobQueue` |

---

## 3. Dead / Unused Cache Patterns

| Pattern | Created By | Notes |
|---------|-----------|-------|
| `reports:*` | **Never created** | Deleted by `InvoiceService::invalidateCaches()` and `BatchService::invalidateCache()` but NO module ever writes keys matching this pattern. Wasteful KEYS scan. |

---

## 4. Invalidation Strategy Summary

| Strategy | Used By | Blocking? | Risk |
|----------|---------|-----------|------|
| `KEYS` + `DEL` | InvoiceService, CustomerService, CreditService, BatchService, ProductService, PosSearchController | **YES** — blocks Valkey during full keyspace scan | High — can timeout on large datasets |
| `SCAN` + `DEL` | `InvoiceController::invalidateInvoiceCache()` only | No — cursor-based iteration | Low |
| `INCR` version counter | PurchaseService only | No — single key increment | None |
| TTL expiry | All caches (fallback) | No | Only recovers after 300s |

---

## 5. Cache Gap Analysis

| Gap | Impact | Severity |
|-----|--------|----------|
| `KEYS` command can timeout silently | Cache invalidation fails, stale data served for up to 5 min | **Critical** |
| No retry on invalidation failure | Single transient failure = stale cache | **High** |
| `reports:*` pattern invalidated but never created | Wasteful KEYS scan on every invoice/batch mutation | Low |
| Product CRUD doesn't invalidate `pos:search:*` | Stale product names in POS search for up to 5 min | Medium |
| Invoice list cache NOT invalidated by `products:search:*` changes | Product name changes not reflected in cached invoice lists | Low |
| Dashboard/Reports have NO caching | Always fresh, but no protection against DB load | Info |
