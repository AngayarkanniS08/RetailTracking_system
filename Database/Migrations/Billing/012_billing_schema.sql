-- ============================================================
-- Migration 012: Billing & Invoicing Schema
-- Replaces old sales/sales_items with a full billing system
-- ============================================================

-- ============================================================
-- 1. CUSTOMERS
-- ============================================================
CREATE TABLE IF NOT EXISTS customers (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    name TEXT NOT NULL,
    phone TEXT NOT NULL CHECK (phone ~ '^[0-9]{10,15}$'),
    email TEXT,
    gstin TEXT,
    address TEXT,
    credit_limit DECIMAL(12,2) NOT NULL DEFAULT 0,
    opening_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
    status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'inactive')),
    created_at TIMESTAMPTZ DEFAULT now(),
    updated_at TIMESTAMPTZ DEFAULT now(),
    UNIQUE (user_id, phone)
);

-- ============================================================
-- 2. INVOICES (replaces sales table)
-- ============================================================
CREATE TABLE IF NOT EXISTS invoices (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    invoice_number TEXT NOT NULL,
    customer_id UUID REFERENCES customers(id) ON DELETE SET NULL,

    -- Customer snapshots (frozen at billing time for historical accuracy)
    customer_name_snapshot TEXT,
    customer_phone_snapshot TEXT,
    customer_gstin_snapshot TEXT,

    -- Financial breakdown
    subtotal        DECIMAL(12,2) NOT NULL DEFAULT 0,
    discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_gst       DECIMAL(12,2) NOT NULL DEFAULT 0,
    round_off       DECIMAL(4,2) NOT NULL DEFAULT 0,
    grand_total     DECIMAL(12,2) NOT NULL DEFAULT 0,

    -- Payment tracking (cash only — no gateway)
    amount_paid     DECIMAL(12,2) NOT NULL DEFAULT 0,
    balance_due     DECIMAL(12,2) NOT NULL DEFAULT 0,

    -- Status
    invoice_status  TEXT NOT NULL DEFAULT 'completed'
                    CHECK (invoice_status IN ('completed', 'cancelled', 'returned')),
    payment_status  TEXT NOT NULL DEFAULT 'paid'
                    CHECK (payment_status IN ('paid', 'partial', 'pending')),

    notes TEXT,
    billed_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    created_at TIMESTAMPTZ DEFAULT now(),
    updated_at TIMESTAMPTZ DEFAULT now(),

    UNIQUE (user_id, invoice_number)
);

-- ============================================================
-- 3. INVOICE ITEMS (replaces sales_items table)
-- ============================================================
CREATE TABLE IF NOT EXISTS invoice_items (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    invoice_id UUID NOT NULL REFERENCES invoices(id) ON DELETE CASCADE,
    product_id UUID NOT NULL REFERENCES products(id) ON DELETE RESTRICT,
    batch_id UUID REFERENCES inventory_batches(id) ON DELETE SET NULL,

    -- Snapshots frozen at billing time
    product_name_snapshot TEXT NOT NULL,
    hsn_code_snapshot TEXT,
    unit_snapshot TEXT NOT NULL,

    quantity        DECIMAL(12,3) NOT NULL CHECK (quantity > 0),
    unit_price      DECIMAL(12,2) NOT NULL CHECK (unit_price >= 0),
    cost_price_snapshot DECIMAL(12,2),
    gst_rate_snapshot   DECIMAL(5,2) NOT NULL DEFAULT 0,
    gst_amount      DECIMAL(12,2) NOT NULL DEFAULT 0,
    discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    line_total      DECIMAL(12,2) NOT NULL,

    created_at TIMESTAMPTZ DEFAULT now()
);

-- ============================================================
-- 4. INVOICE RETURNS
-- ============================================================
CREATE TABLE IF NOT EXISTS invoice_returns (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    invoice_id UUID NOT NULL REFERENCES invoices(id) ON DELETE RESTRICT,
    invoice_item_id UUID REFERENCES invoice_items(id) ON DELETE SET NULL,
    product_id UUID NOT NULL REFERENCES products(id) ON DELETE RESTRICT,
    batch_id UUID REFERENCES inventory_batches(id) ON DELETE SET NULL,
    qty_returned DECIMAL(12,3) NOT NULL CHECK (qty_returned > 0),
    refund_amount DECIMAL(12,2) NOT NULL CHECK (refund_amount >= 0),
    restock_qty DECIMAL(12,3) NOT NULL DEFAULT 0,
    reason TEXT,
    created_at TIMESTAMPTZ DEFAULT now()
);

-- ============================================================
-- 5. INVOICE SEQUENCES (per-user, per-year auto-increment)
-- ============================================================
CREATE TABLE IF NOT EXISTS invoice_sequences (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    year TEXT NOT NULL,
    prefix TEXT NOT NULL DEFAULT 'INV',
    last_number INT NOT NULL DEFAULT 0,
    updated_at TIMESTAMPTZ DEFAULT now(),
    UNIQUE (user_id, year, prefix)
);

-- ============================================================
-- 6. STOCK MOVEMENTS (audit trail)
-- ============================================================
CREATE TABLE IF NOT EXISTS stock_movements (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    batch_id UUID REFERENCES inventory_batches(id) ON DELETE SET NULL,
    product_id UUID NOT NULL REFERENCES products(id) ON DELETE RESTRICT,
    reference_type TEXT NOT NULL CHECK (reference_type IN ('PURCHASE', 'SALE', 'RETURN', 'ADJUSTMENT')),
    reference_id UUID,
    movement_type TEXT NOT NULL CHECK (movement_type IN ('IN', 'OUT')),
    qty DECIMAL(12,3) NOT NULL CHECK (qty > 0),
    created_at TIMESTAMPTZ DEFAULT now()
);

-- ============================================================
-- 7. CUSTOMER LEDGER (credit/debit trail)
-- ============================================================
CREATE TABLE IF NOT EXISTS customer_ledger (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    customer_id UUID NOT NULL REFERENCES customers(id) ON DELETE CASCADE,
    invoice_id UUID REFERENCES invoices(id) ON DELETE SET NULL,
    entry_type TEXT NOT NULL CHECK (entry_type IN ('invoice', 'payment', 'return', 'opening')),
    debit DECIMAL(12,2) NOT NULL DEFAULT 0,
    credit DECIMAL(12,2) NOT NULL DEFAULT 0,
    balance DECIMAL(12,2) NOT NULL DEFAULT 0,
    notes TEXT,
    created_at TIMESTAMPTZ DEFAULT now()
);

-- ============================================================
-- 8. DROP OLD TABLES (no data loss — billing is new)
-- ============================================================
DROP TABLE IF EXISTS sales_items;
DROP TABLE IF EXISTS sales;

-- ============================================================
-- INDEXES
-- ============================================================
CREATE INDEX IF NOT EXISTS idx_cust_user_id ON customers(user_id);
CREATE INDEX IF NOT EXISTS idx_cust_phone ON customers(user_id, phone);

CREATE INDEX IF NOT EXISTS idx_inv_user_id ON invoices(user_id);
CREATE INDEX IF NOT EXISTS idx_inv_customer ON invoices(customer_id);
CREATE INDEX IF NOT EXISTS idx_inv_number ON invoices(user_id, invoice_number);
CREATE INDEX IF NOT EXISTS idx_inv_billed_at ON invoices(user_id, billed_at);
CREATE INDEX IF NOT EXISTS idx_inv_status ON invoices(invoice_status);
CREATE INDEX IF NOT EXISTS idx_inv_payment_status ON invoices(payment_status);

CREATE INDEX IF NOT EXISTS idx_inv_items_invoice ON invoice_items(invoice_id);
CREATE INDEX IF NOT EXISTS idx_inv_items_product ON invoice_items(product_id);
CREATE INDEX IF NOT EXISTS idx_inv_items_batch ON invoice_items(batch_id);

CREATE INDEX IF NOT EXISTS idx_inv_ret_invoice ON invoice_returns(invoice_id);
CREATE INDEX IF NOT EXISTS idx_inv_ret_item ON invoice_returns(invoice_item_id);
CREATE INDEX IF NOT EXISTS idx_inv_ret_product ON invoice_returns(product_id);

CREATE INDEX IF NOT EXISTS idx_seq_user_year ON invoice_sequences(user_id, year);

CREATE INDEX IF NOT EXISTS idx_stock_mvmt_batch ON stock_movements(batch_id);
CREATE INDEX IF NOT EXISTS idx_stock_mvmt_product ON stock_movements(product_id);
CREATE INDEX IF NOT EXISTS idx_stock_mvmt_ref ON stock_movements(reference_type, reference_id);
CREATE INDEX IF NOT EXISTS idx_stock_mvmt_user ON stock_movements(user_id, created_at);

CREATE INDEX IF NOT EXISTS idx_ledger_customer ON customer_ledger(customer_id);
CREATE INDEX IF NOT EXISTS idx_ledger_user ON customer_ledger(user_id);

-- ============================================================
-- ROW LEVEL SECURITY
-- ============================================================
ALTER TABLE customers ENABLE ROW LEVEL SECURITY;
ALTER TABLE invoices ENABLE ROW LEVEL SECURITY;
ALTER TABLE invoice_items ENABLE ROW LEVEL SECURITY;
ALTER TABLE invoice_returns ENABLE ROW LEVEL SECURITY;
ALTER TABLE invoice_sequences ENABLE ROW LEVEL SECURITY;
ALTER TABLE stock_movements ENABLE ROW LEVEL SECURITY;
ALTER TABLE customer_ledger ENABLE ROW LEVEL SECURITY;

-- customers
CREATE POLICY customers_isolation ON customers
    USING (user_id = current_setting('app.current_user_id', true)::uuid)
    WITH CHECK (user_id = current_setting('app.current_user_id', true)::uuid);

-- invoices
CREATE POLICY invoices_isolation ON invoices
    USING (user_id = current_setting('app.current_user_id', true)::uuid)
    WITH CHECK (user_id = current_setting('app.current_user_id', true)::uuid);

-- invoice_items (child — isolated via parent)
CREATE POLICY invoice_items_isolation ON invoice_items
    USING (EXISTS (
        SELECT 1 FROM invoices
        WHERE invoices.id = invoice_id
        AND invoices.user_id = current_setting('app.current_user_id', true)::uuid
    ));

-- invoice_returns
CREATE POLICY invoice_returns_isolation ON invoice_returns
    USING (EXISTS (
        SELECT 1 FROM invoices
        WHERE invoices.id = invoice_id
        AND invoices.user_id = current_setting('app.current_user_id', true)::uuid
    ));

-- invoice_sequences
CREATE POLICY invoice_sequences_isolation ON invoice_sequences
    USING (user_id = current_setting('app.current_user_id', true)::uuid)
    WITH CHECK (user_id = current_setting('app.current_user_id', true)::uuid);

-- stock_movements
CREATE POLICY stock_movements_isolation ON stock_movements
    USING (user_id = current_setting('app.current_user_id', true)::uuid)
    WITH CHECK (user_id = current_setting('app.current_user_id', true)::uuid);

-- customer_ledger
CREATE POLICY customer_ledger_isolation ON customer_ledger
    USING (user_id = current_setting('app.current_user_id', true)::uuid)
    WITH CHECK (user_id = current_setting('app.current_user_id', true)::uuid);
