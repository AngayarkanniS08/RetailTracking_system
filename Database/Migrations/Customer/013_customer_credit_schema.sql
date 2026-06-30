-- ============================================================
-- Migration 013: Customer Credit Schema
-- Payment receipts, sequences, and performance indexes
-- ============================================================

-- ============================================================
-- 1. PAYMENT RECEIPTS (tracks payment receipts separately)
-- ============================================================
CREATE TABLE IF NOT EXISTS payment_receipts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    customer_id UUID NOT NULL REFERENCES customers(id) ON DELETE CASCADE,
    ledger_id UUID NOT NULL REFERENCES customer_ledger(id) ON DELETE RESTRICT,
    receipt_number TEXT NOT NULL,
    amount DECIMAL(12,2) NOT NULL CHECK (amount > 0),
    notes TEXT,
    created_at TIMESTAMPTZ DEFAULT now(),
    UNIQUE (user_id, receipt_number)
);

-- ============================================================
-- 2. PAYMENT RECEIPT SEQUENCES (per-user, per-year auto-increment)
-- ============================================================
CREATE TABLE IF NOT EXISTS payment_receipt_sequences (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    year TEXT NOT NULL,
    prefix TEXT NOT NULL DEFAULT 'PAY',
    last_number INT NOT NULL DEFAULT 0,
    updated_at TIMESTAMPTZ DEFAULT now(),
    UNIQUE (user_id, year, prefix)
);

-- ============================================================
-- 3. PERFORMANCE INDEXES
-- ============================================================

-- For credit dashboard: quickly find customers with outstanding balance
CREATE INDEX IF NOT EXISTS idx_ledger_balance_filter ON customer_ledger(user_id, balance DESC);

-- For customer credit limit checks
CREATE INDEX IF NOT EXISTS idx_customers_credit ON customers(user_id, credit_limit) WHERE status = 'active';

-- For payment receipts lookups
CREATE INDEX IF NOT EXISTS idx_payment_receipts_customer ON payment_receipts(user_id, customer_id);
CREATE INDEX IF NOT EXISTS idx_payment_receipts_ledger ON payment_receipts(ledger_id);
CREATE INDEX IF NOT EXISTS idx_payment_receipts_number ON payment_receipts(user_id, receipt_number);

-- ============================================================
-- 4. ROW LEVEL SECURITY
-- ============================================================
ALTER TABLE payment_receipts ENABLE ROW LEVEL SECURITY;
ALTER TABLE payment_receipt_sequences ENABLE ROW LEVEL SECURITY;

CREATE POLICY payment_receipts_isolation ON payment_receipts
    USING (user_id = current_setting('app.current_user_id', true)::uuid)
    WITH CHECK (user_id = current_setting('app.current_user_id', true)::uuid);

CREATE POLICY payment_receipt_sequences_isolation ON payment_receipt_sequences
    USING (user_id = current_setting('app.current_user_id', true)::uuid)
    WITH CHECK (user_id = current_setting('app.current_user_id', true)::uuid);
