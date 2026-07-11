-- ============================================================
-- Migration 017: RLS Security Hardening
--
-- 1. Add user_id to child tables (vendor_purchase_items, invoice_items, invoice_returns)
-- 2. Enable RLS + per-operation policies on tables missing it
-- 3. Replace all single policies with per-operation policies
-- 4. Add WITH CHECK to all policies
-- 5. Add FORCE ROW LEVEL SECURITY to all tables
-- 6. Add composite indexes (user_id, id)
-- 7. Convert stock_list to materialized view
-- 8. Fix product_daily_sales current_setting
-- ============================================================

-- ============================================================
-- 1. ADD user_id TO CHILD TABLES
-- ============================================================

-- 1a. vendor_purchase_items
ALTER TABLE vendor_purchase_items ADD COLUMN IF NOT EXISTS user_id UUID;

UPDATE vendor_purchase_items vpi
SET user_id = vp.user_id
FROM vendor_purchases vp
WHERE vpi.purchase_id = vp.id
  AND vpi.user_id IS NULL;

ALTER TABLE vendor_purchase_items ALTER COLUMN user_id SET NOT NULL;
ALTER TABLE vendor_purchase_items ADD CONSTRAINT fk_vpi_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;
CREATE INDEX IF NOT EXISTS idx_vpi_user_id ON vendor_purchase_items(user_id);

-- 1b. invoice_items
ALTER TABLE invoice_items ADD COLUMN IF NOT EXISTS user_id UUID;

UPDATE invoice_items ii
SET user_id = i.user_id
FROM invoices i
WHERE ii.invoice_id = i.id
  AND ii.user_id IS NULL;

ALTER TABLE invoice_items ALTER COLUMN user_id SET NOT NULL;
ALTER TABLE invoice_items ADD CONSTRAINT fk_ii_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;
CREATE INDEX IF NOT EXISTS idx_inv_items_user ON invoice_items(user_id);

-- 1c. invoice_returns
ALTER TABLE invoice_returns ADD COLUMN IF NOT EXISTS user_id UUID;

UPDATE invoice_returns ir
SET user_id = i.user_id
FROM invoices i
WHERE ir.invoice_id = i.id
  AND ir.user_id IS NULL;

ALTER TABLE invoice_returns ALTER COLUMN user_id SET NOT NULL;
ALTER TABLE invoice_returns ADD CONSTRAINT fk_ir_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;
CREATE INDEX IF NOT EXISTS idx_inv_ret_user ON invoice_returns(user_id);

-- ============================================================
-- 2. ENABLE RLS ON TABLES MISSING IT
-- ============================================================

ALTER TABLE categories ENABLE ROW LEVEL SECURITY;
ALTER TABLE subcategories ENABLE ROW LEVEL SECURITY;
ALTER TABLE products ENABLE ROW LEVEL SECURITY;
ALTER TABLE vendor_purchase_items ENABLE ROW LEVEL SECURITY;

-- ============================================================
-- 3. DROP OLD SINGLE POLICIES
-- ============================================================

-- 007 dashboard policies
DROP POLICY IF EXISTS "Users can access their own sales" ON public.sales;
DROP POLICY IF EXISTS "Users can access their own sales_items" ON public.sales_items;
DROP POLICY IF EXISTS "Users can access their own purchases" ON public.purchases;
DROP POLICY IF EXISTS "Users can access their own inventory batches" ON public.inventory_batches;
DROP POLICY IF EXISTS "Users can access their own stock list" ON public.stock_list;

-- 012 billing policies
DROP POLICY IF EXISTS customers_isolation ON customers;
DROP POLICY IF EXISTS invoices_isolation ON invoices;
DROP POLICY IF EXISTS invoice_items_isolation ON invoice_items;
DROP POLICY IF EXISTS invoice_returns_isolation ON invoice_returns;
DROP POLICY IF EXISTS invoice_sequences_isolation ON invoice_sequences;
DROP POLICY IF EXISTS stock_movements_isolation ON stock_movements;
DROP POLICY IF EXISTS customer_ledger_isolation ON customer_ledger;

-- 013 credit policies
DROP POLICY IF EXISTS payment_receipts_isolation ON payment_receipts;
DROP POLICY IF EXISTS payment_receipt_sequences_isolation ON payment_receipt_sequences;

-- 014 product daily sales
DROP POLICY IF EXISTS pds_user_isolation ON product_daily_sales;

-- 009 vendor policies
DROP POLICY IF EXISTS vendors_isolation ON vendors;
DROP POLICY IF EXISTS vendor_purchases_isolation ON vendor_purchases;

-- 011 vendor payments
DROP POLICY IF EXISTS vendor_payments_isolation ON vendor_payments;

-- 016 backup policies
DROP POLICY IF EXISTS "Users can access their own backup config" ON public.backup_config;
DROP POLICY IF EXISTS "Users can access their own backup jobs" ON public.backup_jobs;

-- ============================================================
-- 3. CREATE PER-OPERATION POLICIES FOR ALL TABLES
-- ============================================================

-- Helper: create per-operation policies for a table with user_id
DO $$
DECLARE
    tables_info RECORD;
BEGIN
    FOR tables_info IN
        SELECT unnest(ARRAY[
            'categories', 'subcategories', 'products',
            'purchases', 'inventory_batches',
            'customers', 'invoices', 'invoice_sequences',
            'stock_movements', 'customer_ledger',
            'payment_receipts', 'payment_receipt_sequences',
            'vendors', 'vendor_purchases', 'vendor_payments',
            'backup_config', 'backup_jobs',
            'vendor_purchase_items'
        ]) AS tablename
    LOOP
        EXECUTE format(
            'CREATE POLICY %I_select ON %I FOR SELECT USING (user_id = current_setting(''app.current_user_id'', true)::uuid)',
            tables_info.tablename, tables_info.tablename
        );
        EXECUTE format(
            'CREATE POLICY %I_insert ON %I FOR INSERT WITH CHECK (user_id = current_setting(''app.current_user_id'', true)::uuid)',
            tables_info.tablename, tables_info.tablename
        );
        EXECUTE format(
            'CREATE POLICY %I_update ON %I FOR UPDATE USING (user_id = current_setting(''app.current_user_id'', true)::uuid) WITH CHECK (user_id = current_setting(''app.current_user_id'', true)::uuid)',
            tables_info.tablename, tables_info.tablename
        );
        EXECUTE format(
            'CREATE POLICY %I_delete ON %I FOR DELETE USING (user_id = current_setting(''app.current_user_id'', true)::uuid)',
            tables_info.tablename, tables_info.tablename
        );
    END LOOP;
END;
$$;

-- ============================================================
-- 3b. PER-OPERATION POLICIES FOR CHILD TABLES (with user_id)
-- ============================================================

DO $$
DECLARE
    tables_info RECORD;
BEGIN
    FOR tables_info IN
        SELECT unnest(ARRAY['invoice_items', 'invoice_returns']) AS tablename
    LOOP
        EXECUTE format(
            'CREATE POLICY %I_select ON %I FOR SELECT USING (user_id = current_setting(''app.current_user_id'', true)::uuid)',
            tables_info.tablename, tables_info.tablename
        );
        EXECUTE format(
            'CREATE POLICY %I_insert ON %I FOR INSERT WITH CHECK (user_id = current_setting(''app.current_user_id'', true)::uuid)',
            tables_info.tablename, tables_info.tablename
        );
        EXECUTE format(
            'CREATE POLICY %I_update ON %I FOR UPDATE USING (user_id = current_setting(''app.current_user_id'', true)::uuid) WITH CHECK (user_id = current_setting(''app.current_user_id'', true)::uuid)',
            tables_info.tablename, tables_info.tablename
        );
        EXECUTE format(
            'CREATE POLICY %I_delete ON %I FOR DELETE USING (user_id = current_setting(''app.current_user_id'', true)::uuid)',
            tables_info.tablename, tables_info.tablename
        );
    END LOOP;
END;
$$;

-- ============================================================
-- 4. ADD FORCE ROW LEVEL SECURITY TO ALL TABLES
-- ============================================================

ALTER TABLE categories FORCE ROW LEVEL SECURITY;
ALTER TABLE subcategories FORCE ROW LEVEL SECURITY;
ALTER TABLE products FORCE ROW LEVEL SECURITY;
ALTER TABLE customers FORCE ROW LEVEL SECURITY;
ALTER TABLE invoices FORCE ROW LEVEL SECURITY;
ALTER TABLE invoice_items FORCE ROW LEVEL SECURITY;
ALTER TABLE invoice_returns FORCE ROW LEVEL SECURITY;
ALTER TABLE invoice_sequences FORCE ROW LEVEL SECURITY;
ALTER TABLE stock_movements FORCE ROW LEVEL SECURITY;
ALTER TABLE customer_ledger FORCE ROW LEVEL SECURITY;
ALTER TABLE purchases FORCE ROW LEVEL SECURITY;
ALTER TABLE inventory_batches FORCE ROW LEVEL SECURITY;
ALTER TABLE payment_receipts FORCE ROW LEVEL SECURITY;
ALTER TABLE payment_receipt_sequences FORCE ROW LEVEL SECURITY;
ALTER TABLE vendors FORCE ROW LEVEL SECURITY;
ALTER TABLE vendor_purchases FORCE ROW LEVEL SECURITY;
ALTER TABLE vendor_purchase_items FORCE ROW LEVEL SECURITY;
ALTER TABLE vendor_payments FORCE ROW LEVEL SECURITY;
ALTER TABLE product_daily_sales FORCE ROW LEVEL SECURITY;
ALTER TABLE backup_config FORCE ROW LEVEL SECURITY;
ALTER TABLE backup_jobs FORCE ROW LEVEL SECURITY;

-- ============================================================
-- 5. ADD COMPOSITE INDEXES (user_id, id)
-- ============================================================

CREATE INDEX IF NOT EXISTS idx_categories_user_id ON categories(user_id, id);
CREATE INDEX IF NOT EXISTS idx_subcategories_user_id ON subcategories(user_id, id);
CREATE INDEX IF NOT EXISTS idx_products_user_id ON products(user_id, id);
CREATE INDEX IF NOT EXISTS idx_customers_user_id ON customers(user_id, id);
CREATE INDEX IF NOT EXISTS idx_invoices_user_id ON invoices(user_id, id);
CREATE INDEX IF NOT EXISTS idx_invoice_items_user_id ON invoice_items(user_id, id);
CREATE INDEX IF NOT EXISTS idx_invoice_returns_user_id ON invoice_returns(user_id, id);
CREATE INDEX IF NOT EXISTS idx_invoice_sequences_user_id ON invoice_sequences(user_id, id);
CREATE INDEX IF NOT EXISTS idx_stock_movements_user_id ON stock_movements(user_id, id);
CREATE INDEX IF NOT EXISTS idx_customer_ledger_user_id ON customer_ledger(user_id, id);
CREATE INDEX IF NOT EXISTS idx_purchases_user_id ON purchases(user_id, id);
CREATE INDEX IF NOT EXISTS idx_inventory_batches_user_id ON inventory_batches(user_id, id);
CREATE INDEX IF NOT EXISTS idx_payment_receipts_user_id ON payment_receipts(user_id, id);
CREATE INDEX IF NOT EXISTS idx_payment_receipt_sequences_user_id ON payment_receipt_sequences(user_id, id);
CREATE INDEX IF NOT EXISTS idx_vendors_user_id ON vendors(user_id, id);
CREATE INDEX IF NOT EXISTS idx_vendor_purchases_user_id ON vendor_purchases(user_id, id);
CREATE INDEX IF NOT EXISTS idx_vendor_purchase_items_user_id ON vendor_purchase_items(user_id, id);
CREATE INDEX IF NOT EXISTS idx_vendor_payments_user_id ON vendor_payments(user_id, id);
CREATE INDEX IF NOT EXISTS idx_product_daily_sales_user_id ON product_daily_sales(user_id, id);
CREATE INDEX IF NOT EXISTS idx_backup_config_user_id ON backup_config(user_id, id);
CREATE INDEX IF NOT EXISTS idx_backup_jobs_user_id ON backup_jobs(user_id, id);

-- ============================================================
-- 5. CONVERT stock_list TO MATERIALIZED VIEW
-- ============================================================

DROP TABLE IF EXISTS public.stock_list CASCADE;

CREATE MATERIALIZED VIEW public.stock_list AS
SELECT
    ib.user_id,
    ib.product_id,
    COALESCE(SUM(ib.remaining_qty), 0)::INT AS quantity
FROM public.inventory_batches ib
GROUP BY ib.user_id, ib.product_id
WITH DATA;

CREATE UNIQUE INDEX IF NOT EXISTS idx_stock_list_user_product
ON public.stock_list(user_id, product_id);

CREATE OR REPLACE FUNCTION public.refresh_stock_list_mv()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    REFRESH MATERIALIZED VIEW CONCURRENTLY public.stock_list;
    RETURN NULL;
END;
$$;

CREATE TRIGGER trg_refresh_stock_list
AFTER INSERT OR UPDATE OR DELETE ON public.inventory_batches
FOR EACH STATEMENT
EXECUTE FUNCTION public.refresh_stock_list_mv();

-- ============================================================
-- 6. FIX product_daily_sales current_setting
-- ============================================================

DROP POLICY IF EXISTS pds_user_isolation ON product_daily_sales;

CREATE POLICY product_daily_sales_select ON product_daily_sales
    FOR SELECT
    USING (user_id = current_setting('app.current_user_id', true)::uuid);

CREATE POLICY product_daily_sales_insert ON product_daily_sales
    FOR INSERT
    WITH CHECK (user_id = current_setting('app.current_user_id', true)::uuid);

CREATE POLICY product_daily_sales_update ON product_daily_sales
    FOR UPDATE
    USING (user_id = current_setting('app.current_user_id', true)::uuid)
    WITH CHECK (user_id = current_setting('app.current_user_id', true)::uuid);

CREATE POLICY product_daily_sales_delete ON product_daily_sales
    FOR DELETE
    USING (user_id = current_setting('app.current_user_id', true)::uuid);
