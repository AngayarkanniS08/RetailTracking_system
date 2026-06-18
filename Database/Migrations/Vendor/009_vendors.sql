CREATE TABLE IF NOT EXISTS vendors (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    name TEXT NOT NULL,
    
    -- Strict numeric constraint: allows only digits (0-9) and 10 to 15 digits long
    contact_info TEXT NOT NULL CHECK (contact_info ~ '^[0-9]{10,15}$'),
    
    created_at TIMESTAMPTZ DEFAULT now(),
    updated_at TIMESTAMPTZ DEFAULT now(),
    CONSTRAINT unique_user_vendor UNIQUE (user_id, name)
);

CREATE TABLE IF NOT EXISTS vendor_purchases (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    vendor_id UUID NOT NULL REFERENCES vendors(id) ON DELETE RESTRICT,
    
    invoice_number TEXT,                       -- Essential for tracking physical bills
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0 CHECK (total_amount >= 0),
    amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0 CHECK (amount_paid >= 0 AND amount_paid <= total_amount),
    
    purchase_date TIMESTAMPTZ NOT NULL DEFAULT now(),
    created_at TIMESTAMPTZ DEFAULT now(),
    updated_at TIMESTAMPTZ DEFAULT now()
);

CREATE TABLE IF NOT EXISTS vendor_purchase_items (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    purchase_id UUID NOT NULL REFERENCES vendor_purchases(id) ON DELETE CASCADE,
    product_id UUID NOT NULL REFERENCES products(id) ON DELETE RESTRICT,
    
    -- Good Denormalization: Snapshot of name & cost at the exact moment of purchase
    product_name_snapshot TEXT NOT NULL, 
    quantity DECIMAL(12,3) NOT NULL CHECK (quantity > 0),
    unit_cost DECIMAL(12,2) NOT NULL CHECK (unit_cost >= 0)
);

-- Index foreign keys for fast joins
CREATE INDEX IF NOT EXISTS idx_vp_user_id ON vendor_purchases(user_id);
CREATE INDEX IF NOT EXISTS idx_vp_vendor_id ON vendor_purchases(vendor_id);
CREATE INDEX IF NOT EXISTS idx_vpi_purchase_id ON vendor_purchase_items(purchase_id);
CREATE INDEX IF NOT EXISTS idx_vpi_product_id ON vendor_purchase_items(product_id);

-- Composite index for your dashboard/reporting date filtering
CREATE INDEX IF NOT EXISTS idx_vp_user_date ON vendor_purchases(user_id, purchase_date);

-- Secure Vendors
ALTER TABLE vendors ENABLE ROW LEVEL SECURITY;
CREATE POLICY vendors_isolation ON vendors
    USING (user_id = current_setting('app.current_user_id', true)::uuid)
    WITH CHECK (user_id = current_setting('app.current_user_id', true)::uuid);

-- Secure Purchase Orders
ALTER TABLE vendor_purchases ENABLE ROW LEVEL SECURITY;
CREATE POLICY vendor_purchases_isolation ON vendor_purchases
    USING (user_id = current_setting('app.current_user_id', true)::uuid)
    WITH CHECK (user_id = current_setting('app.current_user_id', true)::uuid);

