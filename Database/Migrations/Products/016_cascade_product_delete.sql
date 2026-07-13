-- ============================================================
-- CASCADE PRODUCT DELETE
-- Changes FK constraints so deleting a product cascades to
-- inventory (stock_list, inventory_batches) and sets NULL on
-- financial/audit tables (invoices, purchases, returns, stock
-- movements).  Snapshot columns keep historical data intact.
-- ============================================================

-- 1. stock_list → cascade (inventory goes with the product)
-- NOTE: migration 017 converts stock_list to a materialized view,
-- which has no FK constraints, so skip ALTER if it's no longer a BASE TABLE.
DO $$
BEGIN
    IF EXISTS (
        SELECT FROM information_schema.tables
        WHERE table_schema = 'public' AND table_name = 'stock_list'
          AND table_type = 'BASE TABLE'
    ) THEN
        EXECUTE 'ALTER TABLE public.stock_list
                 DROP CONSTRAINT IF EXISTS stock_list_product_id_fkey,
                 ADD CONSTRAINT stock_list_product_id_fkey
                     FOREIGN KEY (product_id) REFERENCES public.products(id) ON DELETE CASCADE';
    END IF;
END $$;

-- 2. invoice_items → SET NULL (product_name_snapshot preserves invoice data)
ALTER TABLE invoice_items
    DROP CONSTRAINT IF EXISTS invoice_items_product_id_fkey,
    ADD CONSTRAINT invoice_items_product_id_fkey
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL;

-- 3. vendor_purchase_items → SET NULL (product_name_snapshot preserves history)
ALTER TABLE vendor_purchase_items
    DROP CONSTRAINT IF EXISTS vendor_purchase_items_product_id_fkey,
    ADD CONSTRAINT vendor_purchase_items_product_id_fkey
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL;

-- 4. stock_movements → SET NULL (keep the audit trail, just drop the link)
ALTER TABLE stock_movements
    DROP CONSTRAINT IF EXISTS stock_movements_product_id_fkey,
    ADD CONSTRAINT stock_movements_product_id_fkey
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL;

-- 5. invoice_returns → allow NULL + SET NULL (traceable via invoice_item_id)
ALTER TABLE invoice_returns
    ALTER COLUMN product_id DROP NOT NULL;
ALTER TABLE invoice_returns
    DROP CONSTRAINT IF EXISTS invoice_returns_product_id_fkey,
    ADD CONSTRAINT invoice_returns_product_id_fkey
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL;

-- 6. sales_items (old table, superseded by invoice_items)
--    Only attempt if the table exists.
DO $$
BEGIN
    IF EXISTS (SELECT FROM information_schema.tables
               WHERE table_schema = 'public' AND table_name = 'sales_items') THEN
        EXECUTE 'ALTER TABLE public.sales_items
                 DROP CONSTRAINT IF EXISTS sales_items_product_id_fkey,
                 ADD CONSTRAINT sales_items_product_id_fkey
                     FOREIGN KEY (product_id) REFERENCES public.products(id) ON DELETE SET NULL';
    END IF;
END $$;
