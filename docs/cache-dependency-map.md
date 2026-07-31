# Cache Dependency Map

## 1. Operation → Invalidation Flow

```
Invoice Created
  │
  ├─► InvoiceService::invalidateCaches()
  │     ├─► KEYS billing:invoices:*         → DEL
  │     ├─► KEYS credit:*                   → DEL
  │     ├─► KEYS reports:*                  → DEL (DEAD — nothing creates these)
  │     ├─► KEYS pos:search:*               → DEL
  │     └─► KEYS inventory:batches:*        → DEL
  │
  └─► InvoiceController::invalidateInvoiceCache()
        └─► SCAN billing:invoices:list:*    → DEL (redundant with above)

Invoice Cancelled
  │
  └─► InvoiceService::invalidateCaches()
        (same patterns as above)
        ⚠ does NOT call InvoiceController::invalidateInvoiceCache()

Invoice Returned
  │
  └─► InvoiceService::invalidateCaches()
        (same patterns as above)
        ⚠ does NOT call InvoiceController::invalidateInvoiceCache()

Customer Created
  │
  └─► CustomerService::invalidateCaches()
        ├─► KEYS credit:*                   → DEL
        └─► KEYS billing:invoices:*         → DEL

Customer Updated
  │
  └─► CustomerService::invalidateCaches()
        (same patterns as above)

Customer Payment Recorded
  │
  └─► CustomerService::invalidateCaches()
        (same patterns as above)

Credit Sale Recorded
  │
  └─► CreditService::invalidateCache()
        ├─► KEYS credit:*                   → DEL
        └─► KEYS billing:invoices:*         → DEL

Batch Created / Updated
  │
  ├─► BatchService::invalidateCache()
  │     ├─► KEYS reports:*                  → DEL (DEAD)
  │     ├─► KEYS inventory:batches:*        → DEL
  │     └─► KEYS pos:search:*               → DEL
  │
  └─► BatchController::store()
        └─► PosSearchController::invalidateProductCache()
              └─► KEYS pos:search:*         → DEL (redundant with above)

Product Created / Updated / Deleted
  │
  └─► ProductService::invalidateProductSearchCache()
        └─► KEYS products:search:*          → DEL
        ⚠ does NOT invalidate pos:search:*
        ⚠ does NOT invalidate billing:invoices:list:*

Purchase Created / Payment Recorded / Purchase Updated
  │
  └─► PurchaseService::invalidateVendorCaches()
        └─► INCR vendors:cache:version      → (version bumps, cache keys embed version)
```

---

## 2. Cache Read → Creator Dependency

```
billing:invoices:list:*
  │ Read by: InvoiceController::index()
  │ Created by: InvoiceController::index()
  │ Invalidated by: InvoiceService, InvoiceController, CustomerService, CreditService
  │
  └─ Depends on: invoices, invoice_items tables

credit:search:*
  │ Read by: CustomerController::index()
  │ Created by: CustomerController::index()
  │ Invalidated by: CustomerService, CreditService, InvoiceService
  │
  └─ Depends on: customers, customer_ledger tables

pos:search:*
  │ Read by: PosSearchController::search()
  │ Created by: PosSearchController::search()
  │ Invalidated by: BatchService, InvoiceService, PosSearchController
  │ ⚠ NOT invalidated by: ProductService
  │
  └─ Depends on: inventory_batches, products tables

inventory:batches:*
  │ Read by: BatchController::index()
  │ Created by: BatchController::index()
  │ Invalidated by: BatchService, InvoiceService
  │
  └─ Depends on: inventory_batches, products tables

products:search:*
  │ Read by: ProductController::index()
  │ Created by: ProductController::index()
  │ Invalidated by: ProductService
  │ ⚠ NOT invalidated by: InvoiceService, BatchService
  │
  └─ Depends on: products table

vendors:list:*, vendors:history:*, vendors:payments:*
  │ Read by: PurchaseController (5 endpoints)
  │ Created by: PurchaseController (5 endpoints)
  │ Invalidated by: PurchaseService (version counter)
  │
  └─ Depends on: vendor_purchases, vendor_purchase_items, vendor_payments tables
```

---

## 3. Invalidation Coverage per Data Domain

| Data Domain | Creates Invoice | Cancels Invoice | Returns Item | Creates Batch | Updates Batch | Creates Product | Updates Product | Creates Customer | Records Payment | Credit Sale |
|-------------|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| Invoice List | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ | ✅ |
| Customer Search | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ | ✅ |
| POS Search | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Inventory Batches | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Product Search | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ | ❌ | ❌ | ❌ |
| Vendor (all) | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Dashboard/Reports | ❌* | ❌* | ❌* | ❌* | ❌* | ❌* | ❌* | ❌* | ❌* | ❌* |

* \* Dashboard/Reports hit DB directly — no cache invalidation needed

---

## 4. Key Observations

1. **Invoice creation is the central invalidation hub** — it invalidates 5 cache patterns, covering most data domains.
2. **Product CRUD only invalidates its own cache** — `pos:search:*` (which shows product names) is missed.
3. **Cancel/Return skip the SCAN-based invalidation** — they rely on KEYS in the service layer only.
4. **`reports:*` is dead code** — invalidated but never populated.
5. **Vendor module is the most robust** — uses version counters, no blocking KEYS.
6. **No single source of truth for dependencies** — each module independently decides what to invalidate.
