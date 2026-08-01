-- ============================================
-- SCOPED PRODUCT NAME UNIQUENESS
-- Allow same product name in different categories / subcategories.
-- Enforce uniqueness only within (user_id, category_id, subcategory_id, LOWER(name)).
-- ============================================

-- Drop old global unique constraint on (user_id, name) if it exists
ALTER TABLE products DROP CONSTRAINT IF EXISTS products_user_id_name_key;

-- Create scoped unique index for (user_id, category_id, COALESCE(subcategory_id, default_uuid), LOWER(name))
CREATE UNIQUE INDEX IF NOT EXISTS idx_products_user_cat_subcat_name_unique
ON products (
    user_id,
    category_id,
    COALESCE(subcategory_id, '00000000-0000-0000-0000-000000000000'::uuid),
    LOWER(name)
);
