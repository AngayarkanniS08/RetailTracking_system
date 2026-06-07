-- Add Alert and ROP columns to Products table
ALTER TABLE public.products
ADD COLUMN IF NOT EXISTS daily_sales     INT     DEFAULT 0,
ADD COLUMN IF NOT EXISTS lead_time       INT     DEFAULT 0,
ADD COLUMN IF NOT EXISTS emergency_stock INT     DEFAULT 0,
ADD COLUMN IF NOT EXISTS rop             INT     DEFAULT 0,
ADD COLUMN IF NOT EXISTS alert_triggered BOOLEAN DEFAULT FALSE;