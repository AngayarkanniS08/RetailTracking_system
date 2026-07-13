-- ============================================================
-- FIX NULLABLE FOREIGN KEY COLUMNS
-- The 016 migration changed FK constraints from RESTRICT to
-- SET NULL on product_id columns, but forgot to drop NOT NULL.
-- PostgreSQL cannot SET NULL on a NOT NULL column, which would
-- cause DELETE on products to fail.
-- ============================================================

-- 1. invoice_items.product_id → allow NULL (SET NULL needs this)
ALTER TABLE invoice_items
    ALTER COLUMN product_id DROP NOT NULL;

-- 2. vendor_purchase_items.product_id → allow NULL
ALTER TABLE vendor_purchase_items
    ALTER COLUMN product_id DROP NOT NULL;

-- 3. stock_movements.product_id → allow NULL
ALTER TABLE stock_movements
    ALTER COLUMN product_id DROP NOT NULL;

-- (invoice_returns.product_id was already handled in 016)
