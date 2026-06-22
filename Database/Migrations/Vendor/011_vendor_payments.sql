CREATE TABLE IF NOT EXISTS vendor_payments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    purchase_id UUID NOT NULL REFERENCES vendor_purchases(id) ON DELETE CASCADE,
    amount DECIMAL(12,2) NOT NULL CHECK (amount > 0),
    payment_date TIMESTAMPTZ NOT NULL DEFAULT now(),
    created_at TIMESTAMPTZ DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_vpymt_user_id ON vendor_payments(user_id);
CREATE INDEX IF NOT EXISTS idx_vpymt_purchase_id ON vendor_payments(purchase_id);
CREATE INDEX IF NOT EXISTS idx_vpymt_date ON vendor_payments(user_id, payment_date);

ALTER TABLE vendor_payments ENABLE ROW LEVEL SECURITY;
CREATE POLICY vendor_payments_isolation ON vendor_payments
    USING (user_id = current_setting('app.current_user_id', true)::uuid)
    WITH CHECK (user_id = current_setting('app.current_user_id', true)::uuid);
