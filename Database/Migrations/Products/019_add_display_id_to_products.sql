-- ============================================================
-- ADD display_id SERIAL TO PRODUCTS
-- Gives each product a human-friendly auto-incrementing number
-- for display purposes. The UUID remains the internal PK.
-- ============================================================

ALTER TABLE products ADD COLUMN IF NOT EXISTS display_id INTEGER;

-- Backfill existing rows with sequential numbers
UPDATE products
SET display_id = sub.seq
FROM (
    SELECT id, row_number() OVER (ORDER BY created_at, id) AS seq
    FROM products
) sub
WHERE products.id = sub.id AND products.display_id IS NULL;

-- Set NOT NULL after backfill (CREATE SEQUENCE is handled implicitly by SERIAL,
-- but since we added an INT column we use a manual sequence)
CREATE SEQUENCE IF NOT EXISTS products_display_id_seq OWNED BY products.display_id;

-- Update the sequence to start after the highest existing value
SELECT setval('products_display_id_seq', COALESCE((SELECT MAX(display_id) FROM products), 0) + 1, false);

-- Ensure new rows auto-increment (SERIAL creates a sequence, but since we
-- didn't use SERIAL syntax, we set the default manually)
ALTER TABLE products ALTER COLUMN display_id SET DEFAULT nextval('products_display_id_seq');

-- Make it NOT NULL now
ALTER TABLE products ALTER COLUMN display_id SET NOT NULL;

-- Add a unique index for fast lookups
CREATE UNIQUE INDEX IF NOT EXISTS idx_products_display_id ON products(display_id);
