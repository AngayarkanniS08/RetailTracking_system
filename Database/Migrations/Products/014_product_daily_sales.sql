-- Product Daily Sales Log
-- Owner-entered daily sales quantities for stock intelligence velocity calculation.
-- Each row records how many units the owner manually reports selling on a given day.
-- Unique per (user_id, product_id, sale_date) so re-entering overwrites.

CREATE TABLE IF NOT EXISTS product_daily_sales (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id     UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    product_id  UUID NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    sale_date   DATE NOT NULL,
    quantity    INT  NOT NULL CHECK (quantity > 0),
    notes       TEXT,
    created_at  TIMESTAMPTZ DEFAULT now(),
    updated_at  TIMESTAMPTZ DEFAULT now(),
    UNIQUE (user_id, product_id, sale_date)
);

CREATE INDEX IF NOT EXISTS idx_pds_product_date   ON product_daily_sales(product_id, sale_date);
CREATE INDEX IF NOT EXISTS idx_pds_user_date       ON product_daily_sales(user_id, sale_date);

ALTER TABLE product_daily_sales ENABLE ROW LEVEL SECURITY;
CREATE POLICY pds_user_isolation ON product_daily_sales
    USING (user_id = current_setting('app.current_user_id')::uuid);
