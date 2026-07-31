-- ============================================================
-- DROP product_daily_sales
-- Removes the owner-entered daily sales log, which was only
-- consumed by the removed /products/history feature. The table
-- is empty and unused; policies and indexes drop with it.
-- ============================================================

DROP TABLE IF EXISTS product_daily_sales;
